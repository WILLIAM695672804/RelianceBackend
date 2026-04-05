<?php

namespace Tests\Unit\Modules\Matching;

use Tests\TestCase;
use App\Modules\Matching\Models\ClientRequest;
use App\Modules\Matching\Services\MatchingService;
use App\Modules\Matching\Services\RequestAnalysisService;
use App\Modules\Auth\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

class MatchingFlowTest extends TestCase
{
    public function test_matching_logic_generates_proposals()
    {
        // 1. Trouver un client
        $client = User::role('client')->first();
        if (!$client) {
            $this->markTestSkipped('Aucun client trouvé pour le test.');
        }

        // 2. Créer une demande
        $request = ClientRequest::create([
            'user_id' => $client->id,
            'subject' => 'Panne électrique totale',
            'description' => 'Plus de lumière dans tout l\'appartement, besoin d\'un électricien urgent.',
            'category' => 'Electricité',
            'status' => 'pending',
            'location' => DB::raw("ST_GeomFromText('POINT(9.7679 4.0511)', 4326)")
        ]);

        // 3. Analyser la demande
        $analysisService = app(RequestAnalysisService::class);
        $analysis = $analysisService->analyze($request);

        $this->assertNotNull($analysis);

        // 4. Lancer le matching
        $matchingService = app(MatchingService::class);
        $proposals = $matchingService->findMatches($request, $analysis->extracted_skills ?? []);

        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $proposals);
        
        echo "\n[TEST] Nombre de techniciens matchés : " . $proposals->count() . "\n";
        foreach($proposals as $p) {
            echo " - Tech: " . $p['technician']->name . " | Score: " . $p['score'] . " | Dist: " . round($p['distance'], 2) . "km\n";
        }
    }
}
