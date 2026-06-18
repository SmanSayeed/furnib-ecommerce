<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class PermissionRoleSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        /** @var array<int,string> $permissions */
        $permissions = config('rbac.permissions');

        foreach ($permissions as $name) {
            Permission::findOrCreate($name, 'web');
        }

        // Reset the in-memory/persistent permission cache so freshly created
        // permissions are visible to syncPermissions below.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        /** @var array<string,array<int,string>> $roles */
        $roles = config('rbac.roles');

        foreach ($roles as $roleName => $perms) {
            $role = Role::findOrCreate($roleName, 'web');
            $grant = in_array('*', $perms, true) ? $permissions : $perms;
            $role->syncPermissions($grant);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
