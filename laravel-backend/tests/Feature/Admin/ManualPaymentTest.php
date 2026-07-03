<?php

declare(strict_types=1);

use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Support\Money;
use Database\Seeders\PermissionRoleSeeder;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->seed(PermissionRoleSeeder::class);
});

function paymentManager(): User
{
    $user = User::factory()->create();
    $user->assignRole('admin'); // orders.manage

    return $user;
}

function paymentViewer(): User
{
    $user = User::factory()->create();
    $user->assignRole('sub-admin'); // orders.view only

    return $user;
}

function manualOrder(int $totalMinor = 300000, int $paidMinor = 0): Order
{
    return Order::factory()->create([
        'total' => Money::fromMinor($totalMinor),
        'advance_paid' => Money::fromMinor($paidMinor),
        'payment_status' => $paidMinor > 0 ? 'partial' : 'unpaid',
    ]);
}

it('records a manual credit and reconciles advance_paid (taka -> paisa)', function () {
    $order = manualOrder(300000, 0);

    actingAs(paymentManager())
        ->post("/admin/orders/{$order->id}/payments", [
            'direction' => 'credit',
            'amount' => '1000', // ৳1000 → 100000 paisa
            'note' => 'bKash received',
        ])->assertRedirect();

    $payment = $order->payments()->firstOrFail();
    expect($payment->amount->toMinor())->toBe(100000)
        ->and($payment->direction)->toBe('credit')
        ->and($payment->gateway)->toBe('manual')
        ->and($payment->type)->toBe('manual')
        ->and($payment->note)->toBe('bKash received');

    expect($order->refresh()->advance_paid->toMinor())->toBe(100000)
        ->and($order->payment_status)->toBe('partial');
});

it('records a manual debit (refund) and reduces advance_paid', function () {
    $order = manualOrder(300000, 200000); // ৳2000 already paid
    // A prior credit so the ledger reconciles to the ৳2000 starting point.
    Payment::create([
        'order_id' => $order->id, 'gateway' => 'sslcommerz', 'amount' => Money::fromMinor(200000),
        'type' => 'partial', 'direction' => 'credit', 'tran_id' => 'T-SEED-1', 'status' => 'success',
    ]);

    actingAs(paymentManager())
        ->post("/admin/orders/{$order->id}/payments", [
            'direction' => 'debit',
            'amount' => '500', // refund ৳500
            'note' => 'Partial refund for damage',
        ])->assertRedirect();

    expect($order->refresh()->advance_paid->toMinor())->toBe(150000); // 2000 - 500
});

it('never touches the original gateway payment row', function () {
    $order = manualOrder(300000, 200000);
    $gateway = Payment::create([
        'order_id' => $order->id, 'gateway' => 'sslcommerz', 'amount' => Money::fromMinor(200000),
        'type' => 'partial', 'direction' => 'credit', 'tran_id' => 'T-KEEP-1', 'status' => 'success',
    ]);

    actingAs(paymentManager())
        ->post("/admin/orders/{$order->id}/payments", [
            'direction' => 'credit', 'amount' => '1000', 'note' => 'Cash top-up',
        ])->assertRedirect();

    // Original row unchanged; a NEW row was appended.
    expect($gateway->refresh()->amount->toMinor())->toBe(200000)
        ->and($gateway->tran_id)->toBe('T-KEEP-1')
        ->and($order->payments()->count())->toBe(2);
});

it('rejects a refund larger than the amount paid', function () {
    $order = manualOrder(300000, 100000); // only ৳1000 paid
    Payment::create([
        'order_id' => $order->id, 'gateway' => 'sslcommerz', 'amount' => Money::fromMinor(100000),
        'type' => 'partial', 'direction' => 'credit', 'tran_id' => 'T-SEED-2', 'status' => 'success',
    ]);

    actingAs(paymentManager())
        ->post("/admin/orders/{$order->id}/payments", [
            'direction' => 'debit', 'amount' => '2000', 'note' => 'Too much',
        ])->assertSessionHasErrors('amount');

    expect($order->refresh()->advance_paid->toMinor())->toBe(100000); // unchanged
});

it('requires a note', function () {
    $order = manualOrder();

    actingAs(paymentManager())
        ->post("/admin/orders/{$order->id}/payments", [
            'direction' => 'credit', 'amount' => '1000',
        ])->assertSessionHasErrors('note');
});

it('requires a positive amount', function () {
    $order = manualOrder();

    actingAs(paymentManager())
        ->post("/admin/orders/{$order->id}/payments", [
            'direction' => 'credit', 'amount' => '0', 'note' => 'x',
        ])->assertSessionHasErrors('amount');
});

it('forbids a viewer without orders.manage', function () {
    $order = manualOrder();

    actingAs(paymentViewer())
        ->post("/admin/orders/{$order->id}/payments", [
            'direction' => 'credit', 'amount' => '1000', 'note' => 'x',
        ])->assertForbidden();

    expect($order->payments()->count())->toBe(0);
});

it('marks the order paid when the ledger covers the total', function () {
    $order = manualOrder(300000, 0);

    actingAs(paymentManager())
        ->post("/admin/orders/{$order->id}/payments", [
            'direction' => 'credit', 'amount' => '3000', 'note' => 'Full cash',
        ])->assertRedirect();

    expect($order->refresh()->payment_status)->toBe('paid')
        ->and($order->status)->not->toBe('confirmed'); // payment never auto-confirms
});
