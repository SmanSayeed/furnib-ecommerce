<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\Auditable;
use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $title
 * @property string $slug
 * @property string|null $details
 * @property string|null $header_image
 * @property string|null $header_image_mobile
 * @property string|null $thumbnail_image
 * @property bool $status
 * @property int $position_order
 */
class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use Auditable, HasFactory, SoftDeletes;

    protected $fillable = [
        'title', 'slug', 'details', 'header_image', 'header_image_mobile', 'thumbnail_image',
        'status', 'position_order', 'meta_title', 'meta_description', 'og_image',
    ];

    protected function casts(): array
    {
        return [
            'status' => 'boolean',
            'position_order' => 'integer',
        ];
    }

    /** @param Builder<Category> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', true);
    }

    /** @param Builder<Category> $query */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('position_order')->orderBy('title');
    }

    /** @return HasMany<Product, $this> */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
