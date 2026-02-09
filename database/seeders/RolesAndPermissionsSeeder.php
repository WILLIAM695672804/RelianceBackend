<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Réinitialiser le cache Spatie
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // ===== PERMISSIONS =====
        $permissions = [
            // Client
            'create_request',
            'view_own_requests',

            // Technicien
            'accept_request',
            'update_intervention_status',

            // Agence
            'manage_technicians',
            'view_agency_requests',

            // Admin
            'validate_kyc',
            'manage_users',
            'manage_platform',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // ===== ROLES =====
        $client = Role::firstOrCreate(['name' => 'client']);
        $technicien = Role::firstOrCreate(['name' => 'technicien']);
        $admin = Role::firstOrCreate(['name' => 'admin']);
        $agence = Role::firstOrCreate(['name' => 'agence']);

        // ===== ASSIGNATIONS =====
        $client->givePermissionTo([
            'create_request',
            'view_own_requests',
        ]);

        $technicien->givePermissionTo([
            'accept_request',
            'update_intervention_status',
        ]);

        $agence->givePermissionTo([
            'manage_technicians',
            'view_agency_requests',
            'accept_request',
            'update_intervention_status',
        ]);

        $admin->givePermissionTo(Permission::all());
    }
}
