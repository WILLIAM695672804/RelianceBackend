<?php

namespace Tests\Feature;

use App\Modules\Notification\Models\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsWorld;
use Tests\TestCase;

class AdminUserTest extends TestCase
{
    use RefreshDatabase, BuildsWorld;

    private $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->admin = $this->makeUser('admin');
    }

    private function asAdmin()
    {
        return $this->actingAs($this->admin, 'sanctum');
    }

    // ── Liste et filtres ───────────────────────────────────

    public function test_la_liste_se_filtre_par_role_statut_et_recherche(): void
    {
        $alice = $this->makeUser('client', ['name' => 'Alice Dupont']);
        $this->makeUser('client', ['name' => 'Bob Martin', 'is_active' => false]);
        $this->makeUser('technicien', ['name' => 'Paul Plombier']);

        $noms = fn (string $q) => collect($this->asAdmin()->getJson("/api/v1/auth/admin/users{$q}")->assertOk()->json('data'))->pluck('name')->all();

        $this->assertEqualsCanonicalizing(['Alice Dupont', 'Bob Martin'], array_values(array_intersect($noms('?role=client'), ['Alice Dupont', 'Bob Martin', 'Paul Plombier'])));
        $this->assertContains('Bob Martin', $noms('?status=inactive'));
        $this->assertNotContains('Alice Dupont', $noms('?status=inactive'));
        $this->assertSame(['Paul Plombier'], array_values(array_intersect($noms('?search=plomb'), ['Paul Plombier', 'Alice Dupont'])));
        $this->assertSame($alice->id, $this->asAdmin()->getJson("/api/v1/auth/admin/users/{$alice->id}")->json('data.id'));
    }

    public function test_le_detail_n_expose_pas_le_mot_de_passe(): void
    {
        $user = $this->makeUser('client');

        $json = $this->asAdmin()->getJson("/api/v1/auth/admin/users/{$user->id}")->assertOk()->json('data');

        $this->assertArrayNotHasKey('password', $json);
        $this->assertArrayNotHasKey('two_factor_secret', $json);
    }

    public function test_activation_et_desactivation_d_un_compte(): void
    {
        $user = $this->makeUser('client');

        $this->asAdmin()->patchJson("/api/v1/auth/admin/users/{$user->id}/toggle-active")->assertOk()->assertJsonPath('is_active', false);
        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'Passw0rd!123'])->assertStatus(403);

        $this->asAdmin()->patchJson("/api/v1/auth/admin/users/{$user->id}/toggle-active")->assertOk()->assertJsonPath('is_active', true);
    }

    // ── Approbation des techniciens ────────────────────────

    public function test_seuls_les_techniciens_non_verifies_et_actifs_sont_en_attente(): void
    {
        $attente = $this->makeUser('technicien', ['verified' => false]);
        $this->makeUser('technicien', ['verified' => true]);
        $this->makeUser('technicien', ['verified' => false, 'is_active' => false]);
        $this->makeUser('client', ['verified' => false]);

        $ids = collect($this->asAdmin()->getJson('/api/v1/auth/admin/technicians/pending')->assertOk()->json('data'))->pluck('id')->all();

        $this->assertSame([$attente->id], $ids);
    }

    public function test_l_approbation_active_et_verifie_le_technicien(): void
    {
        $tech = $this->makeUser('technicien', ['verified' => false, 'is_active' => false]);

        $this->asAdmin()->postJson("/api/v1/auth/admin/technicians/{$tech->id}/approve")->assertOk();

        $this->assertTrue((bool) $tech->fresh()->verified);
        $this->assertTrue((bool) $tech->fresh()->is_active);
    }

    public function test_l_approbation_d_un_non_technicien_est_un_404(): void
    {
        $client = $this->makeUser('client');

        $this->asAdmin()->postJson("/api/v1/auth/admin/technicians/{$client->id}/approve")->assertNotFound();
    }

    public function test_le_rejet_exige_un_motif_desactive_le_compte_et_le_retire_de_la_file(): void
    {
        $tech = $this->makeUser('technicien', ['verified' => false]);

        $this->asAdmin()->postJson("/api/v1/auth/admin/technicians/{$tech->id}/reject", [])->assertStatus(422);
        $this->assertTrue((bool) $tech->fresh()->is_active);

        $this->asAdmin()->postJson("/api/v1/auth/admin/technicians/{$tech->id}/reject", ['reason' => 'Pièces non conformes'])->assertOk();

        $this->assertFalse((bool) $tech->fresh()->is_active);
        $this->assertSame([], $this->asAdmin()->getJson('/api/v1/auth/admin/technicians/pending')->json('data'));
    }

    public function test_le_motif_de_rejet_est_conserve_et_notifie_au_technicien(): void
    {
        $tech = $this->makeUser('technicien', ['verified' => false]);

        $this->asAdmin()->postJson("/api/v1/auth/admin/technicians/{$tech->id}/reject", ['reason' => 'Pièces non conformes'])->assertOk();

        $this->assertSame('Pièces non conformes', $tech->fresh()->meta['rejection_reason']);
        $notification = Notification::where('user_id', $tech->id)->firstOrFail();
        $this->assertStringContainsString('Pièces non conformes', $notification->body);
    }

    public function test_une_approbation_ulterieure_efface_le_rejet(): void
    {
        $tech = $this->makeUser('technicien', ['verified' => false]);
        $this->asAdmin()->postJson("/api/v1/auth/admin/technicians/{$tech->id}/reject", ['reason' => 'Dossier incomplet'])->assertOk();

        $this->asAdmin()->postJson("/api/v1/auth/admin/technicians/{$tech->id}/approve")->assertOk();

        $this->assertTrue((bool) $tech->fresh()->is_active);
        $this->assertArrayNotHasKey('rejection_reason', $tech->fresh()->meta ?? []);
    }

    // ── Rôles ──────────────────────────────────────────────

    public function test_attribution_et_retrait_d_un_role(): void
    {
        $user = $this->makeUser('client');

        $this->asAdmin()->postJson("/api/v1/auth/admin/users/{$user->id}/roles", ['role' => 'technicien'])->assertSuccessful();
        $this->assertTrue($user->fresh()->hasRole('technicien'));

        $this->asAdmin()->deleteJson("/api/v1/auth/admin/users/{$user->id}/roles/technicien")->assertSuccessful();
        $this->assertFalse($user->fresh()->hasRole('technicien'));
    }

    public function test_les_statistiques_sont_reservees_a_l_administrateur(): void
    {
        $this->actingAs($this->makeUser('client'), 'sanctum')->getJson('/api/v1/auth/admin/stats')->assertForbidden();
        $this->asAdmin()->getJson('/api/v1/auth/admin/stats')->assertOk();
    }

    public function test_le_secret_2fa_n_est_serialise_dans_aucune_reponse_contenant_l_utilisateur(): void
    {
        $user = $this->makeUser('client');
        $this->actingAs($user, 'sanctum')->postJson('/api/v1/auth/2fa/generate')->assertSuccessful();
        $this->assertNotEmpty($user->fresh()->two_factor_secret, 'Précondition : un secret est enregistré.');

        $this->assertArrayNotHasKey('two_factor_secret', $this->actingAs($user, 'sanctum')->getJson('/api/v1/auth/me')->json('user'));
        $this->assertArrayNotHasKey('two_factor_secret', $this->asAdmin()->getJson("/api/v1/auth/admin/users/{$user->id}")->json('data'));
        $this->assertStringNotContainsString('two_factor_secret', $this->asAdmin()->getJson('/api/v1/auth/admin/users')->getContent());
    }
}
