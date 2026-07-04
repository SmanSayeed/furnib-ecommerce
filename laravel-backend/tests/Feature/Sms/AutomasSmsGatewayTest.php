<?php

declare(strict_types=1);

use App\Services\Settings\SettingsService;
use App\Support\Sms\AutomasSmsGateway;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    cache()->flush();
    $settings = app(SettingsService::class);
    $settings->set('sms', 'api_key', 'test-key', true);
    $settings->set('sms', 'sender_id', '8809617635160', false);
    cache()->flush();
    $this->gateway = app(AutomasSmsGateway::class);
});

function fakeAutomas(int $status = 0): void
{
    Http::fake([
        'api.automas.com.bd/*' => Http::response([
            'response' => [['status' => $status, 'id' => 296334, 'msisdn' => '8801712345678']],
        ], 200),
    ]);
}

it('sends Bangla as Unicode (smsformat=8) and strips the + from the number', function () {
    fakeAutomas();

    expect($this->gateway->send('+8801712345678', 'আপনার অর্ডার নিশ্চিত হয়েছে'))->toBeTrue();

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'smsformat=8')
            && str_contains($request->url(), 'msisdn=8801712345678');
    });
});

it('sends ASCII (e.g. OTP) without the Unicode flag', function () {
    fakeAutomas();

    expect($this->gateway->send('+8801712345678', 'Your code is 123456'))->toBeTrue();

    Http::assertSent(fn ($request) => ! str_contains($request->url(), 'smsformat=8'));
});

it('returns false when the gateway reports a non-zero status', function () {
    fakeAutomas(status: 1000); // insufficient balance

    expect($this->gateway->send('+8801712345678', 'test'))->toBeFalse();
});

it('returns false (no throw) when the credentials are missing', function () {
    app(SettingsService::class)->set('sms', 'api_key', '', true);
    cache()->flush();
    Http::fake();

    expect(app(AutomasSmsGateway::class)->send('+8801712345678', 'x'))->toBeFalse();
    Http::assertNothingSent();
});
