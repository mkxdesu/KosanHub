<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Property;
use Illuminate\Http\Request;

class PropertyController extends Controller
{
    public function index()
    {
        return response()->json(
            Property::withCount('rooms')->latest()->get()
        );
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'        => 'required|string|max:255',
            'address'     => 'required|string',
            'city'        => 'nullable|string|max:100',
            'owner_name'  => 'nullable|string|max:255',
            'owner_phone' => 'nullable|string|max:20',
        ]);

        $property = Property::create($data);

        return response()->json($property, 201);
    }

    public function show(Property $property)
    {
        return response()->json(
            $property->load(['rooms', 'rooms.activeContract.tenant'])
        );
    }

    public function update(Request $request, Property $property)
    {
        $data = $request->validate([
            'name'        => 'sometimes|string|max:255',
            'address'     => 'sometimes|string',
            'city'        => 'nullable|string|max:100',
            'owner_name'  => 'nullable|string|max:255',
            'owner_phone' => 'nullable|string|max:20',
        ]);

        $property->update($data);

        return response()->json($property);
    }

    public function destroy(Property $property)
    {
        $property->delete();

        return response()->json(['message' => 'Property deleted']);
    }
}