<?php

namespace Tests\Feature;

use App\Modules\Auth\Models\User;
use App\Modules\Finance\Models\Hold;
use App\Modules\Finance\Models\Payment;
use App\Modules\Finance\Models\PlatformFee;
use App\Modules\Finance\Models\SubscriptionPlan;
use App\Modules\Finance\Models\Transaction;
use App\Modules\Finance\Services\EscrowService;
use App\Modules\Finance\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\BuildsWorld;
use Tests\TestCase;

class FinanceTest extends TestCase
{
    use RefreshDatabase, BuildsWorld;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    private function pendingPayment(User $payer, string $method = 'bank_transfer', float $amount = 5000, string $ref = null): Payment
    {
        return Payment::create([
            'payer_id' => $payer->id, 'payee_id' => null, 'method' => $method, 'amount' => $amount,
            'currency' => 'XAF', 'status' => 'pending', 'external_ref' => $ref ?? 'ref-' . uniqid(),
            'metadata' => ['type' => 'wallet_recharge'],
        ]);
    }

    // ── WalletService ──────────────────────────────────────

    public function test_settle_recharge_credite_une_seule_fois(): void
    {
        $user = $this->makeUser('client');
        $this->fund($user, 0);
        $payment = $this->pendingPayment($user, 'momo', 5000);
        $service = app(WalletService::class);

        $first = $service->settleRecharge($payment->id);
        $second = $service->settleRecharge($payment->id);

        $this->assertSame('settled', $first->status);
        $this->assertNull($second, 'Un paiement déjà réglé ne doit pas être recrédité.');
        $this->assertEqualsWithDelta(5000, $this->balanceOf($user), 0.01);
        $this->assertSame(1, Transaction::where('related_payment', $payment->id)->count());
    }

    public function test_settle_recharge_ignore_un_paiement_inconnu(): void
    {
        $this->assertNull(app(WalletService::class)->settleRecharge((string) \Illuminate\Support\Str::uuid()));
    }

    public function test_charge_platform_debite_et_trace_la_transaction(): void
    {
        $user = $this->makeUser('technicien');
        $this->fund($user, 10000);

        $payment = app(WalletService::class)->chargePlatform($user->id, 4000, 'subscription', ['plan' => 'pro']);

        $this->assertSame('settled', $payment->status);
        $this->assertEqualsWithDelta(6000, $this->balanceOf($user), 0.01);
        $this->assertSame(-4000.0, (float) Transaction::where('related_payment', $payment->id)->value('amount'));
    }

    public function test_charge_platform_refuse_un_solde_insuffisant_sans_effet_de_bord(): void
    {
        $user = $this->makeUser('technicien');
        $this->fund($user, 1000);

        try {
            app(WalletService::class)->chargePlatform($user->id, 4000, 'subscription');
            $this->fail('DomainException attendue.');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('Solde insuffisant', $e->getMessage());
        }

        $this->assertEqualsWithDelta(1000, $this->balanceOf($user), 0.01);
        $this->assertSame(0, Payment::count());
    }

    // ── EscrowService ──────────────────────────────────────

    public function test_hold_reduit_le_solde_disponible_sans_toucher_au_solde(): void
    {
        $user = $this->makeUser('client');
        $wallet = $this->fund($user, 10000);

        app(EscrowService::class)->placeHold($user->id, 7000, 'test');

        $this->assertEqualsWithDelta(3000, $wallet->fresh()->availableBalance(), 0.01);
        $this->assertEqualsWithDelta(10000, $this->balanceOf($user), 0.01);

        $this->expectException(\DomainException::class);
        app(EscrowService::class)->placeHold($user->id, 4000, 'excès');
    }

    public function test_capture_deduit_les_frais_actifs_du_beneficiaire(): void
    {
        $client = $this->makeUser('client');
        $tech = $this->makeUser('technicien');
        $this->fund($client, 10000);
        $this->fund($tech, 0);
        PlatformFee::create([
            'name' => 'Commission', 'fee_type' => 'percentage', 'value' => 0.10,
            'applies_to' => 'job', 'active' => true,
        ]);
        $escrow = app(EscrowService::class);
        $hold = $escrow->placeHold($client->id, 10000, 'invoice:x');

        $escrow->captureHold($hold->id, $tech->id, 'wallet');

        $this->assertEqualsWithDelta(0, $this->balanceOf($client), 0.01);
        $this->assertEqualsWithDelta(9000, $this->balanceOf($tech), 0.01);
        $this->assertSame('captured', $hold->fresh()->status);
    }

