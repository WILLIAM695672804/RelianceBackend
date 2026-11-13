<?php

namespace Tests\Feature;

use App\Modules\Governance\Models\Dispute;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\Concerns\BuildsWorld;
use Tests\TestCase;

class ModulesWorkflowTest extends TestCase
{
    use RefreshDatabase, BuildsWorld;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    // ── Litiges ────────────────────────────────────────────

    public function test_un_litige_n_oppose_que_les_parties_de_la_mission(): void
    {
        $client = $this->makeUser('client');
        $tech = $this->makeTechnician();
        $tiers = $this->makeUser('client');
        [$request] = $this->assignedMission($client, $tech);
        $payload = fn ($against) => ['request_id' => $request->id, 'against' => $against, 'reason' => 'Retard', 'description' => 'Intervention tardive'];

        $this->actingAs($tiers, 'sanctum')->postJson('/api/v1/disputes', $payload($tech->id))->assertForbidden();
        $this->actingAs($client, 'sanctum')->postJson('/api/v1/disputes', $payload($tiers->id))->assertForbidden();
        $this->actingAs($client, 'sanctum')->postJson('/api/v1/disputes', $payload($client->id))->assertForbidden();
        $this->assertSame(0, Dispute::count());

        $this->actingAs($client, 'sanctum')->postJson('/api/v1/disputes', $payload($tech->id))->assertStatus(201);
        $this->assertSame(1, Dispute::count());
    }

    public function test_le_litige_est_visible_des_seules_parties_et_de_l_administrateur(): void
    {
        $client = $this->makeUser('client');
        $tech = $this->makeTechnician();
        $tiers = $this->makeUser('client');
        $admin = $this->makeUser('admin');
        [$request] = $this->assignedMission($client, $tech);
        $id = $this->actingAs($client, 'sanctum')->postJson('/api/v1/disputes', [
            'request_id' => $request->id, 'against' => $tech->id, 'reason' => 'x', 'description' => 'y',
        ])->json('data.id');

        $this->actingAs($tech, 'sanctum')->getJson("/api/v1/disputes/{$id}")->assertOk();
        $this->actingAs($tiers, 'sanctum')->getJson("/api/v1/disputes/{$id}")->assertNotFound();
        $this->actingAs($tiers, 'sanctum')->postJson("/api/v1/disputes/{$id}/message", ['message' => 'intrusion'])->assertNotFound();
        $this->actingAs($tech, 'sanctum')->postJson("/api/v1/disputes/{$id}/message", ['message' => 'ma version'])->assertOk();

        $this->actingAs($admin, 'sanctum')->getJson('/api/v1/admin/disputes')->assertOk();
        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/admin/disputes/{$id}/resolve", ['resolution' => 'Accord amiable'])->assertOk();
        $this->actingAs($client, 'sanctum')->postJson("/api/v1/disputes/{$id}/message", ['message' => 'tardif'])->assertStatus(422);
    }

    // ── Administration ─────────────────────────────────────

    public function test_la_carte_admin_expose_techniciens_et_demandes_geolocalises(): void
    {
        $client = $this->makeUser('client');
        $tech = $this->makeTechnician(['radius' => 20]);
        $request = $this->makeRequestFor($client);

        $data = $this->actingAs($this->makeUser('admin'), 'sanctum')->getJson('/api/v1/admin/map')->assertOk()->json('data');

        $t = collect($data['technicians'])->firstWhere('id', $tech->id);
        $this->assertNotNull($t);
        $this->assertEqualsWithDelta(4.05, $t['latitude'], 0.001);
        $this->assertEqualsWithDelta(20, $t['radius_km'], 0.001);
        $this->assertContains('Plomberie générale', $t['skills']);
        $this->assertContains($request->id, collect($data['requests'])->pluck('id')->all());
    }

    public function test_l_admin_consulte_et_recherche_les_conversations(): void
    {
        $client = $this->makeUser('client');
        $tech = $this->makeUser('technicien');
        $admin = $this->makeUser('admin');
        $conv = $this->actingAs($client, 'sanctum')->postJson('/api/v1/conversations', [
            'members' => [$tech->id], 'subject' => 'Question', 'initial_message' => 'Bonjour monsieur',
        ])->assertSuccessful()->json('data.id');
        $this->actingAs($tech, 'sanctum')->postJson("/api/v1/conversations/{$conv}/messages", ['body' => 'Oui, dites-moi'])->assertSuccessful();

        $this->actingAs($admin, 'sanctum')->getJson('/api/v1/admin/conversations')->assertOk()->assertJsonFragment(['id' => $conv]);
        $this->actingAs($admin, 'sanctum')->getJson("/api/v1/admin/conversations/{$conv}")->assertOk();
        $this->actingAs($admin, 'sanctum')->getJson('/api/v1/admin/conversations/search?q=Bonjour')->assertOk();
    }

