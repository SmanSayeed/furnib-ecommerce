<?php

declare(strict_types=1);

use App\Support\Money;

it('round-trips a display amount to minor units without precision loss', function () {
    $money = Money::fromDisplay(1234.56);

    expect($money->toMinor())->toBe(123456)
        ->and($money->toDisplay())->toBe(1234.56);
});

it('adds money in minor units', function () {
    $sum = Money::fromMinor(100)->add(Money::fromMinor(250));

    expect($sum->toMinor())->toBe(350);
});

it('subtracts money in minor units', function () {
    $diff = Money::fromMinor(350)->subtract(Money::fromMinor(100));

    expect($diff->toMinor())->toBe(250);
});

it('rejects negative minor units', function () {
    Money::fromMinor(-1);
})->throws(InvalidArgumentException::class);

it('rejects non-numeric display input', function () {
    Money::fromDisplay('not-a-number');
})->throws(InvalidArgumentException::class);

it('formats with a currency symbol', function () {
    expect(Money::fromMinor(123456)->format('৳'))->toBe('৳1,234.56');
});
