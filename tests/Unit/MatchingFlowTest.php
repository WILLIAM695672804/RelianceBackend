<?php

namespace Tests\Unit\Modules\Matching;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use App\Modules\Auth\Models\User;
use App\Modules\Matching\Models\ClientRequest;
use App\Modules\Matching\Services\MatchingService;
use App\Modules\Matching\Services\RequestAnalysisService;
use App\Modules\Profile\Models\Location;
use App\Modules\Profile\Models\Skill;

/**
 * Régression : ce test s'appuyait sur un schéma pré-migration
 * (colonnes `user_id`/`subject`/`location` sur client_requests, méthode
 * `MatchingService::findMatches()`) remplacé depuis par la migration
 * "module3_upgrade" (client_id/title/address_id/detected_category) et une
 * réécriture complète du service de matching (méthode `match()`, scoring
 * pondéré, géolocalisation via `locations.geom`). Le test n'avait jamais été
 * mis à jour et échouait faute de fixtures (RefreshDatabase non activé,
 * aucun rôle/technicien seedé).
 */
class MatchingFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'RolesAndPermissionsSeeder']);
    }

    public function test_matching_logic_generates_proposals()
    {
        // Compétence détectée par mot-clé ("panne électrique" → catégorie electricite)
        $skill = Skill::create([
            'slug' => 'electricite-generale',
            'name' => 'Électricité générale',
            'category' => 'electricite',
        ]);

        // 1. Client avec une adresse géolocalisée (lat/lng lues depuis `meta` par MatchingService)
        $client = User::create([
            'name' => 'Client Test',
            'email' => 'client_matching@test.com',
            'password' => bcrypt('password123'),
        ]);
        $client->assignRole('client');

        $address = Location::create([
            'user_id' => $client->id,
            'label' => 'Domicile',
            'address' => 'Bonapriso, Douala',
            'is_default' => true,
            'meta' => ['latitude' => 4.0511, 'longitude' => 9.7679],
        ]);

        // 2. Technicien vérifié/actif, avec la compétence et une position géographique réelle
        $technician = User::create([
            'name' => 'Technicien Test',
            'email' => 'tech_matching@test.com',
            'password' => bcrypt('password123'),
            'verified' => true,
            'is_active' => true,
            'rating' => 4.5,
        ]);
        $technician->assignRole('technicien');
        $technician->skills()->attach($skill->id, [
            'proficiency' => 80,
            'hourly_rate' => 5000,
            'travel_fee' => 1000,
        ]);

        $techLocation = Location::create([
            'user_id' => $technician->id,
            'label' => "Zone d'intervention",
            'address' => 'Akwa, Douala',
            'is_default' => true,
        ]);
        // `geom` (PostGIS geography) n'est pas mass-assignable via Eloquent, cf. Location::$fillable
        DB::statement(
            'UPDATE locations SET geom = ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography WHERE id = ?',
            [9.7700, 4.0500, $techLocation->id]
        );

        // 3. Créer la demande client
        $request = ClientRequest::create([
            'client_id' => $client->id,
            'title' => 'Panne électrique totale',
            'description' => "Plus de lumière dans tout l'appartement, besoin d'un électricien urgent.",
            'address_id' => $address->id,
            'status' => 'pending',
        ]);

        // 4. Analyser la demande (détection de compétence par mots-clés)
        $analysisService = app(RequestAnalysisService::class);
        $analysis = $analysisService->analyze($request);

        $this->assertNotNull($analysis);
        $this->assertContains('electricite-generale', $analysis->estimated_skills);
        $this->assertEquals($skill->id, $request->fresh()->detected_category);

        // 5. Lancer le matching
        $matchingService = app(MatchingService::class);
        $result = $matchingService->match($request->fresh());

        $this->assertArrayHasKey('technicians', $result);
        $this->assertCount(1, $result['technicians']);
        $this->assertEquals($technician->id, $result['technicians'][0]['id']);
    }
}
