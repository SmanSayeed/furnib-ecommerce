<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A captured application exception, persisted so the owner can review errors
 * from the developer console even in production (where logs go to stderr and no
 * readable log file exists). Rows are immutable — recorded once, never updated.
 *
 * @property int $id
 * @property string $level
 * @property string $message
 * @property string|null $exception_class
 * @property string|null $file
 * @property int|null $line
 * @property string|null $method
 * @property string|null $url
 * @property Carbon|null $created_at
 */
class ErrorLog extends Model
{
    /** Errors are write-once; there is no updated_at column. */
    public const UPDATED_AT = null;

    protected $fillable = [
        'level',
        'message',
        'exception_class',
        'file',
        'line',
        'method',
        'url',
    ];

    protected function casts(): array
    {
        return [
            'line' => 'integer',
            'created_at' => 'datetime',
        ];
    }
}
