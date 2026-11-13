<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\Request;

class TenantController extends Controller
{
    public function index()
    {
        return response()->json(
            Tenant::withCount('contracts')->latest()->get()
        );
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'              => 'required|string|max:255',
            'phone'             => 'nullable|string|max:20',
            'email'             => 'nullable|email|max:255',
            'id_card_number'    => 'nullable|string|max:30',
            'address'           => 'nullable|string',
            'emergency_contact' => 'nullable|string|max:20',
        ]);

        $tenant = Tenant::create($data);

        return response()->json($tenant, 201);
    }

    public function show(Tenant $tenant)
    {
        return response()->json(
            $tenant->load(['contracts.room.property', 'activeContracts'])
        );
    }

    public function update(Request $request, Tenant $tenant)
    {
        $data = $request->validate([
            'name'              => 'sometimes|string|max:255',
            'phone'             => 'nullable|string|max:20',
            'email'             => 'nullable|email|max:255',
            'id_card_number'    => 'nullable|string|max:30',
            'address'           => 'nullable|string',
            'emergency_contact' => 'nullable|string|max:20',
        ]);

        $tenant->update($data);

        return response()->json($tenant);
    }

    public function destroy(Tenant $tenant)
    {
        if ($tenant->activeContracts()->exists()) {
            return response()->json([
                'message' => 'Tidak bisa hapus penyewa yang masih punya kontrak aktif.',
            ], 422);
        }

        $tenant->delete();

        return response()->json(['message' => 'Tenant deleted']);
    }
}