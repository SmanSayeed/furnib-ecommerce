<?php

declare(strict_types=1);

namespace App\Concerns;

use App\Support\Lists\ListQuery;
use Illuminate\Database\Eloquent\Builder;

/**
 * Eloquent local scope that applies a {@see ListQuery} to a query: a LIKE
 * OR-group over the whitelisted search columns (supporting `relation.column`),
 * exact-match filters, the date window on `created_at`, and a whitelisted
 * ORDER BY. Every column comes from the resource config, never raw input.
 *
 * Usage on a model: `use AppliesListFilters;` then
 * `Order::query()->applyList($listQuery)->paginate($listQuery->perPage)`.
 */
trait AppliesListFilters
{
    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeApplyList(Builder $query, ListQuery $list): Builder
    {
        if ($list->search !== null && $list->searchColumns !== []) {
            $term = '%'.$list->search.'%';

            $query->where(function (Builder $group) use ($list, $term): void {
                foreach ($list->searchColumns as $column) {
                    if (str_contains($column, '.')) {
                        [$relation, $relationColumn] = explode('.', $column, 2);
                        $group->orWhereHas(
                            $relation,
                            fn (Builder $q) => $q->where($relationColumn, 'like', $term),
                        );
                    } else {
                        $group->orWhere($column, 'like', $term);
                    }
                }
            });
        }

        foreach ($list->filters as $column => $value) {
            $query->where($query->qualifyColumn($column), $value);
        }

        $list->dateRange->apply($query, $query->qualifyColumn('created_at'));

        $direction = $list->dir === 'asc' ? 'asc' : 'desc';

        return $query->orderBy($query->qualifyColumn($list->sort), $direction);
    }
}
