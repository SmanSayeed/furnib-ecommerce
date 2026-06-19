<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Order;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'gateway' => 'sslcommerz',
            'amount' => 100000,
            'type' => Payment::TYPE_FULL,
            'tran_id' => 'FNBPAY-'.Str::upper(Str::random(16)),
            'val_id' => null,
            'status' => Payment::STATUS_PENDING,
            'raw_payload' => null,
        ];
    }

    public function success(): static
    {
        return $this->state(fn (): array => ['status' => Payment::STATUS_SUCCESS]);
    }
}
