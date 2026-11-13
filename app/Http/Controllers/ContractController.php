<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Contract;
use App\Models\Room;
use App\Services\ContractService;
use Illuminate\Http\Request;

class ContractController extends Controller
{
    public function __construct(
        protected ContractService $contractService
    ) {}

    public function index(Request $request)
    {
        $query = Contract::with(['room.property', 'tenant']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return response()->json($query->latest()->get());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'room_id'      => 'required|exists:rooms,id',
            'tenant_id'    => 'required|exists:tenants,id',
            'start_date'   => 'required|date',
            'end_date'     => 'nullable|date|after:start_date',
            'monthly_rent' => 'nullable|numeric|min:0',
            'notes'        => 'nullable|string',
        ]);

        $room = Room::findOrFail($data['room_id']);

        // Aturan bisnis: 1 kamar = 1 kontrak aktif
        if ($room->activeContract()->exists()) {
            return response()->json([
                'message' => 'Kamar ini sudah memiliki kontrak aktif. Tidak boleh double booking.',
            ], 422);
        }

        if ($room->status !== 'available') {
            return response()->json([
                'message' => 'Kamar tidak tersedia (status: ' . $room->status . ').',
            ], 422);
        }

        $contract = Contract::create([
            'room_id'      => $data['room_id'],
            'tenant_id'    => $data['tenant_id'],
            'start_date'   => $data['start_date'],
            'end_date'     => $data['end_date'] ?? null,
            'monthly_rent' => $data['monthly_rent'] ?? $room->monthly_rent,
            'status'       => 'active',
            'notes'        => $data['notes'] ?? null,
        ]);

        $room->update(['status' => 'occupied']);

        return response()->json(
            $contract->load(['room.property', 'tenant']),
            201
        );
    }

    public function show(Contract $contract)
    {
        return response()->json(
            $contract->load(['room.property', 'tenant', 'invoices.payments'])
        );
    }

    public function end(Contract $contract, Request $request)
    {
        if ($contract->status !== 'active') {
            return response()->json([
                'message' => 'Hanya kontrak aktif yang bisa diakhiri.',
            ], 422);
        }

        $contract->update([
            'status'   => 'ended',
            'end_date' => $request->input('end_date', now()->toDateString()),
        ]);

        $contract->room->update(['status' => 'available']);

        return response()->json(
            $contract->fresh(['room.property', 'tenant'])
        );
    }

    public function destroy(Contract $contract)
    {
        if ($contract->status === 'active') {
            $contract->room->update(['status' => 'available']);
        }

        $contract->delete();

        return response()->json(['message' => 'Contract deleted']);
    }
}