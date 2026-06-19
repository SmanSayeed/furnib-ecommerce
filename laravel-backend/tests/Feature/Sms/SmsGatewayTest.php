<?php

declare(strict_types=1);

use App\Support\Sms\FakeSmsGateway;
use App\Support\Sms\LogSmsGateway;
use App\Support\Sms\SmsGateway;
use Illuminate\Support\Facades\Log;

it('resolves the gateway interface to the log driver by default', function () {
    expect(app(SmsGateway::class))->toBeInstanceOf(LogSmsGateway::class);
});

it('log driver writes the message and reports success', function () {
    Log::spy();

    $sent = app(LogSmsGateway::class)->send('+8801712345678', 'Your code is 123456');

    expect($sent)->toBeTrue();
    Log::shouldHaveReceived('info')->once();
});

it('fake gateway records sent messages and can be filtered by mobile', function () {
    $fake = new FakeSmsGateway;

    $fake->send('+8801712345678', 'one');
    $fake->send('+8801999999999', 'two');

    expect($fake->sent)->toHaveCount(2)
        ->and($fake->messagesTo('+8801712345678'))->toHaveCount(1)
        ->and($fake->messagesTo('+8801712345678')[0]['message'])->toBe('one');
});

it('fake gateway reports a handled failure without throwing', function () {
    $fake = new FakeSmsGateway;
    $fake->failNext();

    expect($fake->send('+8801712345678', 'x'))->toBeFalse()
        ->and($fake->sent)->toBeEmpty();
});
