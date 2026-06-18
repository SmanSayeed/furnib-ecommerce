<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Repositories\Contracts\RepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

abstract class BaseRepository implements RepositoryInterface
{
    public function __construct(protected Model $model) {}

    /** @param array<int,string> $columns */
    public function all(array $columns = ['*']): Collection
    {
        return $this->model->newQuery()->get($columns);
    }

    public function find(int|string $id): ?Model
    {
        return $this->model->newQuery()->find($id);
    }

    public function findOrFail(int|string $id): Model
    {
        return $this->model->newQuery()->findOrFail($id);
    }

    /** @param array<string,mixed> $attributes */
    public function create(array $attributes): Model
    {
        return $this->model->newQuery()->create($attributes);
    }

    /** @param array<string,mixed> $attributes */
    public function update(Model $model, array $attributes): Model
    {
        $model->fill($attributes)->save();

        return $model->refresh();
    }

    public function delete(Model $model): bool
    {
        return (bool) $model->delete();
    }
}
