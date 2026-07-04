<?php

declare(strict_types=1);

use App\Models\Customer;
use App\Models\Order;
use App\Support\Orders\PayLink;

function payableCodOrder(): Order
{
    $customer = Customer::factory()->create(['name' => 'Karim', 'mobile' => '+8801712345678']);

    return Order::factory()->create([
        'customer_id' => $customer->id,
        'subtotal' => 4500,
        'shipping_cost' => 150,
        'total' => 4650,
        'advance_paid' => 0,
        'payment_status' => 'unpaid',
    ]);
}

it('signs and verifies a pay link token', function () {
    $order = payableCodOrder();
    $token = PayLink::token($order->order_no);

    expect(PayLink::verify($order->order_no, $token))->toBeTrue()
        ->and(PayLink::verify($order->order_no, 'wrong'))->toBeFalse()
        ->and(PayLink::verify($order->order_no, ''))->toBeFalse();

    expect(PayLink::for($order))->toContain('/pay/'.$order->order_no.'?t='.$token);
});

it('returns the order summary for a valid token', function () {
    $order = payableCodOrder();
    $token = PayLink::token($order->order_no);

    $this->getJson("/api/v1/pay/{$order->order_no}/summary?t={$token}")
        ->assertOk()
        ->assertJsonPath('data.order_no', $order->order_no)
        ->assertJsonPath('data.customer_name', 'Karim')
        ->assertJsonPath('data.due_minor', 465000)
        ->assertJsonPath('data.shipping_minor', 15000)
        ->assertJsonPath('data.can_pay_shipping', true)
        ->assertJsonPath('data.can_pay_full', true);
});

it('rejects a bad or missing token with a generic 404 (no IDOR)', function () {
    $order = payableCodOrder();

    $this->getJson("/api/v1/pay/{$order->order_no}/summary?t=forged")->assertNotFound();
    $this->getJson("/api/v1/pay/{$order->order_no}/summary")->assertNotFound();
});

it('offers no payment buttons once the order is fully paid', function () {
    $order = payableCodOrder();
    $order->update(['advance_paid' => 4650, 'payment_status' => 'paid']);
    $token = PayLink::token($order->order_no);

    $this->getJson("/api/v1/pay/{$order->order_no}/summary?t={$token}")
        ->assertOk()
        ->assertJsonPath('data.can_pay_full', false)
        ->assertJsonPath('data.can_pay_shipping', false)
        ->assertJsonPath('data.due_minor', 0);
});
