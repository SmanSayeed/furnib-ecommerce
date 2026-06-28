<?php

declare(strict_types=1);

use App\Models\NewsletterSubscriber;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Shipment;
use App\Models\User;
use Database\Seeders\PermissionRoleSeeder;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->seed(PermissionRoleSeeder::class);
});

function adminListUser(): User
{
    $user = User::factory()->create();
    $user->assignRole('admin'); // has payments.view, orders.view, settings.manage, audit.view

    return $user;
}

function noAccessUser(): User
{
    $user = User::factory()->create();
    $user->assignRole('marketer'); // only marketing.manage

    return $user;
}

it('shows the transactions list to payments.view staff', function () {
    Payment::factory()->for(Order::factory())->create();

    actingAs(adminListUser())->get('/admin/payments')->assertOk();
});

it('blocks transactions for staff without payments.view', function () {
    actingAs(noAccessUser())->get('/admin/payments')->assertForbidden();
});

it('shows the consignments list to orders.view staff', function () {
    Shipment::factory()->for(Order::factory())->create();

    actingAs(adminListUser())->get('/admin/shipping/consignments')->assertOk();
});

it('blocks consignments for staff without orders.view', function () {
    actingAs(noAccessUser())->get('/admin/shipping/consignments')->assertForbidden();
});

it('shows the subscribers list to settings.manage staff', function () {
    NewsletterSubscriber::factory()->count(2)->create();

    actingAs(adminListUser())->get('/admin/subscribers')->assertOk();
});

it('exports subscribers as csv', function () {
    NewsletterSubscriber::factory()->create(['email' => 'sub@example.com']);

    $res = actingAs(adminListUser())->get('/admin/subscribers/export');

    $res->assertOk();
    expect($res->headers->get('content-type'))->toContain('text/csv');
});

it('blocks subscribers for staff without settings.manage', function () {
    actingAs(noAccessUser())->get('/admin/subscribers')->assertForbidden();
    actingAs(noAccessUser())->get('/admin/subscribers/export')->assertForbidden();
});

it('shows the audit log to audit.view staff', function () {
    actingAs(adminListUser())->get('/admin/audit-logs')->assertOk();
});

it('blocks the audit log for staff without audit.view', function () {
    actingAs(noAccessUser())->get('/admin/audit-logs')->assertForbidden();
});
