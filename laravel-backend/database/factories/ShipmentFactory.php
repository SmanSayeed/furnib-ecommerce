<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Order;
use App\Models\Shipment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Shipment>
 */
class ShipmentFactory extends Factory
{
    protected $model = Shipment::class;

    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'courier' => 'steadfast',
            'consignment_id' => null,
            'tracking_code' => null,
            'status' => Shipment::STATUS_PENDING,
            'recipient_name' => fake()->name(),
            'recipient_phone' => '+8801'.fake()->numberBetween(3, 9).fake()->numerify('########'),
            'recipient_address' => fake()->address(),
            'cod_amount' => 0,
            'note' => null,
            'raw_payload' => null,
        ];
    }
}
