<?php

namespace Tests\Feature;

use App\Broadcasting\ResilientPusherBroadcaster;
use App\Modules\Finance\Models\Subscription;
use App\Modules\Matching\Models\Proposal;
use App\Modules\Matching\Services\ProposalExpirationService;
use App\Modules\Notification\Models\PushToken;
use App\Modules\Notification\Services\NotificationService;
use App\Modules\Notification\Services\SmsService;
use Illuminate\Broadcasting\BroadcastException;
use Illuminate\Broadcasting\Broadcasters\PusherBroadcaster;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\Concerns\BuildsWorld;
use Tests\TestCase;

class MatchingAndNotificationsTest extends TestCase
{
    use RefreshDatabase, BuildsWorld;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    private function matchedIds($client, $request): array
    {
        return collect(
            $this->actingAs($client, 'sanctum')->postJson("/api/v1/requests/{$request->id}/match")
                ->assertSuccessful()->json('data.technicians')
        )->pluck('id')->all();
    }

    // ── Rapprochement géographique ─────────────────────────

    public function test_un_technicien_proche_est_retenu(): void
    {
        $client = $this->makeUser('client');
        $tech = $this->makeTechnician();

        $this->assertContains($tech->id, $this->matchedIds($client, $this->makeRequestFor($client)));
    }

    public function test_le_rayon_declare_par_le_technicien_est_respecte(): void
    {
        $client = $this->makeUser('client');
        // ≈ 11 km du client : exclu avec un rayon de 5 km, retenu avec 20 km.
        $court = $this->makeTechnician(['radius' => 5], 9.8700, 4.0500);
        $large = $this->makeTechnician(['radius' => 20], 9.8700, 4.0500);

        $ids = $this->matchedIds($client, $this->makeRequestFor($client));

        $this->assertNotContains($court->id, $ids);
        $this->assertContains($large->id, $ids);
    }

    public function test_un_technicien_non_verifie_ou_inactif_est_exclu(): void
    {
        $client = $this->makeUser('client');
        $nonVerifie = $this->makeTechnician();
        $nonVerifie->forceFill(['verified' => false])->save();
        $inactif = $this->makeTechnician();
        $inactif->forceFill(['is_active' => false])->save();

        $ids = $this->matchedIds($client, $this->makeRequestFor($client));

        $this->assertNotContains($nonVerifie->id, $ids);
        $this->assertNotContains($inactif->id, $ids);
    }

    public function test_filtre_d_abonnement_actif_quand_il_est_exige(): void
    {
        config(['reliance.require_subscription' => true]);
        $client = $this->makeUser('client');
        $abonne = $this->makeTechnician();
        $sans = $this->makeTechnician();
        $expire = $this->makeTechnician();
        Subscription::create([
            'subscriber_id' => $abonne->id, 'plan_name' => 'pro', 'status' => 'active',
            'start_date' => now(), 'end_date' => now()->addMonth(), 'recurring' => true,
        ]);
        Subscription::create([
            'subscriber_id' => $expire->id, 'plan_name' => 'pro', 'status' => 'active',
            'start_date' => now()->subMonths(2), 'end_date' => now()->subMonth(), 'recurring' => true,
        ]);

        $ids = $this->matchedIds($client, $this->makeRequestFor($client));

        $this->assertContains($abonne->id, $ids);
        $this->assertNotContains($sans->id, $ids);
        $this->assertNotContains($expire->id, $ids);
    }

    public function test_sans_exigence_d_abonnement_tous_les_techniciens_eligibles_sont_retenus(): void
    {
        config(['reliance.require_subscription' => false]);
        $client = $this->makeUser('client');
        $sans = $this->makeTechnician();

        $this->assertContains($sans->id, $this->matchedIds($client, $this->makeRequestFor($client)));
    }

    // ── Expiration des propositions ────────────────────────

    public function test_expiration_des_propositions_echues(): void
    {
        $client = $this->makeUser('client');
        $tech = $this->makeTechnician();
        $request = $this->makeRequestFor($client);
        $proposalId = $this->actingAs($client, 'sanctum')
            ->postJson("/api/v1/requests/{$request->id}/select-technician", ['technician_id' => $tech->id, 'price' => 5000])
            ->json('data.id');
        $recent = Proposal::findOrFail($proposalId);
        $recent->forceFill(['expires_at' => now()->subMinute()])->save();
        $request->forceFill(['status' => 'quoted'])->save();

        $count = app(ProposalExpirationService::class)->expireOverdue();

        $this->assertSame(1, $count);
        $this->assertSame('expired', $recent->fresh()->status);
        $this->assertSame('analyzed', $request->fresh()->status);
        $this->assertSame(0, app(ProposalExpirationService::class)->expireOverdue(), 'Idempotent.');
    }

    public function test_une_proposition_non_echue_n_expire_pas(): void
    {
        $client = $this->makeUser('client');
        $tech = $this->makeTechnician();
        $request = $this->makeRequestFor($client);
        $proposalId = $this->actingAs($client, 'sanctum')
            ->postJson("/api/v1/requests/{$request->id}/select-technician", ['technician_id' => $tech->id, 'price' => 5000])
            ->json('data.id');
        Proposal::findOrFail($proposalId)->forceFill(['expires_at' => now()->addHour()])->save();

        $this->assertSame(0, app(ProposalExpirationService::class)->expireOverdue());
    }

