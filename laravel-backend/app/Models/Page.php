<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\PageFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A CMS content page (About us, Privacy policy, …) shown in the storefront
 * footer. `body_html` is HTMLPurifier-sanitised before it is ever stored, so it
 * is safe to render directly.
 *
 * @property int $id
 * @property string $slug
 * @property string $title
 * @property string|null $body_html
 * @property bool $is_published
 * @property bool $is_system
 * @property bool $show_in_footer
 * @property int $position
 */
class Page extends Model
{
    /** @use HasFactory<PageFactory> */
    use HasFactory;

    protected $fillable = [
        'slug',
        'title',
        'body_html',
        'is_published',
        'is_system',
        'show_in_footer',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'is_system' => 'boolean',
            'show_in_footer' => 'boolean',
            'position' => 'integer',
        ];
    }

    /** @param  Builder<Page>  $query */
    public function scopePublished(Builder $query): void
    {
        $query->where('is_published', true);
    }

    /**
     * Published pages that appear in the storefront footer, in display order.
     *
     * @param  Builder<Page>  $query
     */
    public function scopeInFooter(Builder $query): void
    {
        $query->where('is_published', true)
            ->where('show_in_footer', true)
            ->orderBy('position')
            ->orderBy('title');
    }
}
