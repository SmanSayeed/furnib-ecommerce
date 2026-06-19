<?php

declare(strict_types=1);

use App\Support\MobileNumber;

it('normalizes local BD numbers to E.164', function () {
    expect(MobileNumber::normalize('01712345678'))->toBe('+8801712345678');
});

it('accepts and normalizes various input forms to the same value', function (string $input) {
    expect(MobileNumber::normalize($input))->toBe('+8801712345678');
})->with([
    '01712345678',
    '+8801712345678',
    '8801712345678',
    '008801712345678',
    '017 1234 5678',
    '01712-345678',
]);

it('rejects invalid numbers', function (string $input) {
    expect(MobileNumber::isValid($input))->toBeFalse();
})->with([
    '123',
    '0171234567',        // too short
    '017123456789',      // too long
    '01212345678',       // invalid operator code (012)
    '+1 555 123 4567',   // non-BD
    'abcdefghijk',
]);

it('throws on an invalid number via fromInput', function () {
    MobileNumber::normalize('123');
})->throws(InvalidArgumentException::class);
