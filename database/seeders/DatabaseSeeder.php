<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Référentiels : nécessaires dans tous les environnements.
        $this->call([
            RolesAndPermissionsSeeder::class,
            SkillsSeeder::class,
            MatchingRulesSeeder::class,
            // Administrateur initial : identifiants issus de ADMIN_EMAIL / ADMIN_PASSWORD hors développement.
            AdminUserSeeder::class,
        ]);

        // Comptes de démonstration (mots de passe connus) : jamais hors développement.
        if (app()->environment('local', 'testing')) {
            $this->call(TestTechnicianSeeder::class);
        }
        // \App\Models\User::factory(10)->create();

        // \App\Models\User::factory()->create([
        //     'name' => 'Test User',
        //     'email' => 'test@example.com',
        // ]);
    }
}