    public function test_la_messagerie_est_reservee_aux_membres(): void
    {
        $client = $this->makeUser('client');
        $tech = $this->makeUser('technicien');
        $intrus = $this->makeUser('client');
        $conv = $this->actingAs($client, 'sanctum')->postJson('/api/v1/conversations', [
            'members' => [$tech->id], 'subject' => 'Privé', 'initial_message' => 'Secret',
        ])->json('data.id');

        $status = $this->actingAs($intrus, 'sanctum')->getJson("/api/v1/conversations/{$conv}/messages")->status();
        $this->assertContains($status, [403, 404]);
        $status = $this->actingAs($intrus, 'sanctum')->postJson("/api/v1/conversations/{$conv}/messages", ['body' => 'intrusion'])->status();
        $this->assertContains($status, [403, 404]);
    }

    public function test_approbation_d_un_technicien_par_l_admin(): void
    {
        $tech = $this->makeUser('technicien', ['verified' => false]);
        $admin = $this->makeUser('admin');

        $this->actingAs($admin, 'sanctum')->getJson('/api/v1/auth/admin/technicians/pending')->assertOk();
        $this->actingAs($admin, 'sanctum')->postJson("/api/v1/auth/admin/technicians/{$tech->id}/approve")->assertOk();

        $this->assertTrue((bool) $tech->fresh()->verified);
    }

    // ── Social ─────────────────────────────────────────────

    public function test_publication_flux_like_commentaire_et_abonnement(): void
    {
        $auteur = $this->makeUser('technicien');
        $lecteur = $this->makeUser('client');

        $postId = $this->actingAs($auteur, 'sanctum')->post('/api/v1/posts', ['content' => 'Chantier terminé'], ['Accept' => 'application/json'])
            ->assertSuccessful()->json('data.id');

        $this->actingAs($lecteur, 'sanctum')->postJson("/api/v1/follows/{$auteur->id}")->assertSuccessful();
        $this->actingAs($lecteur, 'sanctum')->getJson('/api/v1/posts/feed')->assertOk();
        $this->actingAs($lecteur, 'sanctum')->postJson("/api/v1/posts/{$postId}/like")->assertSuccessful();
        $this->actingAs($lecteur, 'sanctum')->postJson("/api/v1/posts/{$postId}/comments", ['content' => 'Bravo'])->assertSuccessful();
        $this->getJson("/api/v1/posts/{$postId}/comments")->assertOk();
    }

    public function test_seul_l_auteur_supprime_sa_publication(): void
    {
        $auteur = $this->makeUser('technicien');
        $autre = $this->makeUser('client');
        $postId = $this->actingAs($auteur, 'sanctum')->post('/api/v1/posts', ['content' => 'À moi'], ['Accept' => 'application/json'])->json('data.id');

        $this->assertContains($this->actingAs($autre, 'sanctum')->deleteJson("/api/v1/posts/{$postId}")->status(), [403, 404]);
        $this->actingAs($auteur, 'sanctum')->deleteJson("/api/v1/posts/{$postId}")->assertSuccessful();
    }

    // ── Planning et profil technicien ──────────────────────

    public function test_planning_du_technicien(): void
    {
        $tech = $this->makeTechnician();

        $this->actingAs($tech, 'sanctum')->postJson('/api/v1/technician/time-slots', [
            'day_of_week' => 2, 'start_time' => '08:00', 'end_time' => '17:00', 'repeat_weekly' => true,
        ])->assertSuccessful();
        $this->actingAs($tech, 'sanctum')->getJson('/api/v1/technician/calendar')->assertOk();
        $this->actingAs($tech, 'sanctum')->getJson('/api/v1/technician/profile')->assertOk();
        $this->actingAs($tech, 'sanctum')->putJson('/api/v1/technician/location', [
            'latitude' => 4.05, 'longitude' => 9.77, 'radius' => 20, 'zones' => ['Akwa'],
        ])->assertSuccessful();
    }

    public function test_un_client_n_accede_pas_aux_routes_technicien_de_planning(): void
    {
        $client = $this->makeUser('client');

        foreach ([['post', '/api/v1/technician/time-slots'], ['put', '/api/v1/technician/location'], ['post', '/api/v1/technician/skills'], ['get', '/api/v1/technician/stats']] as [$m, $uri]) {
            $this->actingAs($client, 'sanctum')->json($m, $uri)->assertForbidden();
        }
    }

