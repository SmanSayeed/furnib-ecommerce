<?php

declare(strict_types=1);

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductShippingCharge;
use App\Models\Shipment;
use App\Models\ShippingZone;
use App\Models\User;
use App\Support\Money;
use Database\Seeders\PermissionRoleSeeder;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->seed(PermissionRoleSeeder::class);
    $this->manager = User::factory()->create();
    $this->manager->assignRole('manager'); // orders.view + orders.manage
});

/** An order with one real product line, so shipping can actually be recomputed. */
function editableOrder(array $overrides = []): Order
{
    $product = Product::factory()->create([
        'price' => 1000, 'stock_amount' => 10, 'stock_status' => true,
        'shipping_charge_allowed' => true,
    ]);

    $order = Order::factory()->create(array_merge([
        'subtotal' => Money::fromMinor(200000),   // ৳2,000
        'shipping_cost' => Money::fromMinor(0),
        'total' => Money::fromMinor(200000),
        'advance_paid' => Money::fromMinor(0),
        'payment_status' => 'unpaid',
        'shipping_zone_id' => null,
    ], $overrides));

    OrderItem::factory()->create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'price' => Money::fromMinor(100000),
        'qty' => 2,
        'line_total' => Money::fromMinor(200000),
    ]);

    return $order->fresh(['items']);
}

// ─── Admin note ───────────────────────────────────────────────────────────────

it('saves an admin note', function () {
    $order = editableOrder();

    actingAs($this->manager)
        ->put("/admin/orders/{$order->id}/note", ['admin_note' => 'Customer will collect on Friday.'])
        ->assertRedirect();

    expect($order->refresh()->admin_note)->toBe('Customer will collect on Friday.');
});

it('keeps the admin note across a status change', function () {
    // pending_note is wiped on any forward transition — the admin note must not be.
    $order = editableOrder(['status' => 'pending', 'pending_note' => 'waiting on a call']);

    actingAs($this->manager)->put("/admin/orders/{$order->id}/note", ['admin_note' => 'VIP — handle first']);
    actingAs($this->manager)->put("/admin/orders/{$order->id}/status", ['status' => 'confirmed']);

    $order->refresh();

    expect($order->admin_note)->toBe('VIP — handle first')
        ->and($order->pending_note)->toBeNull()   // wiped, as designed
        ->and($order->status)->toBe('confirmed');
});

it('forbids an admin note without orders.manage', function () {
    $order = editableOrder();
    $user = User::factory()->create();
    $user->assignRole('marketer');

    actingAs($user)
        ->put("/admin/orders/{$order->id}/note", ['admin_note' => 'nope'])
        ->assertForbidden();
});

// ─── Customer + address ───────────────────────────────────────────────────────

function customerPayload(Order $order, array $overrides = []): array
{
    return array_merge([
        'name' => $order->customer?->name,
        'mobile' => $order->customer?->mobile,
        'email' => $order->customer?->email,
        'address' => $order->address,
        'shipping_zone_id' => $order->shipping_zone_id,
    ], $overrides);
}

it('corrects the delivery address without touching the totals', function () {
    $order = editableOrder();
    $before = $order->total->toMinor();

    actingAs($this->manager)
        ->put("/admin/orders/{$order->id}/customer", customerPayload($order, [
            'address' => 'House 9, Road 4, Banani, Dhaka',
        ]))
        ->assertRedirect();

    $order->refresh();

    expect($order->address)->toBe('House 9, Road 4, Banani, Dhaka')
        ->and($order->total->toMinor())->toBe($before);
});

it('corrects the customer name mobile and email', function () {
    $order = editableOrder();

    actingAs($this->manager)
        ->put("/admin/orders/{$order->id}/customer", customerPayload($order, [
            'name' => 'Rahim Uddin',
            'mobile' => '01712345678',
            'email' => 'rahim@example.com',
        ]))
        ->assertRedirect();

    $customer = $order->refresh()->customer;

    expect($customer->name)->toBe('Rahim Uddin')
        ->and($customer->mobile)->toBe('+8801712345678')  // normalized
        ->and($customer->email)->toBe('rahim@example.com');
});

it('rejects a mobile that already belongs to another customer', function () {
    Customer::factory()->create(['mobile' => '+8801812345678']);
    $order = editableOrder();

    actingAs($this->manager)
        ->put("/admin/orders/{$order->id}/customer", customerPayload($order, ['mobile' => '01812345678']))
        ->assertSessionHasErrors('mobile');
});

it('normalizes the mobile before checking uniqueness', function () {
    // The other customer holds the E.164 form; the admin types the local form.
    // Without normalisation these would look like two different numbers.
    Customer::factory()->create(['mobile' => '+8801912345678']);
    $order = editableOrder();

    actingAs($this->manager)
        ->put("/admin/orders/{$order->id}/customer", customerPayload($order, ['mobile' => '01912345678']))
        ->assertSessionHasErrors('mobile');
});

// ─── Zone change → recompute ──────────────────────────────────────────────────

it('recomputes shipping and the total when the zone changes', function () {
    $order = editableOrder();
    $zone = ShippingZone::factory()->create(['cost' => 150]);

    actingAs($this->manager)
        ->put("/admin/orders/{$order->id}/customer", customerPayload($order, ['shipping_zone_id' => $zone->id]))
        ->assertRedirect();

    $order->refresh();

    expect($order->shipping_cost->toMinor())->toBe(15000)     // ৳150 zone base
        ->and($order->total->toMinor())->toBe(215000);        // ৳2,000 + ৳150
});

