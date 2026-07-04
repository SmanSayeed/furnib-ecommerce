<?php

declare(strict_types=1);

use App\Models\NotificationLog;
use App\Models\Order;
use App\Services\Settings\SettingsService;

beforeEach(function () {
    cache()->flush();
    app(SettingsService::class)->set('sms', 'dlr_token', 'secret-token', true);
    cache()->flush();
});

function smsLog(string $providerId, string $status = 'sent'): NotificationLog
{
    $order = Order::factory()->create();

    return NotificationLog::query()->create([
        'order_id' => $order->id,
        'channel' => 'sms',
        'event' => 'confirmed',
        'recipient' => '+8801712345678',
        'message' => 'test',
        'provider' => 'automas',
        'provider_message_id' => $providerId,
        'status' => $status,
    ]);
}

it('marks a message delivered on a success DLR matched by provider id', function () {
    $log = smsLog('MSG-100');

    $this->postJson('/api/v1/sms/dlr/secret-token/success', ['id' => 'MSG-100'])
        ->assertOk()
        ->assertJson(['ok' => true]);

    $log->refresh();
    expect($log->status)->toBe('delivered')
        ->and($log->delivered_at)->not->toBeNull();
});

it('marks a message undelivered on a fail DLR and records the reason', function () {
    $log = smsLog('MSG-200');

    $this->getJson('/api/v1/sms/dlr/secret-token/failed?sid=MSG-200&error=Handset+off')
        ->assertOk();

    $log->refresh();
    expect($log->status)->toBe('undelivered')
        ->and($log->delivered_at)->toBeNull()
        ->and($log->error)->toContain('Handset');
});

it('rejects a DLR with a bad token', function () {
    $log = smsLog('MSG-300');

    $this->postJson('/api/v1/sms/dlr/wrong-token/success', ['id' => 'MSG-300'])
        ->assertNotFound();

    expect($log->refresh()->status)->toBe('sent'); // unchanged
});

it('ignores a DLR for an unknown message id without leaking', function () {
    $this->postJson('/api/v1/sms/dlr/secret-token/success', ['id' => 'DOES-NOT-EXIST'])
        ->assertOk()
        ->assertJson(['ok' => true]);
});

it('rejects an invalid outcome segment', function () {
    $this->postJson('/api/v1/sms/dlr/secret-token/maybe', ['id' => 'x'])
        ->assertNotFound();
});
