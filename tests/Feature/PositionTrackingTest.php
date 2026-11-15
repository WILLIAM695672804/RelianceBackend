<?php

namespace Tests\Feature;

use App\Events\TechnicianPositionUpdated;
use App\Modules\Matching\Models\Proposal;
use App\Modules\Operations\Models\TechnicianJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\Concerns\BuildsWorld;
use Tests\TestCase;

/** Position du technicien : conservation, diffusion en direct et lecture par le client. */
class PositionTrackingTest extends TestCase
{
    use RefreshDatabase, BuildsWorld;

    private $client;
    private $tech;
    private $demande;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->client = $this->makeUser('client');
        $this->tech = $this->makeUser('technicien');
        $this->demande = $this->makeRequestFor($this->client);
        Proposal::forceCreate([
            'request_id' => $this->demande->id, 'technician_id' => $this->tech->id,
            'price' => 15000, 'status' => 'accepted', 'expires_at' => now()->addDay(),
        ]);
    }

    private function mission(string $statut): TechnicianJob
    {
        return TechnicianJob::create(['request_id' => $this->demande->id, 'technician_id' => $this->tech->id, 'status' => $statut]);
    }

    private function envoyer(float $lat = 4.05, float $lng = 9.76)
    {
        return $this->actingAs($this->tech, 'sanctum')->postJson('/api/v1/position/update', ['latitude' => $lat, 'longitude' => $lng]);
    }

    public function test_le_client_lit_la_derniere_position_et_son_heure(): void
    {
        $this->envoyer(4.06, 9.77)->assertOk();

        $data = $this->actingAs($this->client, 'sanctum')->getJson('/api/v1/position/technician')->assertOk()->json('data');

        $this->assertEqualsWithDelta(4.06, $data['latitude'], 0.0001);
        $this->assertNotEmpty($data['updated_at']);
    }

    public function test_sans_nouvel_envoi_la_position_expire_au_bout_de_cinq_minutes(): void
    {
        $this->envoyer()->assertOk();

        $this->travel(6)->minutes();

        $this->assertNull($this->actingAs($this->client, 'sanctum')->getJson('/api/v1/position/technician')->json('data'));
    }

    public function test_un_renvoi_regulier_maintient_la_position(): void
    {
        foreach (range(1, 12) as $i) {   // 6 minutes de signal toutes les 30 s, sans bouger
            $this->envoyer()->assertOk();
            $this->travel(30)->seconds();
        }

        $this->assertNotNull($this->actingAs($this->client, 'sanctum')->getJson('/api/v1/position/technician')->json('data'));
    }

    /** @dataProvider statutsDiffuses */
    public function test_la_position_est_diffusee_en_direct_pendant_la_course(string $statut): void
    {
        Event::fake([TechnicianPositionUpdated::class]);
        $mission = $this->mission($statut);

        $this->envoyer()->assertOk();

        Event::assertDispatched(TechnicianPositionUpdated::class, fn ($e) => $e->jobId === $mission->id);
    }

    public static function statutsDiffuses(): array
    {
        return [['en_route'], ['arrived'], ['in_progress']];
    }

    public function test_la_position_n_est_pas_diffusee_avant_le_depart_ni_apres_la_fin(): void
    {
        Event::fake([TechnicianPositionUpdated::class]);
        $mission = $this->mission('pending');

        $this->envoyer()->assertOk();
        $mission->update(['status' => 'completed']);
        $this->envoyer()->assertOk();

        Event::assertNotDispatched(TechnicianPositionUpdated::class);
    }
}
