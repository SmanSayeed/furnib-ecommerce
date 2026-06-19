<?php

declare(strict_types=1);

use App\Support\AdvancePayment;
use App\Support\Money;

it('returns zero when the product is not an advance product', function () {
    $advance = AdvancePayment::forLine(Money::fromMinor(100000), false, null, null, null);

    expect($advance->toMinor())->toBe(0);
});

it('requires the full line total for type full', function () {
    $advance = AdvancePayment::forLine(Money::fromMinor(100000), true, 'full', null, null);

    expect($advance->toMinor())->toBe(100000);
});

it('computes a percentage partial advance', function () {
    // 30% of 1000.00 (100000 paisa) = 300.00
    $advance = AdvancePayment::forLine(Money::fromMinor(100000), true, 'partial', 'percentage', 30);

    expect($advance->toMinor())->toBe(30000);
});

it('uses a fixed partial amount, capped at the line total', function () {
    $advance = AdvancePayment::forLine(Money::fromMinor(100000), true, 'partial', 'amount', 50000);
    expect($advance->toMinor())->toBe(50000);

    $capped = AdvancePayment::forLine(Money::fromMinor(100000), true, 'partial', 'amount', 250000);
    expect($capped->toMinor())->toBe(100000); // never exceeds the line total
});
