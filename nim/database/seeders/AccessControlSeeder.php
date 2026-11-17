<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class AccessControlSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $editorPermissions = [
            'dashboard.view',
            'membership.register.view',
            'membership.register.import',
            'membership.register.process',
            'membership.register.export',
            'membership.upgrades.view',
            'membership.upgrades.import',
            'membership.upgrades.process',
            'membership.upgrades.export',
            'membership.batches.view',
        ];

        $adminPermissions = [
            'users.manage',
            'settings.manage',
            'upgrade-types.manage',
            'audit-logs.view',
        ];

        $permissions = [...$editorPermissions, ...$adminPermissions];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        Role::findOrCreate('Super Admin', 'web')->syncPermissions($permissions);
        Role::findOrCreate('Editor', 'web')->syncPermissions($editorPermissions);
    }
}
