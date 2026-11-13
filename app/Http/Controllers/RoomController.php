<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Room;
use Illuminate\Http\Request;

class RoomController extends Controller
{
    public function index(Request $request)
    {
        $query = Room::with(['property', 'activeContract.tenant']);

        if ($request->filled('property_id')) {
            $query->where('property_id', $request->property_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return response()->json($query->latest()->get());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'property_id'  => 'required|exists:properties,id',
            'room_number'  => 'required|string|max:50',
            'monthly_rent' => 'required|numeric|min:0',
            'status'       => 'nullable|in:available,occupied,maintenance',
            'floor'        => 'nullable|integer|min:0',
            'description'  => 'nullable|string',
        ]);

        $exists = Room::where('property_id', $data['property_id'])
            ->where('room_number', $data['room_number'])
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => 'Nomor kamar sudah ada di properti ini.',
            ], 422);
        }

        $room = Room::create($data);

        return response()->json($room->load('property'), 201);
    }

    public function show(Room $room)
    {
        return response()->json(
            $room->load(['property', 'contracts.tenant', 'activeContract.tenant'])
        );
    }

    public function update(Request $request, Room $room)
    {
        $data = $request->validate([
            'room_number'  => 'sometimes|string|max:50',
            'monthly_rent' => 'sometimes|numeric|min:0',
            'status'       => 'sometimes|in:available,occupied,maintenance',
            'floor'        => 'nullable|integer|min:0',
            'description'  => 'nullable|string',
        ]);

        $room->update($data);

        return response()->json($room->load('property'));
    }

    public function destroy(Room $room)
    {
        if ($room->activeContract()->exists()) {
            return response()->json([
                'message' => 'Tidak bisa hapus kamar yang masih punya kontrak aktif.',
            ], 422);
        }

        $room->delete();

        return response()->json(['message' => 'Room deleted']);
    }
}