<?php

declare(strict_types=1);

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Support\Money;
use Database\Seeders\PermissionRoleSeeder;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->seed(PermissionRoleSeeder::class);
});

function renderInvoice(Order $order): string
{
    return view('invoices.order', [
        'order' => $order,
        'siteName' => 'Furnib',
        'logoUrl' => null,
        'company' => ['name' => 'Furnib', 'website' => null, 'address' => null, 'phone' => null, 'email' => null],
    ])->render();
}

it('shows an order-level discount line when the order is discounted', function () {
    $order = Order::factory()
        ->has(OrderItem::factory()->count(1)->state([
            'price' => Money::fromMinor(1000000), 'qty' => 1, 'line_total' => Money::fromMinor(1000000),
        ]), 'items')
        ->create([
            'subtotal' => Money::fromMinor(1000000),
            'discount' => Money::fromMinor(50000),      // ৳500 off
            'shipping_cost' => Money::fromMinor(10000),
            'total' => Money::fromMinor(960000),
        ]);

    expect(renderInvoice($order))
        ->toContain('Order Discount:')
        ->toContain('500Tk.');
});

it('omits the order-discount line when there is no order discount', function () {
    $order = Order::factory()
        ->has(OrderItem::factory()->count(1), 'items')
        ->create(['discount' => Money::fromMinor(0)]);

    expect(renderInvoice($order))->not->toContain('Order Discount:');
});

it('downloads an order invoice as a PDF for orders.view staff', function () {
    $order = Order::factory()->has(OrderItem::factory()->count(2), 'items')->create();
    $user = User::factory()->create();
    $user->assignRole('sub-admin'); // orders.view

    $response = actingAs($user)->get("/admin/orders/{$order->id}/invoice");

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('application/pdf');
    expect($response->headers->get('content-disposition'))->toContain($order->order_no);
});

it('blocks staff without orders.view from the invoice', function () {
    $order = Order::factory()->create();
    $user = User::factory()->create();
    $user->assignRole('marketer'); // no orders.view

    actingAs($user)
        ->get("/admin/orders/{$order->id}/invoice")
        ->assertForbidden();
});

it('embeds the configured logo in the invoice template', function () {
    $order = Order::factory()->has(OrderItem::factory()->count(1), 'items')->create();

    $html = view('invoices.order', [
        'order' => $order,
        'siteName' => 'Furnib',
        'logoUrl' => 'https://cdn.example.com/furnib-invoice.png',
        'company' => ['name' => 'Furnib', 'website' => 'https://furnib.com', 'address' => null, 'phone' => null, 'email' => null],
    ])->render();

    expect($html)->toContain('https://cdn.example.com/furnib-invoice.png')
        ->and($html)->toContain('<img');
});

it('omits the invoice logo img when none is configured', function () {
    $order = Order::factory()->has(OrderItem::factory()->count(1), 'items')->create();

    $html = view('invoices.order', [
        'order' => $order,
        'siteName' => 'Furnib',
        'logoUrl' => null,
        'company' => ['name' => 'Furnib', 'website' => 'https://furnib.com', 'address' => null, 'phone' => null, 'email' => null],
    ])->render();

    // No logo → the brand name is printed as text instead of an <img>.
    expect($html)->not->toContain('<img')
        ->and($html)->toContain('Furnib');
});

it('returns 404 for a missing order invoice', function () {
    $user = User::factory()->create();
    $user->assignRole('admin');

    actingAs($user)
        ->get('/admin/orders/999999/invoice')
        ->assertNotFound();
});
