<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * Bootstraps the day-to-day client admin (default admin@gmail.com). The
 * bootstrap password comes from env; the admin is forced to change it on
 * first login. Email/password are changeable afterwards.
 */
class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = config('rbac.admin_email');
        $password = config('rbac.admin_bootstrap_password');

        if (blank($password)) {
            throw new RuntimeException('ADMIN_PASSWORD is not set; cannot bootstrap the admin account.');
        }

        $admin = User::firstOrNew(['email' => $email]);
        $admin->name = $admin->name ?: 'Administrator';
        $admin->password = Hash::make($password);
        $admin->email_verified_at = $admin->email_verified_at ?? now();
        $admin->forceFill(['must_change_password' => true])->save();

        if (! $admin->hasRole('admin')) {
            $admin->assignRole('admin');
        }
    }
}
