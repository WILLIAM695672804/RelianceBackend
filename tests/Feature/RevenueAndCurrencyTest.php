<?php

namespace Tests\Feature;

use App\Modules\Finance\Models\ExchangeRate;
use App\Modules\Finance\Models\PlatformFee;
use App\Modules\Finance\Models\PlatformRevenue;
use App\Modules\Finance\Models\SubscriptionPlan;
use App\Modules\Finance\Models\Wallet;
use App\Modules\Finance\Services\EscrowService;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_un_abonnement_est_un_revenu_integral_de_la_plateforme(): void
    {
        $tech = $this->makeUser('technicien');
        $this->fund($tech, 10000);
        $plan = SubscriptionPlan::create(['name' => 'Pro', 'slug' => 'pro', 'price_monthly' => 5000, 'price_yearly' => 50000, 'currency' => 'XAF', 'features' => []]);

        $this->actingAs($tech, 'sanctum')->postJson('/api/v1/subscriptions/subscribe', ['plan_id' => $plan->id, 'billing_cycle' => 'monthly'])->assertSuccessful();

        $this->assertEqualsWithDelta(5000, (float) PlatformRevenue::where('source', 'subscription')->sum('amount'), 0.01);
    }

    public function test_le_tableau_des_revenus_est_reserve_a_l_administrateur(): void
    {
        PlatformRevenue::create(['source' => 'job_commission', 'amount' => 1500, 'currency' => 'XAF']);
        PlatformRevenue::create(['source' => 'subscription', 'amount' => 5000, 'currency' => 'XAF']);

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

    // ── Portefeuilles multi-devises ────────────────────────

    public function test_chaque_utilisateur_dispose_d_un_portefeuille_par_devise(): void
    {
        $user = $this->makeUser('client');

        $devises = collect($this->actingAs($user, 'sanctum')->getJson('/api/v1/wallets')->assertOk()->json('data'))->pluck('currency')->sort()->values()->all();

        $this->assertSame(['EUR', 'USD', 'XAF'], $devises);
        $this->assertSame(3, Wallet::where('user_id', $user->id)->count());
    }

    public function test_les_operations_courantes_restent_sur_le_portefeuille_xaf(): void
    {
        $user = $this->makeUser('client');
        $this->fund($user, 7000);
        $this->actingAs($user, 'sanctum')->getJson('/api/v1/wallets')->assertOk(); // crée EUR et USD

        $this->assertEqualsWithDelta(7000, (float) $this->actingAs($user, 'sanctum')->getJson('/api/v1/wallet')->json('data.balance'), 0.01);
    }

    public function test_conversion_xaf_vers_euro_au_taux_de_l_administrateur(): void
    {
        $this->taux('EUR', 655);
        $user = $this->makeUser('client');
        $this->fund($user, 65500);

        $r = $this->actingAs($user, 'sanctum')->postJson('/api/v1/wallets/convert', ['amount' => 65500, 'from_currency' => 'XAF', 'to_currency' => 'EUR'])->assertOk();

        $this->assertEqualsWithDelta(100, $r->json('data.credited'), 0.01);
        $this->assertEqualsWithDelta(0, $this->balanceOf($user), 0.01);
        $this->assertEqualsWithDelta(100, (float) Wallet::where('user_id', $user->id)->where('currency', 'EUR')->value('balance'), 0.01);
    }

    public function test_conversion_entre_deux_devises_etrangeres_passe_par_le_xaf(): void
    {
        $this->taux('EUR', 650);
        $this->taux('USD', 600);
        $user = $this->makeUser('client');
        $this->actingAs($user, 'sanctum')->getJson('/api/v1/wallets');
        Wallet::where('user_id', $user->id)->where('currency', 'EUR')->update(['balance' => 60]);

        $r = $this->actingAs($user, 'sanctum')->postJson('/api/v1/wallets/convert', ['amount' => 60, 'from_currency' => 'EUR', 'to_currency' => 'USD'])->assertOk();

        $this->assertEqualsWithDelta(65, $r->json('data.credited'), 0.01); // 60 × 650 / 600
    }

    public function test_conversion_refusee_sans_solde_sans_taux_ou_vers_la_meme_devise(): void
    {
        $user = $this->makeUser('client');
        $this->fund($user, 1000);
        $convertir = fn (array $d) => $this->actingAs($user, 'sanctum')->postJson('/api/v1/wallets/convert', $d);

        $convertir(['amount' => 500, 'from_currency' => 'XAF', 'to_currency' => 'EUR'])->assertStatus(422);       // aucun taux
        $this->taux('EUR', 655);
        $convertir(['amount' => 50000, 'from_currency' => 'XAF', 'to_currency' => 'EUR'])->assertStatus(422);     // solde insuffisant
        $convertir(['amount' => 500, 'from_currency' => 'XAF', 'to_currency' => 'XAF'])->assertStatus(422);       // même devise
        $convertir(['amount' => 500, 'from_currency' => 'XAF', 'to_currency' => 'GBP'])->assertStatus(422);       // devise inconnue
        $this->assertEqualsWithDelta(1000, $this->balanceOf($user), 0.01);
    }

    public function test_un_administrateur_n_a_pas_de_portefeuille(): void
    {
        $this->actingAs($this->makeUser('admin'), 'sanctum')->getJson('/api/v1/wallets')->assertForbidden();
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
