<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductShippingCharge;
use App\Models\ShippingZone;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductShippingCharge>
 */
class ProductShippingChargeFactory extends Factory
{
    protected $model = ProductShippingCharge::class;

    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'shipping_zone_id' => ShippingZone::factory(),
            // display amount; the Money cast stores it as minor units
            'extra_cost' => fake()->randomFloat(2, 10, 100),
        ];
    }
}
