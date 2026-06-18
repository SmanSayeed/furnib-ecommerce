<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $group
 * @property string $key
 * @property string|null $value
 * @property string $type
 * @property bool $is_secret
 */
class Setting extends Model
{
    protected $fillable = ['group', 'key', 'value', 'type', 'is_secret'];

    protected function casts(): array
    {
        return [
            'is_secret' => 'boolean',
        ];
    }
}
