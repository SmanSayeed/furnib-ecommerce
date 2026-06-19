<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderItem>
 */
class OrderItemFactory extends Factory
{
    protected $model = OrderItem::class;

    public function definition(): array
    {
        $price = fake()->randomFloat(2, 200, 5000);
        $qty = fake()->numberBetween(1, 4);

        return [
            'order_id' => Order::factory(),
            'product_id' => Product::factory(),
            'title' => fake()->randomElement([
                'Oak Chair', 'Walnut Table', 'Velvet Sofa', 'Pine Shelf', 'Teak Bed', 'Rattan Stool',
            ]),
            'sku' => 'FNB-'.fake()->unique()->numerify('######'),
            // display amounts; Money cast stores minor units
            'price' => $price,
            'qty' => $qty,
            'line_total' => $price * $qty,
        ];
    }
}