    public function test_un_hold_ne_peut_etre_capture_ni_libere_deux_fois(): void
    {
        $client = $this->makeUser('client');
        $tech = $this->makeUser('technicien');
        $this->fund($client, 5000);
        $this->fund($tech, 0);
        $escrow = app(EscrowService::class);
        $hold = $escrow->placeHold($client->id, 5000, 'x');
        $escrow->captureHold($hold->id, $tech->id, 'wallet');

        try {
            $escrow->captureHold($hold->id, $tech->id, 'wallet');
            $this->fail('Seconde capture acceptée.');
        } catch (\DomainException $e) {
            $this->assertEqualsWithDelta(5000, $this->balanceOf($tech), 0.01);
        }

        $this->expectException(\DomainException::class);
        $escrow->releaseHold($hold->id);
    }

    public function test_api_holds_reserve_au_proprietaire_du_portefeuille(): void
    {
        $owner = $this->makeUser('client');
        $intruder = $this->makeUser('client');
        $tech = $this->makeUser('technicien');
        $wallet = $this->fund($owner, 5000);
        $hold = Hold::create(['wallet_id' => $wallet->id, 'amount' => 1000, 'reason' => 'x', 'status' => 'held']);

        $this->actingAs($intruder, 'sanctum')
            ->postJson("/api/v1/holds/{$hold->id}/capture", ['payee_id' => $tech->id, 'method' => 'wallet'])->assertForbidden();
        $this->actingAs($intruder, 'sanctum')->postJson("/api/v1/holds/{$hold->id}/release")->assertForbidden();
        $this->actingAs($owner, 'sanctum')->postJson("/api/v1/holds/{$hold->id}/release")->assertSuccessful();
    }

    // ── Confirmation de paiement ───────────────────────────

    public function test_confirm_refuse_un_tiers(): void
    {
        $payer = $this->makeUser('client');
        $this->fund($payer, 0);
        $payment = $this->pendingPayment($payer);

        $this->actingAs($this->makeUser('technicien'), 'sanctum')
            ->postJson("/api/v1/payments/{$payment->id}/confirm")->assertForbidden();
        $this->assertEqualsWithDelta(0, $this->balanceOf($payer), 0.01);
    }

    public function test_confirm_d_un_virement_est_reserve_a_l_administrateur(): void
    {
        $payer = $this->makeUser('client');
        $admin = $this->makeUser('admin');
        $this->fund($payer, 0);
        $payment = $this->pendingPayment($payer, 'bank_transfer', 3000);

        $this->actingAs($payer, 'sanctum')->postJson("/api/v1/payments/{$payment->id}/confirm")->assertForbidden();
        $this->assertEqualsWithDelta(0, $this->balanceOf($payer), 0.01);

        $this->actingAs($admin, 'sanctum')->postJson("/api/v1/payments/{$payment->id}/confirm")->assertSuccessful();
        $this->assertEqualsWithDelta(3000, $this->balanceOf($payer), 0.01);

        // Idempotence : seconde confirmation refusée, aucun recrédit.
        $this->actingAs($admin, 'sanctum')->postJson("/api/v1/payments/{$payment->id}/confirm")->assertStatus(409);
        $this->assertEqualsWithDelta(3000, $this->balanceOf($payer), 0.01);
    }

    public function test_confirm_mobile_money_attend_la_confirmation_de_la_passerelle(): void
    {
        $payer = $this->makeUser('client');
        $this->fund($payer, 0);
        $payment = $this->pendingPayment($payer, 'momo', 2000, 'np-1');
        Http::fake(['*' => Http::response(['transaction' => ['status' => 'pending']])]);

        $this->actingAs($payer, 'sanctum')->postJson("/api/v1/payments/{$payment->id}/confirm")->assertStatus(202);

        $this->assertEqualsWithDelta(0, $this->balanceOf($payer), 0.01);
        $this->assertSame('pending', $payment->fresh()->status);
    }

    public function test_confirm_mobile_money_credite_apres_confirmation_de_la_passerelle(): void
    {
        $payer = $this->makeUser('client');
        $this->fund($payer, 0);
        $payment = $this->pendingPayment($payer, 'momo', 2000, 'np-1');
        Http::fake(['*' => Http::response(['transaction' => ['status' => 'complete']])]);

        $this->actingAs($payer, 'sanctum')->postJson("/api/v1/payments/{$payment->id}/confirm")->assertStatus(200);

        $this->assertEqualsWithDelta(2000, $this->balanceOf($payer), 0.01);
    }

