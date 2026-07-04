<?php

declare(strict_types=1);

use App\Enums\OrderNotificationEvent;
use App\Jobs\SendOrderNotification;
use App\Models\Customer;
use App\Models\NotificationLog;
use App\Models\Order;
use App\Services\Notifications\OrderNotificationService;
use App\Services\Settings\SettingsService;
use App\Support\Sms\FakeSmsGateway;
use App\Support\Sms\SmsGateway;
use Illuminate\Support\Facades\Bus;

beforeEach(function () {
    cache()->flush();
    $this->sms = new FakeSmsGateway;
    $this->app->instance(SmsGateway::class, $this->sms);

    $this->settings = app(SettingsService::class);
    $this->settings->set('sms', 'enabled', true);
    cache()->flush();
});

function smsOrder(array $overrides = []): Order
{
    $customer = Customer::factory()->create(['name' => 'Karim', 'mobile' => '+8801712345678']);

    return Order::factory()->create(array_merge([
        'customer_id' => $customer->id,
        'status' => 'pending',
    ], $overrides));
}

function notify(Order $order, OrderNotificationEvent $event): void
{
    app(OrderNotificationService::class)->notify($order, $event);
}

it('sends a Bangla confirmation SMS and logs it', function () {
    $order = smsOrder();

    notify($order, OrderNotificationEvent::Confirmed);

    expect($this->sms->sent)->toHaveCount(1)
        ->and($this->sms->sent[0]['mobile'])->toBe('+8801712345678')
        ->and($this->sms->sent[0]['message'])->toContain($order->order_no)
        ->and($this->sms->sent[0]['message'])->toContain('নিশ্চিত');

    $log = NotificationLog::query()->where('order_id', $order->id)->where('channel', 'sms')->firstOrFail();
    expect($log->status)->toBe('sent')
        ->and($log->event)->toBe('confirmed')
        ->and($log->recipient)->toBe('+8801712345678')
        // Provider id captured (via ProvidesMessageId) so a later DLR can match.
        ->and($log->provider_message_id)->toBe('FAKE-SMS-1');
});

it('includes the tracking code in the shipped SMS', function () {
    $order = smsOrder();
    $order->shipment()->create([
        'recipient_name' => 'Karim', 'recipient_phone' => '+8801712345678',
        'recipient_address' => 'Dhaka', 'tracking_code' => 'TRK00099', 'status' => 'in_review',
    ]);

    notify($order, OrderNotificationEvent::Shipped);

    expect($this->sms->sent[0]['message'])->toContain('TRK00099');
});

it('does not send when the master switch is off', function () {
    $this->settings->set('sms', 'enabled', false);
    cache()->flush();

    notify(smsOrder(), OrderNotificationEvent::Confirmed);

    expect($this->sms->sent)->toHaveCount(0)
        ->and(NotificationLog::query()->count())->toBe(0);
});

it('does not send when that event toggle is off', function () {
    $this->settings->set('sms', OrderNotificationEvent::Confirmed->toggleKey(), false);
    cache()->flush();

    notify(smsOrder(), OrderNotificationEvent::Confirmed);

    expect($this->sms->sent)->toHaveCount(0)
        ->and(NotificationLog::query()->count())->toBe(0);
});

it('is idempotent — the same order+event sends once', function () {
    $order = smsOrder();

    notify($order, OrderNotificationEvent::Confirmed);
    notify($order, OrderNotificationEvent::Confirmed);

    expect($this->sms->sent)->toHaveCount(1)
        ->and(NotificationLog::query()->where('order_id', $order->id)->where('event', 'confirmed')->count())->toBe(1);
});

it('records a failed send without throwing, and can retry later', function () {
    $order = smsOrder();
    $this->sms->failNext();

    notify($order, OrderNotificationEvent::Confirmed);

    $log = NotificationLog::query()->where('order_id', $order->id)->firstOrFail();
    expect($log->status)->toBe('failed');

    // A later run (gateway healthy) upgrades the same row to sent.
    $this->sms->shouldFail = false;
    notify($order, OrderNotificationEvent::Confirmed);

    expect(NotificationLog::query()->where('order_id', $order->id)->count())->toBe(1)
        ->and(NotificationLog::query()->where('order_id', $order->id)->first()->status)->toBe('sent');
});

it('uses an editable template override when set', function () {
    $this->settings->set('sms', OrderNotificationEvent::Confirmed->templateKey(), 'অর্ডার {order_no} ওকে - {name}');
    cache()->flush();

    $order = smsOrder();
    notify($order, OrderNotificationEvent::Confirmed);

    expect($this->sms->sent[0]['message'])->toBe("অর্ডার {$order->order_no} ওকে - Karim");
});

it('dispatches the notification job when an order status changes', function () {
    Bus::fake([SendOrderNotification::class]);
    $order = smsOrder(['status' => 'pending']);

    $order->update(['status' => 'confirmed']);

    Bus::assertDispatched(
        SendOrderNotification::class,
        fn ($job) => $job->orderId === $order->id && $job->event === 'confirmed',
    );
});

it('does not dispatch on a non-customer-facing status change', function () {
    Bus::fake([SendOrderNotification::class]);
    $order = smsOrder(['status' => 'confirmed']);

    $order->update(['status' => 'processing']); // no OrderNotificationEvent maps to processing

    Bus::assertNotDispatched(SendOrderNotification::class);
});
