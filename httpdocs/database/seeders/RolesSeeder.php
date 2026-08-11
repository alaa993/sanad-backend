<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolesSeeder extends Seeder
{
    public function run(): void
    {
        $roles = ['admin', 'organization', 'specialist', 'patient'];
        foreach ($roles as $r) {
            Role::firstOrCreate(['name' => $r, 'guard_name' => 'web']);
        }

        $perms = [
            'manage users', 'manage specialists', 'manage organizations',
            'view sessions', 'create sessions', 'join sessions'
        ];
        foreach ($perms as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
        }

        $admin = Role::where('name', 'admin')->first();
        if ($admin) $admin->syncPermissions($perms);

        $org = Role::where('name', 'organization')->first();
        if ($org) $org->syncPermissions(['manage specialists', 'view sessions', 'create sessions']);

        $spec = Role::where('name', 'specialist')->first();
        if ($spec) $spec->syncPermissions(['view sessions', 'create sessions']);

        $patient = Role::where('name', 'patient')->first();
        if ($patient) $patient->syncPermissions(['join sessions', 'view sessions']);
    }
}
