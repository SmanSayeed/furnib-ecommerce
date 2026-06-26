<?php

declare(strict_types=1);

use App\Models\Customer;
use App\Models\Order;
use App\Models\User;
use Database\Seeders\PermissionRoleSeeder;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->seed(PermissionRoleSeeder::class);
});

function invoiceViewer(): User
{
    $user = User::factory()->create();
    $user->assignRole('sub-admin'); // orders.view

    return $user;
}

it('denies the invoice list to users without orders.view', function () {
    actingAs(User::factory()->create())
        ->get('/admin/invoices')
        ->assertForbidden();
});

it('lists invoices derived from orders', function () {
    $customer = Customer::factory()->create(['name' => 'Karim']);
    $order = Order::factory()->create(['customer_id' => $customer->id]);

    actingAs(invoiceViewer())
        ->get('/admin/invoices')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('invoices/index')
            ->has('invoices', 1)
            ->where('invoices.0.invoice_no', $order->order_no)
            ->where('invoices.0.customer', 'Karim'));
});

it('filters invoices by paid payment status', function () {
    Order::factory()->create(['payment_status' => 'paid']);
    Order::factory()->create(['payment_status' => 'unpaid']);

    actingAs(invoiceViewer())
        ->get('/admin/invoices?payment_status=paid')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('invoices', 1)
            ->where('invoices.0.payment_status', 'paid'));
});

it('filters invoices by a custom date range', function () {
    Order::factory()->create(['created_at' => Carbon::parse('2026-03-15 10:00')]);
    Order::factory()->create(['created_at' => Carbon::parse('2026-06-15 10:00')]);

    actingAs(invoiceViewer())
        ->get('/admin/invoices?range=custom&from=2026-03-01&to=2026-03-31')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('invoices', 1));
});

it('downloads a row invoice PDF via the order invoice route', function () {
    $order = Order::factory()->create();

    actingAs(invoiceViewer())
        ->get("/admin/orders/{$order->id}/invoice")
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});
