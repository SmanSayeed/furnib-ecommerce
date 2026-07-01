<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(PermissionRoleSeeder::class);

        // Default delivery areas (idempotent) so checkout has zones to show.
        $this->call(ShippingZoneSeeder::class);

        // Legal / about CMS pages required for payment-gateway compliance.
        // Idempotent — existing pages are never overwritten.
        $this->call(CompliancePagesSeeder::class);

        // Bootstrap accounts only when their credentials are configured,
        // so `db:seed` does not hard-fail in environments without them.
        if (filled(config('rbac.owner_email')) && filled(config('rbac.owner_bootstrap_password'))) {
            $this->call(OwnerSeeder::class);
        }

        if (filled(config('rbac.admin_bootstrap_password'))) {
            $this->call(AdminSeeder::class);
        }
    }
}
