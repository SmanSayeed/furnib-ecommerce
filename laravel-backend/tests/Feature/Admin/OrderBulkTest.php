<?php

declare(strict_types=1);

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Database\Seeders\PermissionRoleSeeder;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->seed(PermissionRoleSeeder::class);
});

function orderBulkManager(): User
{
    $user = User::factory()->create();
    $user->assignRole('admin'); // orders.manage

    return $user;
}

function orderBulkViewer(): User
{
    $user = User::factory()->create();
    $user->assignRole('sub-admin'); // orders.view only

    return $user;
}

it('bulk-confirms selected pending orders', function () {
    $orders = Order::factory()->count(3)->create(['status' => 'pending']);

    actingAs(orderBulkManager())
        ->post('/admin/orders/bulk/status', [
            'status' => 'confirmed',
            'ids' => $orders->pluck('id')->all(),
        ])->assertRedirect();

    expect(Order::query()->where('status', 'confirmed')->count())->toBe(3);
});

it('skips orders whose transition is illegal instead of forcing them', function () {
    $pending = Order::factory()->create(['status' => 'pending']);
    $delivered = Order::factory()->status('delivered')->create();

    actingAs(orderBulkManager())
        ->post('/admin/orders/bulk/status', [
            'status' => 'confirmed',
            'ids' => [$pending->id, $delivered->id],
        ])->assertRedirect();

    expect($pending->refresh()->status)->toBe('confirmed')
        ->and($delivered->refresh()->status)->toBe('delivered');
});

it('bulk-updates every order matching the current filters', function () {
    Order::factory()->count(2)->create(['status' => 'pending']);
    Order::factory()->status('delivered')->create();

    actingAs(orderBulkManager())
        ->post('/admin/orders/bulk/status', [
            'status' => 'confirmed',        // target
            'all_matching' => true,
            'filters' => ['status' => 'pending'], // list filter, kept separate
        ])->assertRedirect();

    // Only the two pending orders (matching the pending filter) were confirmed;
    // the delivered order is out of scope.
    expect(Order::query()->where('status', 'confirmed')->count())->toBe(2);
});

it('blocks orders.view-only staff from bulk status changes', function () {
    $order = Order::factory()->create(['status' => 'pending']);

    actingAs(orderBulkViewer())
        ->post('/admin/orders/bulk/status', [
            'status' => 'confirmed',
            'ids' => [$order->id],
        ])->assertForbidden();
});

it('downloads chained invoices for the selected orders as one PDF', function () {
    $orders = Order::factory()->count(2)->has(OrderItem::factory()->count(2), 'items')->create();
    $ids = $orders->pluck('id')->implode(',');

    $response = actingAs(orderBulkViewer())->get("/admin/orders/bulk/invoices?ids={$ids}");

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('application/pdf')
        ->and($response->headers->get('content-disposition'))->toContain('invoices-');
});

it('downloads courier shipping labels for the selected orders as one PDF', function () {
    $orders = Order::factory()->count(4)->has(OrderItem::factory()->count(1), 'items')->create();
    $ids = $orders->pluck('id')->implode(',');

    $response = actingAs(orderBulkViewer())->get("/admin/orders/bulk/shipping-labels?ids={$ids}");

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('application/pdf')
        ->and($response->headers->get('content-disposition'))->toContain('shipping-labels-');
});

it('returns 404 when no orders are selected for a download', function () {
    actingAs(orderBulkViewer())
        ->get('/admin/orders/bulk/invoices?ids=')
        ->assertNotFound();
});

it('renders one shipping label per page from the batch view', function () {
    $orders = Order::factory()->count(3)->has(OrderItem::factory()->count(1), 'items')->create();

    $html = view('shipping-labels.bulk', [
        'orders' => $orders->load('items', 'customer', 'shippingZone', 'shipment'),
        'siteName' => 'Furnib',
        'logoUrl' => null,
        'company' => ['name' => 'Furnib', 'website' => 'https://furnib.com', 'address' => 'Dhaka', 'phone' => '01700000000', 'email' => 'hi@furnib.com'],
    ])->render();

    // One .label-page per order, and each order number is printed on its label.
    expect(substr_count($html, 'class="label-page"'))->toBe(3);
    foreach ($orders as $order) {
        expect($html)->toContain($order->order_no);
    }
});

it('downloads a single order shipping label', function () {
    $order = Order::factory()->has(OrderItem::factory()->count(1), 'items')->create();

    $response = actingAs(orderBulkViewer())->get("/admin/orders/{$order->id}/label");

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('application/pdf')
        ->and($response->headers->get('content-disposition'))->toContain($order->order_no);
});
