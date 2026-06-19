<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ShippingZone;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShippingZone>
 */
class ShippingZoneFactory extends Factory
{
    protected $model = ShippingZone::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->randomElement([
                'Inside Dhaka', 'Outside Dhaka', 'Dhaka Sub-urban', 'Chattogram', 'Sylhet',
            ]).' '.fake()->unique()->numberBetween(1, 9999),
            // display amount; the Money cast stores it as minor units
            'cost' => fake()->randomFloat(2, 50, 200),
            'status' => true,
            'position_order' => fake()->numberBetween(0, 20),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['status' => false]);
    }
}
