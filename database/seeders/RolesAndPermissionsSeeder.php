<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Nettoyer les tables Spatie (Postgres compatible)
        \Illuminate\Support\Facades\DB::statement("SET session_replication_role = 'replica';");
        \Illuminate\Support\Facades\DB::table('role_has_permissions')->truncate();
        \Illuminate\Support\Facades\DB::table('model_has_roles')->truncate();
        \Illuminate\Support\Facades\DB::table('model_has_permissions')->truncate();
        \Illuminate\Support\Facades\DB::table('roles')->truncate();
        \Illuminate\Support\Facades\DB::table('permissions')->truncate();
        \Illuminate\Support\Facades\DB::statement("SET session_replication_role = 'origin';");

        // Réinitialiser le cache Spatie
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // ===== PERMISSIONS =====
        $permissions = [
            // Client
            'create_request',
            'view_own_requests',
            'view_matching_results',
            'accept_proposals',

            // Technicien
            'accept_request',
            'update_intervention_status',
            'receive_matching_alerts',
            'manage_proposals',

            // Agence
            'manage_technicians',
            'view_agency_requests',
            'manage_proposals',

            // Admin
            'validate_kyc',
            'manage_users',
            'manage_platform',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'api']);
        }

        // ===== ROLES =====
        $client = Role::firstOrCreate(['name' => 'client', 'guard_name' => 'api']);
        $technicien = Role::firstOrCreate(['name' => 'technicien', 'guard_name' => 'api']);
        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'api']);
        $agence = Role::firstOrCreate(['name' => 'agence', 'guard_name' => 'api']);

        // ===== ASSIGNATIONS =====
        $client->givePermissionTo([
            'create_request',
            'view_own_requests',
            'view_matching_results',
            'accept_proposals',
        ]);

        $technicien->givePermissionTo([
            'accept_request',
            'update_intervention_status',
            'receive_matching_alerts',
            'manage_proposals',
        ]);

        $agence->givePermissionTo([
            'manage_technicians',
            'view_agency_requests',
            'accept_request',
            'update_intervention_status',
            'manage_proposals',
        ]);

        $admin->givePermissionTo(Permission::all());
    }
}
