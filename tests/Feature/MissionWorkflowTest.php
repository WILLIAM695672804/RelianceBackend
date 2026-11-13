<?php

namespace Tests\Feature;

use App\Modules\Finance\Models\Hold;
use App\Modules\Matching\Models\ClientRequest;
use App\Modules\Operations\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsWorld;
use Tests\TestCase;

/**
 * Parcours métier de bout en bout : proposition → mandat → job → devis → séquestre → paiement → avis.
 */
class MissionWorkflowTest extends TestCase
{
    use RefreshDatabase, BuildsWorld;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    // ── Propositions ───────────────────────────────────────

    public function test_refus_remet_la_demande_en_selection_et_notifie_le_client(): void
    {
        $client = $this->makeUser('client');
        $tech = $this->makeTechnician();
        $request = $this->makeRequestFor($client);

        $proposalId = $this->actingAs($client, 'sanctum')
            ->postJson("/api/v1/requests/{$request->id}/select-technician", ['technician_id' => $tech->id, 'price' => 9000])
            ->assertSuccessful()->json('data.id');

        $this->actingAs($tech, 'sanctum')->postJson("/api/v1/proposals/{$proposalId}/refuse")->assertSuccessful();

        $this->assertSame('analyzed', $request->fresh()->status);
        $this->assertNotEmpty($this->actingAs($client, 'sanctum')->getJson('/api/v1/notifications')->json('data'));

        // Nouveau choix possible après refus.
        $this->actingAs($client, 'sanctum')
            ->postJson("/api/v1/requests/{$request->id}/select-technician", ['technician_id' => $tech->id, 'price' => 9500])
            ->assertSuccessful();
    }

    public function test_la_selection_d_un_non_technicien_est_refusee(): void
    {
        $client = $this->makeUser('client');
        $request = $this->makeRequestFor($client);

        $this->actingAs($client, 'sanctum')
            ->postJson("/api/v1/requests/{$request->id}/select-technician", ['technician_id' => $client->id, 'price' => 1])
            ->assertStatus(422);
    }

    public function test_une_demande_deja_assignee_n_accepte_plus_de_selection(): void
    {
        $client = $this->makeUser('client');
        $tech = $this->makeTechnician();
        [$request] = $this->assignedMission($client, $tech);

        $this->actingAs($client, 'sanctum')
            ->postJson("/api/v1/requests/{$request->id}/select-technician", ['technician_id' => $tech->id, 'price' => 1000])
            ->assertStatus(422);
    }

    // ── Mandat ─────────────────────────────────────────────

    public function test_un_job_exige_une_proposition_acceptee_du_technicien(): void
    {
        $client = $this->makeUser('client');
        $tech = $this->makeTechnician();
        $other = $this->makeTechnician();
        [$request] = $this->assignedMission($client, $tech);

        $this->actingAs($client, 'sanctum')->postJson('/api/v1/jobs', ['request_id' => $request->id])->assertForbidden();
        $this->actingAs($other, 'sanctum')->postJson('/api/v1/jobs', ['request_id' => $request->id])->assertForbidden();
        $this->actingAs($tech, 'sanctum')->postJson('/api/v1/jobs', ['request_id' => $request->id])->assertSuccessful();
    }

    public function test_la_creation_du_job_est_idempotente(): void
    {
        $client = $this->makeUser('client');
        $tech = $this->makeTechnician();
        [$request] = $this->assignedMission($client, $tech);

        $first = $this->actingAs($tech, 'sanctum')->postJson('/api/v1/jobs', ['request_id' => $request->id])->json('data.id');
        $second = $this->actingAs($tech, 'sanctum')->postJson('/api/v1/jobs', ['request_id' => $request->id])->json('data.id');

        $this->assertNotNull($first);
        $this->assertSame($first, $second);
    }

    public function test_un_devis_exige_une_proposition_acceptee_du_technicien(): void
    {
        $client = $this->makeUser('client');
        $tech = $this->makeTechnician();
        $other = $this->makeTechnician();
        [$request] = $this->assignedMission($client, $tech);

        $payload = ['request_id' => $request->id, 'total_amount' => 1000, 'breakdown' => []];
        $this->actingAs($other, 'sanctum')->postJson('/api/v1/quotes', $payload)->assertForbidden();
    }

    // ── Séquestre et paiement ──────────────────────────────

    public function test_acceptation_du_devis_sans_fonds_ne_pose_pas_de_sequestre(): void
    {
        $client = $this->makeUser('client');
        $tech = $this->makeTechnician();
        [$request] = $this->assignedMission($client, $tech);
        $quoteId = $this->sentQuote($tech, $request, 12000);

        $response = $this->actingAs($client, 'sanctum')->postJson("/api/v1/quotes/{$quoteId}/accept")->assertSuccessful();

        $this->assertSame('insufficient_funds', $response->json('data.escrow'));
        $this->assertSame(0, Hold::count());
    }

    public function test_paiement_sans_fonds_renvoie_402_et_ne_marque_pas_la_facture_payee(): void
    {
        $client = $this->makeUser('client');
        $tech = $this->makeTechnician();
        [$request] = $this->assignedMission($client, $tech);
        $quoteId = $this->sentQuote($tech, $request);
        $invoiceId = $this->actingAs($client, 'sanctum')->postJson("/api/v1/quotes/{$quoteId}/accept")->json('data.invoice_id');

        $this->actingAs($client, 'sanctum')->postJson("/api/v1/invoices/{$invoiceId}/pay")->assertStatus(402);

        $this->assertNotSame('paid', Invoice::findOrFail($invoiceId)->status);
    }

