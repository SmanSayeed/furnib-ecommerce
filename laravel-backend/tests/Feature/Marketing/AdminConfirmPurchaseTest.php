<?php

declare(strict_types=1);

use App\Actions\Marketing\ConfirmOrderPurchase;
use App\Models\Order;
use App\Models\User;
use App\Support\Capi\ConversionApi;
use App\Support\Capi\FakeConversionApi;
use Database\Seeders\PermissionRoleSeeder;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    cache()->flush();
    $this->seed(PermissionRoleSeeder::class);
    $this->capi = new FakeConversionApi;
    $this->app->instance(ConversionApi::class, $this->capi);
});

function orderManager(): User
{
    $user = User::factory()->create();
    $user->assignRole('admin'); // orders.manage

    return $user;
}

it('fires exactly one server-side Purchase when the admin confirms an order', function () {
    $order = Order::factory()->create([
        'status' => 'pending',
        'total' => 5000,
        'fbp' => 'fb.1.99.abc',
        'fbc' => 'fb.1.99.click',
    ]);

    actingAs(orderManager())
        ->put("/admin/orders/{$order->id}/status", ['status' => 'confirmed'])
        ->assertRedirect();

    $purchases = $this->capi->ofType('Purchase');
    expect($purchases)->toHaveCount(1)
        ->and($purchases[0]->eventId)->toBe('purchase.'.$order->order_no);

    // Attribution: the customer's stored first-party cookies ride along.
    $userData = $purchases[0]->toArray()['user_data'];
    expect($userData['fbp'])->toBe('fb.1.99.abc')
        ->and($userData['fbc'])->toBe('fb.1.99.click');

    // Idempotency stamp set so it never refires.
    expect($order->refresh()->marketing_purchase_sent_at)->not->toBeNull();
});

it('does not fire a Purchase for a non-confirm status change', function () {
    $order = Order::factory()->create(['status' => 'confirmed', 'total' => 5000]);

    actingAs(orderManager())
        ->put("/admin/orders/{$order->id}/status", ['status' => 'processing'])
        ->assertRedirect();

    expect($this->capi->ofType('Purchase'))->toHaveCount(0)
        ->and($order->refresh()->marketing_purchase_sent_at)->toBeNull();
});

it('is idempotent — a second confirm attempt never double-fires', function () {
    $order = Order::factory()->create(['status' => 'pending', 'total' => 5000]);
    $action = app(ConfirmOrderPurchase::class);

    expect($action->handle($order))->toBeTrue()
        ->and($action->handle($order->refresh()))->toBeFalse()
        ->and($this->capi->ofType('Purchase'))->toHaveCount(1);
});
