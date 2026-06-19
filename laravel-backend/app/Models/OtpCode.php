<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\OtpCodeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One-time verification code for customer mobile login. The `code` column holds
 * a bcrypt hash — never the plaintext OTP. Intentionally NOT Auditable: codes
 * must never reach the activity log.
 *
 * @property int $id
 * @property string $mobile
 * @property string $code
 * @property Carbon $expires_at
 * @property int $attempts
 */
class OtpCode extends Model
{
    /** @use HasFactory<OtpCodeFactory> */
    use HasFactory;

    protected $fillable = ['mobile', 'code', 'expires_at', 'attempts'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'attempts' => 'integer',
        ];
    }
}
