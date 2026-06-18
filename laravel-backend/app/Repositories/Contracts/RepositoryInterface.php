<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Generic data-access contract. Concrete repositories extend the Eloquent
 * base implementation; callers depend on this interface, never on Eloquent.
 */
interface RepositoryInterface
{
    /** @param array<int,string> $columns */
    public function all(array $columns = ['*']): Collection;

    public function find(int|string $id): ?Model;

    public function findOrFail(int|string $id): Model;

    /** @param array<string,mixed> $attributes */
    public function create(array $attributes): Model;

    /** @param array<string,mixed> $attributes */
    public function update(Model $model, array $attributes): Model;

    public function delete(Model $model): bool;
}
