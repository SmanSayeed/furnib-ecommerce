<?php

declare(strict_types=1);

use App\Support\Capi\CapiUserData;

it('hashes email and phone and normalizes them first', function () {
    $data = (new CapiUserData(email: '  Buyer@Example.COM ', phone: '01712-345678'))->toArray();

    expect($data['em'])->toBe(hash('sha256', 'buyer@example.com'))
        ->and($data['ph'])->toBe(hash('sha256', '8801712345678'));
});

it('passes IP, user agent and fb cookies through unhashed (non-secret signals)', function () {
    $data = (new CapiUserData(ip: '203.0.113.7', userAgent: 'UA/1.0', fbp: 'fb.1.x', fbc: 'fb.1.y'))->toArray();

    expect($data['client_ip_address'])->toBe('203.0.113.7')
        ->and($data['client_user_agent'])->toBe('UA/1.0')
        ->and($data['fbp'])->toBe('fb.1.x')
        ->and($data['fbc'])->toBe('fb.1.y')
        ->and($data)->not->toHaveKey('em'); // no PII supplied → key dropped
});

it('drops empty fields instead of hashing blanks', function () {
    expect((new CapiUserData(email: '', phone: null))->toArray())->toBe([]);
});

it('keeps an already-international number without double-prefixing', function () {
    $data = (new CapiUserData(phone: '+8801712345678'))->toArray();

    expect($data['ph'])->toBe(hash('sha256', '8801712345678'));
});