    public function test_confirm_signale_une_passerelle_injoignable(): void
    {
        $payer = $this->makeUser('client');
        $this->fund($payer, 0);
        $payment = $this->pendingPayment($payer, 'momo', 2000, 'np-2');
        Http::fake(fn () => throw new \RuntimeException('réseau'));

        $this->actingAs($payer, 'sanctum')->postJson("/api/v1/payments/{$payment->id}/confirm")->assertStatus(502);
        $this->assertEqualsWithDelta(0, $this->balanceOf($payer), 0.01);
    }

    // ── Webhook NotchPay ───────────────────────────────────

    public function test_webhook_signature_invalide_est_rejete(): void
    {
        config(['services.notchpay.hash_key' => 'secret']);

        $this->postJson('/api/v1/webhooks/notchpay', ['reference' => 'x'], ['x-notch-signature' => 'faux'])
            ->assertStatus(401);
    }

    public function test_webhook_valide_credite_une_seule_fois(): void
    {
        config(['services.notchpay.hash_key' => 'secret']);
        $payer = $this->makeUser('client');
        $this->fund($payer, 0);
        $this->pendingPayment($payer, 'momo', 4000, 'np-hook');
        Http::fake(['*' => Http::response(['transaction' => ['status' => 'complete']])]);

        $body = json_encode(['data' => ['reference' => 'np-hook', 'channel' => 'cm.mtn']]);
        $headers = [
            'x-notch-signature' => hash_hmac('sha256', $body, 'secret'),
            'CONTENT_TYPE' => 'application/json',
        ];

        $call = fn () => $this->call('POST', '/api/v1/webhooks/notchpay', [], [], [], $this->transformHeadersToServerVars($headers), $body);

        $call()->assertSuccessful();
        $call()->assertStatus(404);
        $this->assertEqualsWithDelta(4000, $this->balanceOf($payer), 0.01);
    }

    public function test_webhook_sans_reference_renvoie_400(): void
    {
        config(['services.notchpay.hash_key' => null]);
        $this->postJson('/api/v1/webhooks/notchpay', [])->assertStatus(400);
    }

    // ── Recharge ───────────────────────────────────────────

    public function test_recharge_virement_renvoie_des_instructions_et_un_paiement_en_attente(): void
    {
        $user = $this->makeUser('client');
        $this->fund($user, 0);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/wallet/recharge', ['amount' => 1000, 'method' => 'bank_transfer'])->assertSuccessful();

        $this->assertNotEmpty($response->json('instructions'));
        $this->assertSame('pending', Payment::findOrFail($response->json('payment_id'))->status);
        $this->assertEqualsWithDelta(0, $this->balanceOf($user), 0.01);
    }

    // ── Abonnement ─────────────────────────────────────────

    private function plan(): SubscriptionPlan
    {
        return SubscriptionPlan::create([
            'name' => 'Pro', 'slug' => 'pro', 'price_monthly' => 5000, 'price_yearly' => 50000,
            'currency' => 'XAF', 'features' => ['Référencement'],
        ]);
    }

    public function test_abonnement_sans_fonds_renvoie_402_et_ne_cree_rien(): void
    {
        $tech = $this->makeUser('technicien');
        $this->fund($tech, 0);
        $plan = $this->plan();

        $this->actingAs($tech, 'sanctum')
            ->postJson('/api/v1/subscriptions/subscribe', ['plan_id' => $plan->id, 'billing_cycle' => 'monthly'])
            ->assertStatus(402);

        $this->assertSame(0, \App\Modules\Finance\Models\Subscription::count());
    }

    public function test_abonnement_avec_fonds_debite_et_refuse_le_doublon(): void
    {
        $tech = $this->makeUser('technicien');
        $this->fund($tech, 100000);
        $plan = $this->plan();
        $payload = ['plan_id' => $plan->id, 'billing_cycle' => 'monthly'];

        $this->actingAs($tech, 'sanctum')->postJson('/api/v1/subscriptions/subscribe', $payload)->assertSuccessful();
        $this->assertEqualsWithDelta(95000, $this->balanceOf($tech), 0.01);

        $this->actingAs($tech, 'sanctum')->postJson('/api/v1/subscriptions/subscribe', $payload)->assertStatus(422);
        $this->assertEqualsWithDelta(95000, $this->balanceOf($tech), 0.01);
    }

    public function test_creation_de_plan_reservee_a_l_administrateur(): void
    {
        $payload = ['name' => 'Pro', 'slug' => 'pro-x', 'price_monthly' => 1, 'price_yearly' => 2];

        $this->actingAs($this->makeUser('technicien'), 'sanctum')->postJson('/api/v1/admin/subscriptions/plans', $payload)->assertForbidden();
        $this->actingAs($this->makeUser('admin'), 'sanctum')->postJson('/api/v1/admin/subscriptions/plans', $payload)->assertStatus(201);
    }
}
