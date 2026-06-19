<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\OtpCode;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<OtpCode>
 */
class OtpCodeFactory extends Factory
{
    protected $model = OtpCode::class;

    public function definition(): array
    {
        return [
            'mobile' => '+8801'.fake()->numberBetween(3, 9).fake()->numerify('########'),
            'code' => Hash::make('123456'),
            'expires_at' => now()->addMinutes(5),
            'attempts' => 0,
        ];
    }

    public function expired(): static
    {
        return $this->state(fn (): array => ['expires_at' => now()->subMinute()]);
    }
}
