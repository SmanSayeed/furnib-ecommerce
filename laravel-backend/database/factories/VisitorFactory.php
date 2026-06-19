<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Visitor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Visitor>
 */
class VisitorFactory extends Factory
{
    protected $model = Visitor::class;

    public function definition(): array
    {
        return [
            'session_id' => fake()->uuid(),
            'ip' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
            'path' => '/'.fake()->slug(),
            'referrer' => null,
            'utm_source' => null,
            'utm_medium' => null,
            'utm_campaign' => null,
        ];
    }
}
