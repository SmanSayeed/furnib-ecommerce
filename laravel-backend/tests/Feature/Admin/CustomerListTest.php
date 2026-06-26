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

function customerViewer(): User
{
    $user = User::factory()->create();
    $user->assignRole('sub-admin'); // orders.view

    return $user;
}

it('denies the customer list to users without orders.view', function () {
    actingAs(User::factory()->create())
        ->get('/admin/customers')
        ->assertForbidden();
});

it('shows order count and total spent (paid + partial only)', function () {
    $customer = Customer::factory()->create();
    Order::factory()->create(['customer_id' => $customer->id, 'payment_status' => 'paid', 'total' => 100]);
    Order::factory()->create(['customer_id' => $customer->id, 'payment_status' => 'partial', 'total' => 200]);
    Order::factory()->create(['customer_id' => $customer->id, 'payment_status' => 'unpaid', 'total' => 400]);

    actingAs(customerViewer())
        ->get('/admin/customers')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('customers/index')
            ->where('customers.0.orders_count', 3)
            ->where('customers.0.total_spent', '৳300.00'));
});

it('searches customers by mobile', function () {
    Customer::factory()->create(['mobile' => '+8801711000111']);
    Customer::factory()->create(['mobile' => '+8801999888777']);

    actingAs(customerViewer())
        ->get('/admin/customers?search=1711000')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('customers', 1)
            ->where('customers.0.mobile', '+8801711000111'));
});

it('limits customers to those who joined this month', function () {
    Customer::factory()->create(['created_at' => Carbon::now()]);
    Customer::factory()->create(['created_at' => Carbon::now()->subMonthsNoOverflow(2)]);

    actingAs(customerViewer())
        ->get('/admin/customers?range=this_month')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('customers', 1));
});

it('sorts customers by total spent descending', function () {
    $small = Customer::factory()->create(['name' => 'Small Spender']);
    Order::factory()->create(['customer_id' => $small->id, 'payment_status' => 'paid', 'total' => 100]);

    $big = Customer::factory()->create(['name' => 'Big Spender']);
    Order::factory()->create(['customer_id' => $big->id, 'payment_status' => 'paid', 'total' => 9000]);

    actingAs(customerViewer())
        ->get('/admin/customers?sort=total_spent&dir=desc')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('customers.0.name', 'Big Spender')
            ->where('customers.1.name', 'Small Spender'));
});
