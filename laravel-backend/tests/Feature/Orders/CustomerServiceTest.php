<?php

declare(strict_types=1);

use App\Models\Customer;
use App\Services\Orders\CustomerService;

beforeEach(function () {
    $this->service = app(CustomerService::class);
});

it('creates a new customer with normalized mobile', function () {
    $customer = $this->service->findOrCreateByMobile('01712345678', 'Karim', 'k@example.com');

    expect($customer->mobile)->toBe('+8801712345678');
    expect($customer->name)->toBe('Karim');
    expect(Customer::query()->count())->toBe(1);
});

it('reuses the existing customer for any input form of the same mobile', function () {
    $first = $this->service->findOrCreateByMobile('01712345678', 'Karim');
    $second = $this->service->findOrCreateByMobile('+8801712345678', 'Someone Else');

    expect($second->id)->toBe($first->id);
    expect(Customer::query()->count())->toBe(1);
});

it('fills name/email when previously blank but never overwrites a set name', function () {
    $created = $this->service->findOrCreateByMobile('01712345678');
    expect($created->name)->toBeNull();

    $withName = $this->service->findOrCreateByMobile('01712345678', 'Karim', 'k@example.com');
    expect($withName->name)->toBe('Karim');
    expect($withName->email)->toBe('k@example.com');

    $again = $this->service->findOrCreateByMobile('01712345678', 'Different Name');
    expect($again->refresh()->name)->toBe('Karim'); // not overwritten
});

it('throws on an invalid mobile', function () {
    $this->service->findOrCreateByMobile('123');
})->throws(InvalidArgumentException::class);
