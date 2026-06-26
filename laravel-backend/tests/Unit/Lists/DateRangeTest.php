<?php

declare(strict_types=1);

use App\Support\Lists\DateRange;
use Carbon\CarbonImmutable;

afterEach(function () {
    CarbonImmutable::setTestNow();
});

it('resolves today to inclusive Asia/Dhaka day bounds expressed in UTC', function () {
    // 2026-06-26 20:00 UTC is already 2026-06-27 02:00 in Dhaka (UTC+6),
    // so "today" in Dhaka is the 27th even though UTC is still the 26th.
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-06-26 20:00:00', 'UTC'));

    $range = DateRange::fromPreset('today');

    // Dhaka 2026-06-27 00:00:00 .. 23:59:59  ->  UTC 2026-06-26 18:00:00 .. 2026-06-27 17:59:59
    expect($range->from->toDateTimeString())->toBe('2026-06-26 18:00:00')
        ->and($range->to->toDateTimeString())->toBe('2026-06-27 17:59:59')
        ->and($range->from->getTimezone()->getName())->toBe('UTC')
        ->and($range->preset)->toBe('today');
});

it('resolves yesterday to the previous Dhaka day', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-06-27 06:00:00', 'UTC')); // Dhaka 12:00 the 27th

    $range = DateRange::fromPreset('yesterday');

    // Dhaka 2026-06-26 00:00:00 .. 23:59:59 -> UTC 2026-06-25 18:00:00 .. 2026-06-26 17:59:59
    expect($range->from->toDateTimeString())->toBe('2026-06-25 18:00:00')
        ->and($range->to->toDateTimeString())->toBe('2026-06-26 17:59:59');
});

it('resolves this_month within the Dhaka calendar month', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-06-15 10:00:00', 'UTC'));

    $range = DateRange::fromPreset('this_month');

    // Dhaka June 1 00:00 -> UTC May 31 18:00 ; Dhaka June 30 23:59:59 -> UTC June 30 17:59:59
    expect($range->from->toDateTimeString())->toBe('2026-05-31 18:00:00')
        ->and($range->to->toDateTimeString())->toBe('2026-06-30 17:59:59');
});

it('resolves last_7 to a 7-day inclusive window ending today', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-06-27 06:00:00', 'UTC'));

    $range = DateRange::fromPreset('last_7');

    // 7 days inclusive: Dhaka Jun 21 00:00 .. Jun 27 23:59:59
    expect($range->from->toDateTimeString())->toBe('2026-06-20 18:00:00')
        ->and($range->to->toDateTimeString())->toBe('2026-06-27 17:59:59');
});

it('resolves custom from/to dates parsed in Dhaka', function () {
    $range = DateRange::fromPreset('custom', '2026-06-01', '2026-06-10');

    expect($range->from->toDateTimeString())->toBe('2026-05-31 18:00:00')
        ->and($range->to->toDateTimeString())->toBe('2026-06-10 17:59:59')
        ->and($range->preset)->toBe('custom');
});

it('treats an unknown or all preset as an unbounded range', function () {
    $range = DateRange::fromPreset('all');

    expect($range->from)->toBeNull()
        ->and($range->to)->toBeNull()
        ->and($range->isAll())->toBeTrue();

    $unknown = DateRange::fromPreset('garbage');
    expect($unknown->isAll())->toBeTrue();
});
