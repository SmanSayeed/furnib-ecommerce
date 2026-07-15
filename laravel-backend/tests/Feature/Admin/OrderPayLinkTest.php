<?php

declare(strict_types=1);

use App\Models\Customer;
use App\Models\NotificationLog;
use App\Models\Order;
use App\Models\User;
use App\Services\Settings\SettingsService;
use App\Support\Orders\PayLink;
use App\Support\Sms\FakeSmsGateway;
use App\Support\Sms\SmsGateway;
use Database\Seeders\PermissionRoleSeeder;

use function Pest\Laravel\actingAs;

/**
 * The admin can see, copy and re-send the customer's self-service pay link. The
 * resend re-renders from the live order row and is rate-limited so it can't be
 * turned into an SMS-bill DoS. The channel's own idempotency guard would swallow
 * a repeat, so the endpoint clears the prior "placed" logs first.
 */
beforeEach(function () {
    cache()->flush();
    $this->sms = new FakeSmsGateway;
    $this->app->instance(SmsGateway::class, $this->sms);

    $this->settings = app(SettingsService::class);
    $this->settings->set('sms', 'enabled', true); // `placed` is on by default
    cache()->flush();

    $this->seed(PermissionRoleSeeder::class);
    $this->manager = User::factory()->create();
    $this->manager->assignRole('manager'); // orders.manage
});

function payableOrder(): Order
{
    $customer = Customer::factory()->create(['mobile' => '+8801712345678']);

    return Order::factory()->create(['customer_id' => $customer->id, 'status' => 'pending']);
}

it('exposes the pay link on the order detail payload', function () {
    $order = payableOrder();

    $props = actingAs($this->manager)
        ->get("/admin/orders/{$order->id}")
        ->viewData('page')['props'];

    expect($props['order']['pay_url'])->toBe(PayLink::for($order));
});

it('resends the pay-link SMS', function () {
    $order = payableOrder();

    actingAs($this->manager)
        ->post("/admin/orders/{$order->id}/resend-pay-link")
        ->assertRedirect();

    expect($this->sms->sent)->toHaveCount(1)
        ->and($this->sms->sent[0]['mobile'])->toBe('+8801712345678')
        ->and($this->sms->sent[0]['message'])->toContain($order->order_no);
});

it('resends even after the placement SMS already went out', function () {
    $order = payableOrder();

    // Simulate the placement SMS already logged as sent — the idempotency guard
    // would normally swallow a repeat.
    NotificationLog::query()->create([
        'order_id' => $order->id, 'event' => 'placed', 'channel' => 'sms',
        'recipient' => '+8801712345678', 'message' => 'old', 'status' => 'sent',
    ]);

    actingAs($this->manager)->post("/admin/orders/{$order->id}/resend-pay-link");

    expect($this->sms->sent)->toHaveCount(1);
});

it('rate-limits resends to 3 per hour per order', function () {
    $order = payableOrder();

    for ($i = 0; $i < 3; $i++) {
        actingAs($this->manager)->post("/admin/orders/{$order->id}/resend-pay-link")->assertRedirect();
    }

    // The 4th within the hour is rejected.
    actingAs($this->manager)
        ->post("/admin/orders/{$order->id}/resend-pay-link")
        ->assertSessionHasErrors('pay_link');

    expect($this->sms->sent)->toHaveCount(3);
});

it('forbids resending without orders.manage', function () {
    $order = payableOrder();
    $user = User::factory()->create();
    $user->assignRole('marketer');

    actingAs($user)
        ->post("/admin/orders/{$order->id}/resend-pay-link")
        ->assertForbidden();
});