    public function test_la_zone_enregistree_pilote_le_rapprochement(): void
    {
        $client = $this->makeUser('client');
        $tech = $this->makeTechnician(['radius' => 50], 9.8700, 4.0500);
        $request = $this->makeRequestFor($client);

        $match = fn () => collect($this->actingAs($client, 'sanctum')->postJson("/api/v1/requests/{$request->id}/match")->json('data.technicians'))->pluck('id')->all();
        $this->assertContains($tech->id, $match());

        $this->actingAs($tech, 'sanctum')->putJson('/api/v1/technician/location', [
            'latitude' => 4.05, 'longitude' => 9.87, 'radius' => 2, 'zones' => ['Akwa'],
        ])->assertSuccessful();

        $this->assertNotContains($tech->id, $match());
    }

    // ── Authentification ───────────────────────────────────

    public function test_inscription_connexion_refusee_puis_verification_par_lien_signe(): void
    {
        Notification::fake();

        $this->postJson('/api/v1/auth/register', [
            'name' => 'Nouveau Client', 'email' => 'nouveau@test.local', 'phone' => '+237600000001',
            'password' => 'Passw0rd!123', 'password_confirmation' => 'Passw0rd!123', 'role' => 'client',
        ])->assertStatus(201);
        $user = \App\Modules\Auth\Models\User::where('email', 'nouveau@test.local')->firstOrFail();
        Notification::assertSentTo($user, VerifyEmailNotification::class);

        auth()->guard('web')->logout();
        $this->postJson('/api/v1/auth/login', ['email' => 'nouveau@test.local', 'password' => 'Passw0rd!123'])
            ->assertStatus(403)->assertJsonPath('requires_email_verification', true);

        $lien = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), ['id' => $user->id, 'hash' => sha1($user->email)]);
        $this->get($lien)->assertSuccessful();
        $this->assertNotNull($user->fresh()->email_verified_at);

        $this->postJson('/api/v1/auth/login', ['email' => 'nouveau@test.local', 'password' => 'Passw0rd!123'])->assertOk();
    }

    public function test_un_lien_de_verification_falsifie_est_refuse(): void
    {
        $user = $this->makeUser('client', ['email_verified_at' => null]);

        $lien = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), ['id' => $user->id, 'hash' => 'mauvais']);
        $this->get($lien)->assertStatus(403);
        $this->get("/api/v1/auth/email/verify/{$user->id}/" . sha1($user->email))->assertStatus(403); // sans signature
        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_le_renvoi_public_du_lien_ne_revele_pas_l_existence_du_compte(): void
    {
        Notification::fake();
        $user = $this->makeUser('client', ['email_verified_at' => null]);

        $existant = $this->postJson('/api/v1/auth/email/resend', ['email' => $user->email]);
        $inconnu = $this->postJson('/api/v1/auth/email/resend', ['email' => 'inconnu@test.local']);

        $this->assertSame($existant->status(), $inconnu->status());
        Notification::assertSentToTimes($user, VerifyEmailNotification::class, 1);
    }

    public function test_une_connexion_avec_un_mauvais_mot_de_passe_est_refusee_et_un_compte_inactif_aussi(): void
    {
        $user = $this->makeUser('client');
        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'faux'])->assertStatus(401);

        $user->forceFill(['is_active' => false])->save();
        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'Passw0rd!123'])->assertStatus(403);
    }

    public function test_generation_du_secret_2fa(): void
    {
        $client = $this->makeUser('client');

        $this->actingAs($client, 'sanctum')->postJson('/api/v1/auth/2fa/generate')->assertSuccessful();
    }

    public function test_mise_a_jour_du_profil_et_des_jetons_push(): void
    {
        $client = $this->makeUser('client');

        $this->actingAs($client, 'sanctum')->putJson('/api/v1/auth/profile', ['name' => 'Nouveau Nom'])->assertSuccessful();
        $this->assertSame('Nouveau Nom', $client->fresh()->name);

        $this->actingAs($client, 'sanctum')->postJson('/api/v1/push-tokens', [
            'token' => 'ExponentPushToken[test]', 'device_type' => 'android',
        ])->assertSuccessful();
        $this->assertSame(1, \App\Modules\Notification\Models\PushToken::where('user_id', $client->id)->count());
    }

    public function test_le_profil_technicien_accepte_un_envoi_multipart_avec_avatar(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');
        $tech = $this->makeTechnician();

        $this->actingAs($tech, 'sanctum')->post('/api/v1/technician/profile', [
            'name' => 'Paul Technicien',
            'avatar' => \Illuminate\Http\UploadedFile::fake()->image('avatar.jpg', 200, 200),
        ], ['Accept' => 'application/json'])->assertSuccessful();

        $this->assertSame('Paul Technicien', $tech->fresh()->name);
        $this->assertNotEmpty($tech->fresh()->avatar_url);
    }

    public function test_le_profil_technicien_multipart_est_interdit_a_un_client(): void
    {
        $this->actingAs($this->makeUser('client'), 'sanctum')
            ->post('/api/v1/technician/profile', ['name' => 'X'], ['Accept' => 'application/json'])->assertForbidden();
    }
}
