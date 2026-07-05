<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Courier;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Courier>
 */
class CourierFactory extends Factory
{
    protected $model = Courier::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1, 99999),
            'driver' => Courier::DRIVER_MANUAL,
            'is_active' => true,
            'is_default' => false,
            'position_order' => 0,
            'config' => null,
        ];
    }

    /** @param array<string, mixed> $config */
    public function steadfast(array $config = ['api_key' => 'key', 'secret_key' => 'secret']): static
    {
        // Slug is left to definition() (unique) so this never clashes with the
        // seeded default 'steadfast' courier that migrations create.
        return $this->state(fn (): array => [
            'name' => 'Steadfast',
            'driver' => Courier::DRIVER_STEADFAST,
            'config' => $config,
        ]);
    }

    public function manual(): static
    {
        return $this->state(fn (): array => ['driver' => Courier::DRIVER_MANUAL, 'config' => null]);
    }

    public function default(): static
    {
        return $this->state(fn (): array => ['is_default' => true, 'is_active' => true]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
