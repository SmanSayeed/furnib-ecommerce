<?php

declare(strict_types=1);

use App\Models\Customer;
use App\Models\Order;
use App\Support\Money;

function statusOrder(int $totalMinor, int $advancePaidMinor): Order
{
    $customer = Customer::factory()->create(['mobile' => '+8801712345678']);

    return Order::factory()->create([
        'customer_id' => $customer->id,
        'total' => Money::fromMinor($totalMinor),
        'advance_amount' => Money::fromMinor($advancePaidMinor),
        'advance_paid' => Money::fromMinor($advancePaidMinor),
        'payment_status' => $advancePaidMinor > 0 ? 'partial' : 'unpaid',
    ]);
}

it('returns paid/due for the correct mobile', function () {
    $order = statusOrder(280000, 147100); // total ৳2800, advance paid ৳1471

    $this->postJson("/api/v1/orders/{$order->order_no}/status", ['mobile' => '01712345678'])
        ->assertOk()
        ->assertJsonPath('data.order_no', $order->order_no)
        ->assertJsonPath('data.advance_paid.minor', 147100)
        ->assertJsonPath('data.due.minor', 132900) // 280000 - 147100
        ->assertJsonPath('data.advance_required', true);
});

it('rejects a wrong mobile with a generic 404 (no IDOR)', function () {
    $order = statusOrder(280000, 0);

    $this->postJson("/api/v1/orders/{$order->order_no}/status", ['mobile' => '01999999999'])
        ->assertNotFound()
        ->assertJsonPath('error.code', 'not_found');
});

it('rejects an invalid mobile without leaking existence', function () {
    $order = statusOrder(280000, 0);

    $this->postJson("/api/v1/orders/{$order->order_no}/status", ['mobile' => 'not-a-number'])
        ->assertNotFound();
});

it('answers 404 for an unknown order number', function () {
    $this->postJson('/api/v1/orders/FNB-00000000-0000/status', ['mobile' => '01712345678'])
        ->assertNotFound();
});
