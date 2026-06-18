<?php

declare(strict_types=1);

use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Repositories\Eloquent\UserRepository;

it('resolves the repository interface to its eloquent implementation', function () {
    expect(app(UserRepositoryInterface::class))->toBeInstanceOf(UserRepository::class);
});

it('finds a user by email through the repository', function () {
    $user = User::factory()->create(['email' => 'mama@furnib.test']);

    $found = app(UserRepositoryInterface::class)->findByEmail('mama@furnib.test');

    expect($found)->not->toBeNull()
        ->and($found->is($user))->toBeTrue();
});
