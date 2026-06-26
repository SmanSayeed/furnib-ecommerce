<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\ShippingZone;
use Illuminate\Database\Seeder;

/**
 * Sensible default delivery areas so checkout works out of the box. Idempotent
 * (matched by name) — safe to re-run. Costs are display Taka; the MoneyCast
 * converts them to minor units. Admins can edit/add zones in Admin → Shipping.
 */
class ShippingZoneSeeder extends Seeder
{
    public function run(): void
    {
        $zones = [
            ['name' => 'Inside Dhaka', 'cost' => 80, 'position_order' => 1],
            ['name' => 'Dhaka Sub-urban', 'cost' => 130, 'position_order' => 2],
            ['name' => 'Outside Dhaka', 'cost' => 150, 'position_order' => 3],
        ];

        foreach ($zones as $zone) {
            ShippingZone::query()->firstOrCreate(
                ['name' => $zone['name']],
                [
                    'cost' => $zone['cost'],
                    'status' => true,
                    'position_order' => $zone['position_order'],
                ],
            );
        }
    }
}
