<?php

declare(strict_types=1);

use App\Models\Customer;
use App\Models\OtpCode;
use App\Services\Auth\OtpService;
use App\Support\Sms\FakeSmsGateway;
use App\Support\Sms\SmsGateway;
use Illuminate\Support\Facades\Hash;

// Route throttle uses the cache store; flush between tests so the per-IP quota
// doesn't bleed across the run.
beforeEach(function () {
    cache()->flush();
    $this->fakeSms = new FakeSmsGateway;
    $this->app->instance(SmsGateway::class, $this->fakeSms);
});

it('stores the OTP as a hash, never plaintext', function () {
    $code = app(OtpService::class)->issue('01712345678');

    $row = OtpCode::query()->where('mobile', '+8801712345678')->firstOrFail();

    expect($row->code)->not->toBe($code)
        ->and(Hash::check($code, $row->code))->toBeTrue();
});

it('rejects an expired code', function () {
    OtpCode::factory()->expired()->create(['mobile' => '+8801712345678']);

    expect(app(OtpService::class)->verify('01712345678', '123456'))->toBeFalse();
});

it('locks out after too many wrong attempts even if the right code follows', function () {
    $otp = app(OtpService::class);
    $code = $otp->issue('01712345678');

    for ($i = 0; $i < OtpService::MAX_ATTEMPTS; $i++) {
        expect($otp->verify('01712345678', '000000'))->toBeFalse();
    }

    expect($otp->verify('01712345678', $code))->toBeFalse();
});

it('request endpoint issues a code and sends it by SMS', function () {
    $this->postJson('/api/v1/auth/otp/request', ['mobile' => '01712345678'])
        ->assertOk()
        ->assertJson(['sent' => true]);

    $messages = $this->fakeSms->messagesTo('+8801712345678');
    expect($messages)->toHaveCount(1)
        ->and($messages[0]['message'])->toMatch('/\b\d{6}\b/');
});

it('never returns the code in the response', function () {
    $response = $this->postJson('/api/v1/auth/otp/request', ['mobile' => '01712345678']);

    expect($response->json())->toBe(['sent' => true]);
});

it('verifies a correct code, auto-registers the customer and issues a working token', function () {
    $this->postJson('/api/v1/auth/otp/request', ['mobile' => '01712345678'])->assertOk();
    $code = extractCode($this->fakeSms);

    expect(Customer::query()->where('mobile', '+8801712345678')->exists())->toBeFalse();

    $verify = $this->postJson('/api/v1/auth/otp/verify', [
        'mobile' => '01712345678',
        'code' => $code,
        'name' => 'Karim',
    ])->assertOk();

    $token = $verify->json('token');
    expect($token)->toBeString()
        ->and($verify->json('customer.mobile'))->toBe('+8801712345678');

    // Customer was auto-registered with the supplied name.
    $customer = Customer::query()->where('mobile', '+8801712345678')->firstOrFail();
    expect($customer->name)->toBe('Karim');

    // The issued token authenticates the customer endpoint.
    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/v1/auth/me')
        ->assertOk()
        ->assertJsonPath('mobile', '+8801712345678');
});

it('rejects a wrong or expired code with 422', function () {
    $this->postJson('/api/v1/auth/otp/request', ['mobile' => '01712345678'])->assertOk();

    $this->postJson('/api/v1/auth/otp/verify', [
        'mobile' => '01712345678',
        'code' => '000000',
    ])->assertStatus(422)->assertJsonPath('error.code', 'validation_error');
});

it('rate-limits repeated requests to the same mobile (429)', function () {
    $this->postJson('/api/v1/auth/otp/request', ['mobile' => '01712345678'])->assertOk();

    $this->postJson('/api/v1/auth/otp/request', ['mobile' => '01712345678'])
        ->assertStatus(429)
        ->assertJsonPath('error.code', 'too_many_requests');
});

it('blocks the customer endpoint without a token (401)', function () {
    $this->getJson('/api/v1/auth/me')->assertStatus(401);
});

it('blocks a token that lacks the customer ability (403)', function () {
    $customer = Customer::factory()->create();
    $token = $customer->createToken('wrong-scope', ['other'])->plainTextToken;

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/v1/auth/me')
        ->assertStatus(403);
});

function extractCode(FakeSmsGateway $sms): string
{
    preg_match('/\b(\d{6})\b/', $sms->sent[0]['message'], $m);

    return $m[1];
}
