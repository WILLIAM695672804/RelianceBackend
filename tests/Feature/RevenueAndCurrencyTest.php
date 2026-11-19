<?php

namespace Tests\Feature;

use App\Modules\Finance\Models\ExchangeRate;
use App\Modules\Finance\Models\PlatformFee;
use App\Modules\Finance\Models\PlatformRevenue;
use App\Modules\Finance\Models\Wallet;
use App\Modules\Finance\Services\EscrowService;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\BuildsWorld;
use Tests\TestCase;

class RevenueAndCurrencyTest extends TestCase
{
    use RefreshDatabase, BuildsWorld;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    private function taux(string $devise, float $xaf): void
    {
        ExchangeRate::create(['from_currency' => $devise, 'to_currency' => 'XAF', 'rate' => $xaf, 'active' => true]);
    }

    // ── Journal des revenus ────────────────────────────────

    public function test_la_commission_d_une_capture_est_inscrite_au_journal_des_revenus(): void
    {
        $client = $this->makeUser('client');
        $tech = $this->makeUser('technicien');
        $this->fund($client, 20000);
        $this->fund($tech, 0);
        PlatformFee::create(['name' => 'Commission', 'fee_type' => 'percentage', 'value' => 0.10, 'applies_to' => 'job', 'active' => true]);
        $escrow = app(EscrowService::class);

        $escrow->captureHold($escrow->placeHold($client->id, 20000, 'x')->id, $tech->id, 'wallet');

        $this->assertEqualsWithDelta(2000, (float) PlatformRevenue::where('source', 'job_commission')->sum('amount'), 0.01);
        $this->assertEqualsWithDelta(18000, $this->balanceOf($tech), 0.01);
    }

    public function test_sans_frais_actif_aucun_revenu_n_est_inscrit(): void
    {
        $client = $this->makeUser('client');
        $tech = $this->makeUser('technicien');
        $this->fund($client, 5000);
        $this->fund($tech, 0);
        $escrow = app(EscrowService::class);

        $escrow->captureHold($escrow->placeHold($client->id, 5000, 'x')->id, $tech->id, 'wallet');

        $this->assertSame(0, PlatformRevenue::count());
    }

    public function test_le_tableau_des_revenus_est_reserve_a_l_administrateur(): void
    {
        PlatformRevenue::create(['source' => 'job_commission', 'amount' => 1500, 'currency' => 'XAF']);
        PlatformRevenue::create(['source' => 'diagnostic_commission', 'amount' => 5000, 'currency' => 'XAF']);

        $this->actingAs($this->makeUser('technicien'), 'sanctum')->getJson('/api/v1/admin/revenue')->assertForbidden();
        $data = $this->actingAs($this->makeUser('admin'), 'sanctum')->getJson('/api/v1/admin/revenue')->assertOk()->json('data');

        $this->assertEqualsWithDelta(6500, $data['total'], 0.01);
        $this->assertCount(2, $data['by_source']);
        $this->assertSame(now()->format('Y-m'), $data['by_month'][0]['mois']);
    }

    // ── Frais d'intervention visibles ──────────────────────

    public function test_la_facture_mentionne_les_frais_d_intervention_et_le_net_du_technicien(): void
    {
        PlatformFee::create(['name' => 'Commission', 'fee_type' => 'percentage', 'value' => 0.10, 'applies_to' => 'job', 'active' => true]);
        $client = $this->makeUser('client');
        $tech = $this->makeTechnician();
        [$request] = $this->assignedMission($client, $tech);
        $quoteId = $this->sentQuote($tech, $request, 12000);
        $invoiceId = $this->actingAs($client, 'sanctum')->postJson("/api/v1/quotes/{$quoteId}/accept")->json('data.invoice_id');

        $facture = $this->actingAs($client, 'sanctum')->getJson("/api/v1/invoices/{$invoiceId}")->assertOk()->json('data');

        $this->assertEqualsWithDelta(1200, $facture['platform_fee'], 0.01);
        $this->assertEqualsWithDelta(10800, $facture['net_to_issuer'], 0.01);
    }