    // ── Diffusion temps réel résiliente ────────────────────

    private function pusherEnPanne(string $class)
    {
        $pusher = \Mockery::mock(\Pusher\Pusher::class);
        $pusher->shouldReceive('trigger')->andThrow(new \Pusher\ApiErrorException('serveur indisponible'));

        return new $class($pusher);
    }

    public function test_le_diffuseur_standard_leve_une_exception_quand_le_serveur_est_en_panne(): void
    {
        $this->expectException(BroadcastException::class);
        $this->pusherEnPanne(PusherBroadcaster::class)->broadcast(['canal'], 'evt', []);
    }

    public function test_le_diffuseur_resilient_journalise_sans_lever_d_exception(): void
    {
        Log::spy();

        $this->pusherEnPanne(ResilientPusherBroadcaster::class)->broadcast(['canal'], 'evt', []);

        Log::shouldHaveReceived('warning')->once();
    }

    public function test_une_action_metier_aboutit_malgre_un_serveur_websocket_indisponible(): void
    {
        config(['broadcasting.default' => 'pusher']);
        $client = $this->makeUser('client');
        $this->makeTechnician();

        // Le pilote `pusher` pointe vers un hôte injoignable : la demande doit être créée malgré tout.
        $request = $this->makeRequestFor($client);

        $this->assertNotNull($request->id);
    }

    // ── Notifications multicanal ───────────────────────────

    public function test_le_push_expo_est_envoye_uniquement_aux_jetons_expo(): void
    {
        Http::fake();
        $user = $this->makeUser('client');
        PushToken::create(['user_id' => $user->id, 'token' => 'ExponentPushToken[abc]', 'provider' => 'expo', 'device_type' => 'android']);
        PushToken::create(['user_id' => $user->id, 'token' => 'fcm-natif-xyz', 'provider' => 'fcm', 'device_type' => 'android']);

        app(NotificationService::class)->send($user->id, 'Titre', 'Corps', ['k' => 'v'], ['in_app', 'push']);

        Http::assertSent(function ($request) {
            $to = collect($request->data())->pluck('to')->all();
            return str_contains($request->url(), 'exp.host') && $to === ['ExponentPushToken[abc]'];
        });
    }

    public function test_une_panne_du_service_push_n_interrompt_pas_l_envoi(): void
    {
        Http::fake(fn () => throw new \RuntimeException('réseau'));
        $user = $this->makeUser('client');
        PushToken::create(['user_id' => $user->id, 'token' => 'ExponentPushToken[abc]', 'provider' => 'expo', 'device_type' => 'ios']);

        $notification = app(NotificationService::class)->send($user->id, 'T', 'C', [], ['in_app', 'push']);

        $this->assertNotNull($notification->id);
    }

    public function test_le_sms_pilote_log_n_envoie_rien(): void
    {
        config(['services.sms.driver' => 'log']);
        Http::fake();

        $this->assertFalse(app(SmsService::class)->send('+237600000000', 'Bonjour'));
        Http::assertNothingSent();
    }

    public function test_le_sms_pilote_http_poste_vers_la_passerelle(): void
    {
        config(['services.sms.driver' => 'http', 'services.sms.url' => 'https://sms.test/send', 'services.sms.key' => 'k']);
        Http::fake(['sms.test/*' => Http::response(['ok' => true], 200)]);

        $this->assertTrue(app(SmsService::class)->send('+237600000000', 'Bonjour'));
        Http::assertSent(fn ($r) => $r->hasHeader('Authorization', 'Bearer k') && $r['to'] === '+237600000000');
    }

    public function test_le_sms_pilote_http_tolere_une_passerelle_en_panne(): void
    {
        config(['services.sms.driver' => 'http', 'services.sms.url' => 'https://sms.test/send']);
        Http::fake(fn () => throw new \RuntimeException('réseau'));

        $this->assertFalse(app(SmsService::class)->send('+237600000000', 'Bonjour'));
    }

    public function test_les_notifications_metier_creent_une_entree_pour_le_bon_destinataire(): void
    {
        $client = $this->makeUser('client');
        $tech = $this->makeUser('technicien');
        $service = app(NotificationService::class);

        $service->notifyProposal($tech->id, 'Fuite', 'p1');
        $service->notifyProposalAccepted($client->id, 'Tech', 'r1');
        $service->notifyProposalRefused($client->id, 'Tech', 'r1');
        $service->notifyProposalExpired($client->id, 'r1');
        $service->notifyQuoteReceived($client->id, 'q1', 'r1');
        $service->notifyJobStatus($client->id, 'in_progress', 'r1');

        $this->assertSame(1, \App\Modules\Notification\Models\Notification::where('user_id', $tech->id)->count());
        $this->assertSame(5, \App\Modules\Notification\Models\Notification::where('user_id', $client->id)->count());
    }
}
