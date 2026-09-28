<?php

namespace Database\Seeders;

use App\Domain\Access\Roles;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/** Idempotent: safe to re-run after adding permissions. */
class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $registrar = app(PermissionRegistrar::class);
        $registrar->forgetCachedPermissions();

        foreach (Roles::PERMISSIONS as $name) {
            Permission::findOrCreate($name, 'web');
        }

        // Global (tenant-less) role definitions.
        $previousTeam = $registrar->getPermissionsTeamId();
        $registrar->setPermissionsTeamId(null);

        foreach (Roles::matrix() as $roleName => $permissions) {
            $role = Role::query()->whereNull('tenant_id')->where('name', $roleName)->where('guard_name', 'web')->first()
                ?? Role::create(['name' => $roleName, 'guard_name' => 'web', 'tenant_id' => null]);
            $role->syncPermissions($permissions);
        }

        $registrar->setPermissionsTeamId($previousTeam);
        $registrar->forgetCachedPermissions();
    }
}