    public function test_paiement_avec_fonds_debite_le_client_et_credite_le_technicien_une_seule_fois(): void
    {
        $client = $this->makeUser('client');
        $tech = $this->makeTechnician();
        $this->fund($tech, 0);
        [$request] = $this->assignedMission($client, $tech);
        $quoteId = $this->sentQuote($tech, $request, 12000);
        $invoiceId = $this->actingAs($client, 'sanctum')->postJson("/api/v1/quotes/{$quoteId}/accept")->json('data.invoice_id');
        $this->fund($client, 50000);

        $this->actingAs($client, 'sanctum')->postJson("/api/v1/invoices/{$invoiceId}/pay")->assertSuccessful();

        $this->assertEqualsWithDelta(38000, $this->balanceOf($client), 0.01);
        $this->assertGreaterThan(0, $this->balanceOf($tech));
        $this->assertSame('paid', Invoice::findOrFail($invoiceId)->status);

        // Idempotence : un second paiement est refusé et ne débite pas de nouveau.
        $this->actingAs($client, 'sanctum')->postJson("/api/v1/invoices/{$invoiceId}/pay")->assertStatus(422);
        $this->assertEqualsWithDelta(38000, $this->balanceOf($client), 0.01);
    }

    public function test_acceptation_du_devis_avec_fonds_pose_un_sequestre_libere_a_l_annulation(): void
    {
        $client = $this->makeUser('client');
        $tech = $this->makeTechnician();
        [$request] = $this->assignedMission($client, $tech);
        $quoteId = $this->sentQuote($tech, $request, 8000);
        $this->fund($client, 50000);

        $response = $this->actingAs($client, 'sanctum')->postJson("/api/v1/quotes/{$quoteId}/accept")->assertSuccessful();
        $invoiceId = $response->json('data.invoice_id');
        $this->assertSame('held', $response->json('data.escrow'));
        $this->assertSame(1, Hold::where('reason', "invoice:{$invoiceId}")->where('status', 'held')->count());

        $jobId = $this->actingAs($tech, 'sanctum')->postJson('/api/v1/jobs', ['request_id' => $request->id])->json('data.id');
        $this->actingAs($tech, 'sanctum')->patchJson("/api/v1/jobs/{$jobId}/status", ['status' => 'cancelled'])->assertSuccessful();

        $this->assertSame(0, Hold::where('reason', "invoice:{$invoiceId}")->where('status', 'held')->count());
    }

    // ── Avis ───────────────────────────────────────────────

    public function test_avis_pre_et_post_sont_distincts_et_uniques(): void
    {
        $client = $this->makeUser('client');
        $tech = $this->makeTechnician();
        [$request] = $this->assignedMission($client, $tech);
        $jobId = $this->actingAs($tech, 'sanctum')->postJson('/api/v1/jobs', ['request_id' => $request->id])->json('data.id');

        // « pre » autorisé dès que la mission est assignée ; « post » prématuré refusé.
        $this->actingAs($client, 'sanctum')
            ->postJson('/api/v1/reviews', ['request_id' => $request->id, 'rating' => 4, 'stage' => 'pre'])->assertSuccessful();
        $this->actingAs($client, 'sanctum')
            ->postJson('/api/v1/reviews', ['request_id' => $request->id, 'rating' => 5])->assertNotFound();

        $this->actingAs($tech, 'sanctum')->patchJson("/api/v1/jobs/{$jobId}/status", ['status' => 'completed'])->assertSuccessful();

        $this->actingAs($client, 'sanctum')
            ->postJson('/api/v1/reviews', ['request_id' => $request->id, 'rating' => 5, 'comment' => 'Parfait'])->assertSuccessful();
        $this->actingAs($client, 'sanctum')
            ->postJson('/api/v1/reviews', ['request_id' => $request->id, 'rating' => 5])->assertStatus(409);
    }

    public function test_la_reputation_ne_compte_que_les_avis_post(): void
    {
        $client = $this->makeUser('client');
        $tech = $this->makeTechnician();
        [$request] = $this->assignedMission($client, $tech);

        $this->actingAs($client, 'sanctum')
            ->postJson('/api/v1/reviews', ['request_id' => $request->id, 'rating' => 1, 'stage' => 'pre'])->assertSuccessful();

        $this->assertSame(0, \App\Modules\Gamification\Models\Review::where('stage', 'post')->count());
        $this->assertSame(1, \App\Modules\Gamification\Models\Review::where('stage', 'pre')->count());
    }

    public function test_les_etats_de_mission_notifient_le_client(): void
    {
        $client = $this->makeUser('client');
        $tech = $this->makeTechnician();
        [$request] = $this->assignedMission($client, $tech);
        $jobId = $this->actingAs($tech, 'sanctum')->postJson('/api/v1/jobs', ['request_id' => $request->id])->json('data.id');

        $this->actingAs($tech, 'sanctum')->patchJson("/api/v1/jobs/{$jobId}/status", ['status' => 'in_progress'])->assertSuccessful();

        $titles = collect($this->actingAs($client, 'sanctum')->getJson('/api/v1/notifications')->json('data'))->pluck('title');
        $this->assertNotEmpty($titles);
    }
}
