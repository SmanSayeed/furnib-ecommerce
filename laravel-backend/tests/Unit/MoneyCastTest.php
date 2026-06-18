<?php

declare(strict_types=1);

use App\Casts\MoneyCast;
use App\Support\Money;

it('casts a display amount to stored minor units on set', function () {
    $cast = new MoneyCast;

    expect($cast->set(new stdClass, 'price', 1234.56, []))->toBe(123456);
});

it('casts stored minor units to a Money object on get', function () {
    $cast = new MoneyCast;

    $money = $cast->get(new stdClass, 'price', 123456, []);

    expect($money)->toBeInstanceOf(Money::class)
        ->and($money->toDisplay())->toBe(1234.56);
});

it('accepts a Money instance on set', function () {
    $cast = new MoneyCast;

    expect($cast->set(new stdClass, 'price', Money::fromMinor(500), []))->toBe(500);
});

it('passes null through unchanged', function () {
    $cast = new MoneyCast;

    expect($cast->set(new stdClass, 'price', null, []))->toBeNull()
        ->and($cast->get(new stdClass, 'price', null, []))->toBeNull();
});
