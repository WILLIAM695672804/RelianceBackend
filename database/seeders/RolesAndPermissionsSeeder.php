<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // ⚠️ NE PAS truncate ici : les tables Spatie (model_has_roles surtout)
        // contiennent les liens utilisateur->role de TOUS les comptes. Les vider
        // ferait perdre son role a chaque utilisateur existant a chaque seed.
        // Ce seeder est 100% idempotent : firstOrCreate + givePermissionTo
        // (qui n'ajoutent rien en double), donc rejouable a chaque demarrage.

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
            'respond_to_proposal',

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
            'respond_to_proposal',
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
