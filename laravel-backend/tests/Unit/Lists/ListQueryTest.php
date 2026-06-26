<?php

declare(strict_types=1);

use App\Support\Lists\ListQuery;
use Illuminate\Http\Request;

function makeListConfig(array $overrides = []): array
{
    return array_merge([
        'searchColumns' => ['order_no', 'customer.name', 'customer.mobile'],
        'filters' => ['status', 'payment_status'],
        'sorts' => ['created_at', 'total', 'status'],
        'defaultSort' => 'created_at',
        'defaultDir' => 'desc',
    ], $overrides);
}

it('falls back to the default sort when the requested sort is not whitelisted', function () {
    $request = Request::create('/admin/orders', 'GET', ['sort' => 'total); DROP TABLE orders;--', 'dir' => 'asc']);

    $query = ListQuery::fromRequest($request, makeListConfig());

    expect($query->sort)->toBe('created_at')
        ->and($query->dir)->toBe('asc');
});

it('keeps a whitelisted sort and normalises an invalid direction to the default', function () {
    $request = Request::create('/admin/orders', 'GET', ['sort' => 'total', 'dir' => 'UPWARD']);

    $query = ListQuery::fromRequest($request, makeListConfig());

    expect($query->sort)->toBe('total')
        ->and($query->dir)->toBe('desc');
});

it('collects only whitelisted, non-empty filters', function () {
    $request = Request::create('/admin/orders', 'GET', [
        'status' => 'pending',
        'payment_status' => '',     // empty -> ignored
        'category_id' => '7',       // not whitelisted -> ignored
    ]);

    $query = ListQuery::fromRequest($request, makeListConfig());

    expect($query->filters)->toBe(['status' => 'pending']);
});

it('trims the search term and exposes the search column whitelist', function () {
    $request = Request::create('/admin/orders', 'GET', ['search' => '  01711  ']);

    $query = ListQuery::fromRequest($request, makeListConfig());

    expect($query->search)->toBe('01711')
        ->and($query->searchColumns)->toBe(['order_no', 'customer.name', 'customer.mobile']);
});

it('treats a blank search as no search', function () {
    $request = Request::create('/admin/orders', 'GET', ['search' => '   ']);

    $query = ListQuery::fromRequest($request, makeListConfig());

    expect($query->search)->toBeNull();
});

it('builds a date range from the range preset', function () {
    $request = Request::create('/admin/orders', 'GET', ['range' => 'this_month']);

    $query = ListQuery::fromRequest($request, makeListConfig());

    expect($query->dateRange->preset)->toBe('this_month');
});

it('clamps per_page within sane bounds', function () {
    $tooBig = ListQuery::fromRequest(Request::create('/x', 'GET', ['per_page' => '5000']), makeListConfig());
    $tooSmall = ListQuery::fromRequest(Request::create('/x', 'GET', ['per_page' => '0']), makeListConfig());

    expect($tooBig->perPage)->toBe(100)
        ->and($tooSmall->perPage)->toBe(1);
});
