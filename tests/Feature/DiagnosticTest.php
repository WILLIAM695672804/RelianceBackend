<?php

namespace Tests\Feature;

use App\Modules\Finance\Models\Diagnostic;
use App\Modules\Finance\Models\PlatformFee;
use App\Modules\Finance\Models\PlatformRevenue;
use App\Modules\Notification\Models\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsWorld;
use Tests\TestCase;

class DiagnosticTest extends TestCase
{
    use RefreshDatabase, BuildsWorld;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    private function mission(): array
    {
        $client = $this->makeUser('client');
        $tech = $this->makeTechnician();
        [$request] = $this->assignedMission($client, $tech);

        return [$client, $tech, $request];
    }

    public function test_le_technicien_mandate_fixe_les_frais_et_le_client_est_notifie(): void
    {
        [$client, $tech, $request] = $this->mission();

        $r = $this->actingAs($tech, 'sanctum')->postJson("/api/v1/requests/{$request->id}/diagnostic", ['amount' => 5000])->assertStatus(201);

        $this->assertEqualsWithDelta(5000, $r->json('data.amount'), 0.01);
        $this->assertFalse($r->json('data.paid'));
        $this->assertSame(1, Notification::where('user_id', $client->id)->where('title', 'Frais de diagnostic')->count());
    }

    public function test_seul_le_technicien_mandate_peut_fixer_les_frais_une_seule_fois(): void
    {
        [$client, $tech, $request] = $this->mission();
        $autre = $this->makeTechnician();
        $url = "/api/v1/requests/{$request->id}/diagnostic";

        $this->actingAs($client, 'sanctum')->postJson($url, ['amount' => 5000])->assertForbidden();
        $this->actingAs($autre, 'sanctum')->postJson($url, ['amount' => 5000])->assertForbidden();
        $this->actingAs($tech, 'sanctum')->postJson($url, ['amount' => 50])->assertStatus(422);
        $this->actingAs($tech, 'sanctum')->postJson($url, ['amount' => 5000])->assertStatus(201);
        $this->actingAs($tech, 'sanctum')->postJson($url, ['amount' => 6000])->assertStatus(409);
        $this->assertSame(1, Diagnostic::count());
    }

    public function test_lecture_limitee_aux_parties_et_a_l_administrateur(): void
    {
        [$client, $tech, $request] = $this->mission();
        $this->actingAs($tech, 'sanctum')->postJson("/api/v1/requests/{$request->id}/diagnostic", ['amount' => 5000]);
        $url = "/api/v1/requests/{$request->id}/diagnostic";

        $this->actingAs($client, 'sanctum')->getJson($url)->assertOk()->assertJsonPath('data.amount', 5000);
        $this->actingAs($tech, 'sanctum')->getJson($url)->assertOk();
        $this->actingAs($this->makeUser('admin'), 'sanctum')->getJson($url)->assertOk();
        $this->actingAs($this->makeUser('client'), 'sanctum')->getJson($url)->assertForbidden();
    }

    public function test_sans_frais_la_lecture_renvoie_null(): void
    {
        [$client, , $request] = $this->mission();

        $this->actingAs($client, 'sanctum')->getJson("/api/v1/requests/{$request->id}/diagnostic")->assertOk()->assertJsonPath('data', null);
    }

    public function test_le_paiement_debite_le_client_credite_le_technicien_et_journalise_la_commission(): void
    {
        PlatformFee::create(['name' => 'Commission diagnostic', 'fee_type' => 'percentage', 'value' => 0.10, 'applies_to' => 'diagnostic', 'active' => true]);
        [$client, $tech, $request] = $this->mission();
        $this->fund($client, 20000);
        $this->fund($tech, 0);
        $id = $this->actingAs($tech, 'sanctum')->postJson("/api/v1/requests/{$request->id}/diagnostic", ['amount' => 5000])->json('data.id');

        $this->actingAs($client, 'sanctum')->postJson("/api/v1/diagnostics/{$id}/pay")->assertOk()->assertJsonPath('data.paid', true);

        $this->assertEqualsWithDelta(15000, $this->balanceOf($client), 0.01);
        $this->assertEqualsWithDelta(4500, $this->balanceOf($tech), 0.01);
        $this->assertEqualsWithDelta(500, (float) PlatformRevenue::where('source', 'diagnostic_commission')->sum('amount'), 0.01);
        $this->assertNotNull(Diagnostic::findOrFail($id)->payment_id);
    }

    public function test_le_paiement_est_idempotent_et_refuse_sans_fonds_ou_pour_un_tiers(): void
    {
        [$client, $tech, $request] = $this->mission();
        $id = $this->actingAs($tech, 'sanctum')->postJson("/api/v1/requests/{$request->id}/diagnostic", ['amount' => 5000])->json('data.id');

        $this->actingAs($client, 'sanctum')->postJson("/api/v1/diagnostics/{$id}/pay")->assertStatus(402);
        $this->actingAs($this->makeUser('client'), 'sanctum')->postJson("/api/v1/diagnostics/{$id}/pay")->assertForbidden();
        $this->assertFalse((bool) Diagnostic::findOrFail($id)->paid);

        $this->fund($client, 20000);
        $this->fund($tech, 0);
        $this->actingAs($client, 'sanctum')->postJson("/api/v1/diagnostics/{$id}/pay")->assertOk();
        $this->actingAs($client, 'sanctum')->postJson("/api/v1/diagnostics/{$id}/pay")->assertStatus(409);
        $this->assertEqualsWithDelta(15000, $this->balanceOf($client), 0.01, 'Aucun double débit.');
    }
}
