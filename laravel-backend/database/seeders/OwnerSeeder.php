<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * Bootstraps the single highest-privilege owner account from config/env.
 * Credentials never live in source. The owner must change the bootstrap
 * password and enrol in 2FA on first login (enforced by EnsureAccountSecured).
 */
class OwnerSeeder extends Seeder
{
    public function run(): void
    {
        $email = config('rbac.owner_email');
        $password = config('rbac.owner_bootstrap_password');

        if (blank($email)) {
            throw new RuntimeException('OWNER_EMAIL is not set; cannot bootstrap the owner account.');
        }

        if (blank($password)) {
            throw new RuntimeException('OWNER_BOOTSTRAP_PASSWORD is not set; cannot bootstrap the owner account.');
        }

        $owner = User::firstOrNew(['email' => $email]);
        $owner->name = $owner->name ?: 'Owner';
        $owner->password = Hash::make($password);
        $owner->email_verified_at = $owner->email_verified_at ?? now();
        $owner->forceFill([
            'must_change_password' => true,
            'two_factor_required' => true,
        ])->save();

        if (! $owner->hasRole('owner')) {
            $owner->assignRole('owner');
        }
    }
}
