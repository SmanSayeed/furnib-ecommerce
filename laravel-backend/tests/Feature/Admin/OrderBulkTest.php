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

it('downloads courier payslips for the selected orders as one PDF', function () {
    $orders = Order::factory()->count(4)->has(OrderItem::factory()->count(1), 'items')->create();
    $ids = $orders->pluck('id')->implode(',');

    $response = actingAs(orderBulkViewer())->get("/admin/orders/bulk/payslips?ids={$ids}");

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('application/pdf')
        ->and($response->headers->get('content-disposition'))->toContain('payslips-');
});

it('returns 404 when no orders are selected for a download', function () {
    actingAs(orderBulkViewer())
        ->get('/admin/orders/bulk/invoices?ids=')
        ->assertNotFound();
});

it('renders three payslips per A4 page from the batch view', function () {
    $orders = Order::factory()->count(4)->has(OrderItem::factory()->count(1), 'items')->create();

    $html = view('invoices.payslips', [
        'orders' => $orders->load('items', 'customer'),
        'siteName' => 'Furnib',
        'logoUrl' => null,
    ])->render();

    // 4 orders → 2 A4 pages (3 + 1). Each order_no appears once.
    expect(substr_count($html, 'class="page"'))->toBe(2);
    foreach ($orders as $order) {
        expect($html)->toContain($order->order_no);
    }
});
