<?php

declare(strict_types=1);

use App\Models\Order;
use App\Models\User;
use Database\Seeders\PermissionRoleSeeder;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->seed(PermissionRoleSeeder::class);
});

function dashboardUser(): User
{
    $user = User::factory()->create();
    $user->assignRole('admin');

    return $user;
}

it('counts only today\'s orders in the today window', function () {
    Order::factory()->create(['created_at' => Carbon::now()]);
    Order::factory()->create(['created_at' => Carbon::now()->subDays(10)]);

    actingAs(dashboardUser())
        ->get('/dashboard?range=today')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->where('orderStats.orders', 1));
});

it('counts revenue only from paid orders', function () {
    Order::factory()->create(['payment_status' => 'paid', 'total' => 100]);
    Order::factory()->create(['payment_status' => 'unpaid', 'total' => 200]);

    actingAs(dashboardUser())
        ->get('/dashboard?range=this_month')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('orderStats.revenue', '৳100'));
});

it('computes average order value as revenue over paid count', function () {
    Order::factory()->create(['payment_status' => 'paid', 'total' => 100]);
    Order::factory()->create(['payment_status' => 'paid', 'total' => 300]);

    actingAs(dashboardUser())
        ->get('/dashboard?range=this_month')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('orderStats.revenue', '৳400')
            ->where('orderStats.aov', '৳200'));
});

it('returns a daily series spanning the last 7 days', function () {
    Order::factory()->create(['created_at' => Carbon::now()]);

    actingAs(dashboardUser())
        ->get('/dashboard?range=last_7')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('series', 7));
});

it('keeps catalog stats all-time regardless of the window', function () {
    actingAs(dashboardUser())
        ->get('/dashboard?range=today')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('stats'));
});
