<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        $subtotal = fake()->randomFloat(2, 500, 20000);
        $shipping = fake()->randomElement([60, 80, 120, 150]);

        return [
            'order_no' => 'FNB-'.now()->format('Ymd').'-'.fake()->unique()->numerify('####'),
            'customer_id' => Customer::factory(),
            'status' => 'pending',
            'payment_status' => 'unpaid',
            // display amounts; Money cast stores minor units
            'subtotal' => $subtotal,
            'shipping_cost' => $shipping,
            'total' => $subtotal + $shipping,
            'advance_amount' => 0,
            'advance_paid' => 0,
            'shipping_zone_id' => null,
            'address' => fake()->address(),
            'customer_ip' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
            'notes' => null,
        ];
    }

    public function status(string $status): static
    {
        return $this->state(fn (): array => ['status' => $status]);
    }
}
