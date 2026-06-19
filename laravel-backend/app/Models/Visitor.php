<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\VisitorFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A storefront pageview. Stores only non-PII traffic data (no name/email).
 *
 * @property int $id
 * @property string|null $session_id
 * @property string|null $ip
 * @property string|null $user_agent
 * @property string $path
 * @property string|null $referrer
 * @property string|null $utm_source
 * @property string|null $utm_medium
 * @property string|null $utm_campaign
 */
class Visitor extends Model
{
    /** @use HasFactory<VisitorFactory> */
    use HasFactory;

    protected $fillable = [
        'session_id', 'ip', 'user_agent', 'path', 'referrer',
        'utm_source', 'utm_medium', 'utm_campaign',
    ];
}
