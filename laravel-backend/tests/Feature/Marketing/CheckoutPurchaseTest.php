<?php

declare(strict_types=1);

use App\Actions\Marketing\ConfirmOrderPurchase;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\ShippingZone;
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

it('fires exactly one server-side Purchase when the order is placed', function () {
    $category = Category::factory()->create(['title' => 'Sofas']);
    $product = Product::factory()->create([
        'category_id' => $category->id,
        'product_status' => 'published',
        'price' => 1000, 'stock_amount' => 10, 'stock_status' => true,
        'sku' => 'SKU-1',
    ]);
    $zone = ShippingZone::factory()->create(['name' => 'Dhaka', 'cost' => 80, 'status' => true]);

    // The afterResponse dispatch runs on kernel terminate, which the test HTTP
    // harness invokes before returning — so the fake has the event by now.
    $this->withHeaders(['X-Fbp' => 'fb.1.99.abc', 'X-Fbc' => 'fb.1.99.click'])
        ->postJson('/api/v1/orders', [
            'items' => [['product_id' => $product->id, 'qty' => 2]],
            'customer' => ['name' => 'Karim Mia', 'mobile' => '01712345678'],
            'shipping_zone_id' => $zone->id,
            'address' => 'House 1, Road 2, Dhaka',
        ])->assertCreated();

    $order = Order::query()->latest('id')->firstOrFail();

    $purchases = $this->capi->ofType('Purchase');
    expect($purchases)->toHaveCount(1)
        ->and($purchases[0]->eventId)->toBe('purchase.'.$order->order_no);

    // Attribution: the customer's first-party cookies ride along.
    $userData = $purchases[0]->toArray()['user_data'];
    expect($userData['fbp'])->toBe('fb.1.99.abc')
        ->and($userData['fbc'])->toBe('fb.1.99.click');

    // Idempotency stamp set at placement so it never refires later.
    expect($order->refresh()->marketing_purchase_sent_at)->not->toBeNull();
});

it('does NOT fire a Purchase on an admin status change — the conversion is at placement', function () {
    // A factory order never went through checkout, so it carries no stamp.
    $order = Order::factory()->create(['status' => 'pending', 'total' => 5000]);

    actingAs(orderManager())
        ->put("/admin/orders/{$order->id}/status", ['status' => 'confirmed'])
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
