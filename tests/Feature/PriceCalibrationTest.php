<?php

namespace Tests\Feature;

use App\Modules\Matching\Models\MlModel;
use App\Modules\Matching\Models\MlTrainingLog;
use App\Modules\Matching\Models\RequestAnalysis;
use App\Modules\Matching\Services\PriceCalibrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsWorld;
use Tests\TestCase;

class PriceCalibrationTest extends TestCase
{
    use RefreshDatabase, BuildsWorld;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    /** Missions « fuite » (difficulté moderate) acceptées aux prix donnés. */
    private function missions(array $prix): void
    {
        $client = $this->makeUser('client');
        $tech = $this->makeTechnician();
        foreach ($prix as $p) {
            $this->assignedMission($client, $tech, $p);
            // Une seule mission active à la fois pour ce technicien : on libère la demande pour la suivante.
            \App\Modules\Matching\Models\ClientRequest::query()->update(['status' => 'completed']);
        }
    }

    public function test_sans_modele_le_prix_suggere_est_la_valeur_par_defaut(): void
    {
        $s = app(PriceCalibrationService::class);

        $this->assertSame(15000.0, $s->estimate('moderate'));
        $this->assertSame(5000.0, $s->estimate('simple'));
        $this->assertSame(15000.0, $s->estimate('inconnu'));
        $this->assertNull($s->deployedVersion());
    }

    public function test_sans_echantillon_l_entrainement_ne_deploie_rien(): void
    {
        $this->assertNull(app(PriceCalibrationService::class)->train());
        $this->assertSame(0, MlModel::count());
    }

    public function test_l_entrainement_calcule_la_mediane_journalise_les_metriques_et_deploie(): void
    {
        $this->missions([20000, 22000, 24000, 30000]);

        $modele = app(PriceCalibrationService::class)->train();

        $this->assertNotNull($modele);
        $this->assertTrue($modele->deployed);
        $this->assertEqualsWithDelta(23000, $modele->metadata['by_difficulty']['moderate']['median'], 0.01);
        $this->assertSame(4, $modele->metadata['by_difficulty']['moderate']['samples']);
        $journal = MlTrainingLog::where('model_id', $modele->id)->firstOrFail();
        $this->assertSame(4, $journal->metrics['samples']);
        $this->assertLessThan($journal->metrics['mae_default'], $journal->metrics['mae_model'], 'Le modèle doit mieux coller aux prix réels que la valeur par défaut.');
    }

    public function test_le_prix_suggere_d_une_nouvelle_demande_utilise_le_modele_deploye(): void
    {
        $this->missions([20000, 22000, 24000, 30000]);
        $modele = app(PriceCalibrationService::class)->train();
        $client = $this->makeUser('client');

        $demande = $this->makeRequestFor($client);
        $analyse = RequestAnalysis::where('request_id', $demande->id)->firstOrFail();

        $this->assertEqualsWithDelta(23000, (float) $analyse->suggested_price, 0.01);
        $this->assertSame($modele->version, $analyse->payload['price_model']);
    }

    public function test_sous_le_seuil_d_echantillons_la_valeur_par_defaut_est_conservee(): void
    {
        $this->missions([20000, 22000]); // 2 < 3

        app(PriceCalibrationService::class)->train();

        $this->assertSame(15000.0, app(PriceCalibrationService::class)->estimate('moderate'));
    }

    public function test_un_nouvel_entrainement_remplace_la_version_deployee(): void
    {
        $this->missions([20000, 22000, 24000]);
        $s = app(PriceCalibrationService::class);
        $premier = $s->train();
        sleep(1); // les versions sont horodatées à la seconde

        $second = $s->train();

        $this->assertNotSame($premier->version, $second->version);
        $this->assertFalse($premier->fresh()->deployed);
        $this->assertSame(1, MlModel::where('deployed', true)->count());
    }

    public function test_la_commande_et_l_api_d_administration(): void
    {
        $this->missions([20000, 22000, 24000]);

        $this->artisan('ml:calibrate-prices')->assertExitCode(0);

        $this->actingAs($this->makeUser('technicien'), 'sanctum')->getJson('/api/v1/admin/ml-models')->assertForbidden();
        $liste = $this->actingAs($this->makeUser('admin'), 'sanctum')->getJson('/api/v1/admin/ml-models')->assertOk()->json('data');
        $this->assertCount(1, $liste);
        $this->assertTrue($liste[0]['deployed']);
        $this->assertSame(3, $liste[0]['metrics']['samples']);
        $this->actingAs($this->makeUser('admin'), 'sanctum')->postJson('/api/v1/admin/ml-models/train')->assertStatus(201);
    }
}