    // ── Portefeuille unique, équivalents en devises ────────
    // Cahier des charges : un seul portefeuille par utilisateur, en XAF ; l'achat de crédit dans une
    // autre devise est converti au taux de l'administrateur (§4.4). Les portefeuilles par devise et la
    // conversion entre eux (ajoutés le 2026-11-13, hors cahier) ont été retirés.

    public function test_chaque_utilisateur_n_a_qu_un_portefeuille_en_xaf(): void
    {
        $user = $this->makeUser('client');

        $this->actingAs($user, 'sanctum')->getJson('/api/v1/wallet')->assertOk()->assertJsonPath('data.currency', 'XAF');

        $this->assertSame(['XAF'], Wallet::where('user_id', $user->id)->pluck('currency')->all());
    }

    public function test_un_second_portefeuille_est_refuse_par_la_base(): void
    {
        $user = $this->makeUser('client');

        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);
        Wallet::create(['user_id' => $user->id, 'currency' => 'EUR', 'balance' => 0]);
    }

    public function test_les_routes_de_portefeuilles_par_devise_n_existent_plus(): void
    {
        $user = $this->makeUser('client');
        $this->fund($user, 1000);

        $this->actingAs($user, 'sanctum')->getJson('/api/v1/wallets')->assertNotFound();
        $this->actingAs($user, 'sanctum')->postJson('/api/v1/wallets/convert', ['amount' => 500, 'from_currency' => 'XAF', 'to_currency' => 'EUR'])->assertNotFound();
        $this->assertEqualsWithDelta(1000, $this->balanceOf($user), 0.01);
    }

    public function test_l_achat_de_credit_en_euros_est_converti_en_xaf_au_taux_de_l_administrateur(): void
    {
        $this->taux('EUR', 655.957);
        $user = $this->makeUser('client');
        $this->fund($user, 0);
        Http::fake(['*' => Http::response(['transaction' => ['reference' => 'np-credit', 'status' => 'pending']])]);

        $this->actingAs($user, 'sanctum')->postJson('/api/v1/credits/purchase', [
            'amount' => 100, 'from_currency' => 'EUR', 'method' => 'card',
        ])->assertSuccessful();

        $paiement = \App\Modules\Finance\Models\Payment::where('payer_id', $user->id)->sole();
        $this->assertSame('XAF', $paiement->currency);
        $this->assertEqualsWithDelta(65596, (float) $paiement->amount, 0.01); // 100 × 655,957, arrondi au franc
        $this->assertSame('pending', $paiement->status);                       // crédité seulement à la confirmation
        $this->assertSame(1, Wallet::where('user_id', $user->id)->count());
    }

    public function test_un_administrateur_n_a_pas_de_portefeuille(): void
    {
        $this->actingAs($this->makeUser('admin'), 'sanctum')->getJson('/api/v1/wallet')->assertForbidden();
    }

    // ── Modification de l'e-mail ───────────────────────────

    public function test_le_changement_d_email_reinitialise_la_verification_et_renvoie_un_lien(): void
    {
        Notification::fake();
        $client = $this->makeUser('client');

        $this->actingAs($client, 'sanctum')->putJson('/api/v1/auth/profile', ['email' => 'nouveau@test.local'])->assertSuccessful();

        $this->assertSame('nouveau@test.local', $client->fresh()->email);
        $this->assertNull($client->fresh()->email_verified_at);
        Notification::assertSentTo($client->fresh(), \App\Notifications\VerifyEmailNotification::class);
    }

    public function test_le_changement_d_email_refuse_une_adresse_deja_utilisee(): void
    {
        $autre = $this->makeUser('client');
        $client = $this->makeUser('client');

        $this->actingAs($client, 'sanctum')->putJson('/api/v1/auth/profile', ['email' => $autre->email])->assertStatus(422);
    }

    public function test_un_echec_d_envoi_du_lien_n_empeche_pas_la_modification(): void
    {
        $client = $this->makeUser('client');
        Notification::shouldReceive('send')->andThrow(new \RuntimeException('SMTP indisponible'));
        Notification::shouldReceive('sendNow')->andThrow(new \RuntimeException('SMTP indisponible'));

        $this->actingAs($client, 'sanctum')->putJson('/api/v1/auth/profile', ['email' => 'autre@test.local'])->assertSuccessful();

        $this->assertSame('autre@test.local', $client->fresh()->email);
    }
}
