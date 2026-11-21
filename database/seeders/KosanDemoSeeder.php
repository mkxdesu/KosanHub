<?php

namespace Database\Seeders;

use App\Models\Contract;
use App\Models\Property;
use App\Models\Room;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class KosanDemoSeeder extends Seeder
{
    public function run(): void
    {
        $property = Property::updateOrCreate(
            ['name' => 'KosanHub Demo'],
            ['address' => 'Jl. Contoh No. 1', 'city' => 'Surabaya']
        );

        $room = Room::updateOrCreate(
            ['property_id' => $property->id, 'room_number' => 'A-01'],
            ['monthly_rent' => 1500000, 'status' => 'occupied']
        );

        $tenant = Tenant::updateOrCreate(
            ['email' => 'demo.tenant@example.com'],
            ['name' => 'Demo Tenant', 'phone' => '081234567890']
        );

        Contract::firstOrCreate(
            ['room_id' => $room->id, 'status' => 'active'],
            [
                'tenant_id' => $tenant->id,
                'start_date' => now()->toDateString(),
                'monthly_rent' => $room->monthly_rent,
            ]
        );
    }
}