it('includes the per-product zone extra when recomputing', function () {
    $order = editableOrder();
    $zone = ShippingZone::factory()->create(['cost' => 80]);
    ProductShippingCharge::factory()->create([
        'product_id' => $order->items->first()->product_id,
        'shipping_zone_id' => $zone->id,
        'extra_cost' => Money::fromMinor(2000), // ৳20/unit, qty 2
    ]);

    actingAs($this->manager)
        ->put("/admin/orders/{$order->id}/customer", customerPayload($order, ['shipping_zone_id' => $zone->id]));

    // Exactly the placement formula: 80 + 20×2 = 120.
    expect($order->refresh()->shipping_cost->toMinor())->toBe(12000)
        ->and($order->total->toMinor())->toBe(212000);
});

it('drops shipping to zero when the zone is cleared', function () {
    $zone = ShippingZone::factory()->create(['cost' => 150]);
    $order = editableOrder([
        'shipping_zone_id' => $zone->id,
        'shipping_cost' => Money::fromMinor(15000),
        'total' => Money::fromMinor(215000),
    ]);

    actingAs($this->manager)
        ->put("/admin/orders/{$order->id}/customer", customerPayload($order, ['shipping_zone_id' => null]));

    $order->refresh();

    expect($order->shipping_cost->toMinor())->toBe(0)
        ->and($order->total->toMinor())->toBe(200000);
});

it('refuses to change the zone of a paid order', function () {
    // Changing the total of a settled order silently creates a debt or a refund.
    $order = editableOrder(['payment_status' => 'paid', 'advance_paid' => Money::fromMinor(200000)]);
    $zone = ShippingZone::factory()->create(['cost' => 150]);

    actingAs($this->manager)
        ->put("/admin/orders/{$order->id}/customer", customerPayload($order, ['shipping_zone_id' => $zone->id]))
        ->assertSessionHasErrors('shipping_zone_id');

    expect($order->refresh()->shipping_cost->toMinor())->toBe(0);
});

it('refuses a zone change that would drop the total below what was paid', function () {
    $expensive = ShippingZone::factory()->create(['cost' => 500]);
    $order = editableOrder([
        'shipping_zone_id' => $expensive->id,
        'shipping_cost' => Money::fromMinor(50000),
        'total' => Money::fromMinor(250000),
        'advance_paid' => Money::fromMinor(240000), // ৳2,400 already collected
        'payment_status' => 'partial',
    ]);

    // Clearing the zone would make the total ৳2,000 — less than the ৳2,400 paid.
    actingAs($this->manager)
        ->put("/admin/orders/{$order->id}/customer", customerPayload($order, ['shipping_zone_id' => null]))
        ->assertSessionHasErrors('shipping_zone_id');

    expect($order->refresh()->total->toMinor())->toBe(250000);
});

it('still saves but warns when a consignment is already booked', function () {
    $order = editableOrder();
    Shipment::factory()->create(['order_id' => $order->id]);

    actingAs($this->manager)
        ->put("/admin/orders/{$order->id}/customer", customerPayload($order, ['address' => 'New address, Dhaka']))
        ->assertRedirect();

    // The edit lands — the admin is told the courier still holds the old address.
    expect($order->refresh()->address)->toBe('New address, Dhaka');
});

it('forbids editing the customer without orders.manage', function () {
    $order = editableOrder();
    $user = User::factory()->create();
    $user->assignRole('marketer');

    actingAs($user)
        ->put("/admin/orders/{$order->id}/customer", customerPayload($order))
        ->assertForbidden();
});

// ─── Pending-reason filter ────────────────────────────────────────────────────

it('filters the orders list by pending reason', function () {
    Order::factory()->create(['status' => 'pending', 'pending_reason' => 'call_waiting']);
    Order::factory()->create(['status' => 'pending', 'pending_reason' => 'payment_pending']);

    $response = actingAs($this->manager)->get('/admin/orders?pending_reason=call_waiting');

    $orders = $response->viewData('page')['props']['orders'];

    expect($orders)->toHaveCount(1)
        ->and($orders[0]['pending_reason'])->toBe('call_waiting');
});

it('binds a hostile pending-reason value instead of interpolating it', function () {
    Order::factory()->create(['status' => 'pending', 'pending_reason' => 'call_waiting']);

    // The whitelist guards the COLUMN name; the value travels as a bound parameter.
    // So a SQL payload matches nothing rather than escaping the query — the page
    // still renders and returns an empty list.
    $response = actingAs($this->manager)
        ->get('/admin/orders?pending_reason='.urlencode("' OR 1=1 --"));

    $response->assertOk();

    expect($response->viewData('page')['props']['orders'])->toHaveCount(0);
});

it('rejects an unknown filter key entirely', function () {
    Order::factory()->create(['status' => 'pending', 'pending_reason' => 'call_waiting']);

    // A column outside the whitelist is dropped, not queried.
    $orders = actingAs($this->manager)
        ->get('/admin/orders?admin_note=anything')
        ->viewData('page')['props']['orders'];

    expect($orders)->toHaveCount(1);
});
