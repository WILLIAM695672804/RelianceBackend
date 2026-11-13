<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsWorld;
use Tests\TestCase;

class TwoFactorEnforcementTest extends TestCase
{
    use RefreshDatabase, BuildsWorld;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    private function actionsFinancieres(): array
    {
        $id = '00000000-0000-0000-0000-000000000000';

        return [
            ['post', '/api/v1/wallet/recharge', ['amount' => 1000, 'method' => 'bank_transfer']],
            ['post', '/api/v1/wallet/withdraw', ['amount' => 1000, 'method' => 'bank_transfer']],
            ['post', '/api/v1/wallets/convert', ['amount' => 1, 'from_currency' => 'XAF', 'to_currency' => 'EUR']],
            ['post', "/api/v1/invoices/{$id}/pay", []],
            ['post', "/api/v1/diagnostics/{$id}/pay", []],
            ['post', '/api/v1/credits/purchase', []],
        ];
    }

    public function test_par_defaut_l_exigence_est_desactivee(): void
    {
        $client = $this->makeUser('client');

        foreach ($this->actionsFinancieres() as [$m, $uri, $body]) {
            $reponse = $this->actingAs($client, 'sanctum')->json($m, $uri, $body);
            $this->assertNotTrue((bool) $reponse->json('requires_2fa_setup'), "{$uri} ne doit pas exiger la 2FA par défaut.");
        }
        $this->assertFalse($this->actingAs($client, 'sanctum')->getJson('/api/v1/auth/me')->json('requires_2fa_setup'));
    }

    public function test_un_client_sans_2fa_est_bloque_sur_chaque_action_financiere(): void
    {
        config(['reliance.require_2fa_clients' => true]);
        $client = $this->makeUser('client');

        foreach ($this->actionsFinancieres() as [$m, $uri, $body]) {
            $this->actingAs($client, 'sanctum')->json($m, $uri, $body)->assertForbidden()->assertJsonPath('requires_2fa_setup', true);
        }
        $this->assertTrue($this->actingAs($client, 'sanctum')->getJson('/api/v1/auth/me')->json('requires_2fa_setup'));
    }

    public function test_un_client_avec_2fa_active_n_est_pas_bloque(): void
    {
        config(['reliance.require_2fa_clients' => true]);
        $client = $this->makeUser('client', ['two_factor_enabled' => true]);
        $this->fund($client, 0);

        $this->actingAs($client, 'sanctum')->postJson('/api/v1/wallet/recharge', ['amount' => 1000, 'method' => 'bank_transfer'])->assertSuccessful();
        $this->assertFalse($this->actingAs($client, 'sanctum')->getJson('/api/v1/auth/me')->json('requires_2fa_setup'));
    }

    public function test_les_autres_roles_ne_sont_pas_concernes(): void
    {
        config(['reliance.require_2fa_clients' => true]);
        $tech = $this->makeUser('technicien');
        $this->fund($tech, 10000);

        $this->actingAs($tech, 'sanctum')->postJson('/api/v1/wallet/withdraw', ['amount' => 1000, 'method' => 'bank_transfer'])->assertSuccessful();
        $this->assertFalse($this->actingAs($tech, 'sanctum')->getJson('/api/v1/auth/me')->json('requires_2fa_setup'));
    }

    public function test_les_lectures_restent_possibles_et_la_connexion_signale_l_activation(): void
    {
        config(['reliance.require_2fa_clients' => true]);
        $client = $this->makeUser('client');

        $this->actingAs($client, 'sanctum')->getJson('/api/v1/wallet')->assertOk();
        $this->actingAs($client, 'sanctum')->postJson('/api/v1/auth/2fa/generate')->assertSuccessful(); // l'activation reste accessible

        $this->postJson('/api/v1/auth/login', ['email' => $client->email, 'password' => 'Passw0rd!123'])
            ->assertOk()->assertJsonPath('requires_2fa_setup', true);
    }
}
