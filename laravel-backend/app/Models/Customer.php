<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\Auditable;
use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Sanctum\HasApiTokens;

/**
 * @property int $id
 * @property string|null $name
 * @property string $mobile
 * @property string|null $email
 */
class Customer extends Model
{
    /** @use HasFactory<CustomerFactory> */
    use Auditable, HasApiTokens, HasFactory, SoftDeletes;

    protected $fillable = ['name', 'mobile', 'email'];

    /** @return HasMany<Order, $this> */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
