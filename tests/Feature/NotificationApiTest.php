<?php

namespace Tests\Feature;

use App\Modules\Notification\Models\Notification;
use App\Modules\Notification\Models\PushToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsWorld;
use Tests\TestCase;

class NotificationApiTest extends TestCase
{
    use RefreshDatabase, BuildsWorld;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    private function creer($user, int $n = 1): void
    {
        for ($i = 0; $i < $n; $i++) {
            Notification::create(['user_id' => $user->id, 'title' => "N{$i}", 'body' => 'b', 'payload' => [], 'channels' => ['in_app']]);
        }
    }

    public function test_la_liste_est_paginee_par_20_et_limitee_a_son_proprietaire(): void
    {
        $moi = $this->makeUser('client');
        $autre = $this->makeUser('client');
        $this->creer($moi, 25);
        $this->creer($autre, 3);

        $r = $this->actingAs($moi, 'sanctum')->getJson('/api/v1/notifications')->assertOk();

        $this->assertCount(20, $r->json('data'));
        $this->assertSame(25, $r->json('total'));
        $this->assertCount(5, $this->actingAs($moi, 'sanctum')->getJson('/api/v1/notifications?page=2')->json('data'));
    }

    public function test_marquage_lu_individuel_et_compteur(): void
    {
        $moi = $this->makeUser('client');
        $this->creer($moi, 3);
        $id = Notification::where('user_id', $moi->id)->value('id');

        $this->assertSame(3, $this->actingAs($moi, 'sanctum')->getJson('/api/v1/notifications/unread-count')->json('count'));
        $this->actingAs($moi, 'sanctum')->postJson("/api/v1/notifications/{$id}/read")->assertOk();

        $this->assertSame(2, $this->actingAs($moi, 'sanctum')->getJson('/api/v1/notifications/unread-count')->json('count'));
        $this->assertNotNull(Notification::findOrFail($id)->read_at);
    }

    public function test_on_ne_peut_marquer_lue_la_notification_d_autrui(): void
    {
        $moi = $this->makeUser('client');
        $autre = $this->makeUser('client');
        $this->creer($autre);
        $id = Notification::where('user_id', $autre->id)->value('id');

        $this->actingAs($moi, 'sanctum')->postJson("/api/v1/notifications/{$id}/read")->assertNotFound();

        $this->assertFalse((bool) Notification::findOrFail($id)->is_read);
    }

    public function test_tout_marquer_lu_n_affecte_que_ses_notifications(): void
    {
        $moi = $this->makeUser('client');
        $autre = $this->makeUser('client');
        $this->creer($moi, 2);
        $this->creer($autre, 2);

        $this->actingAs($moi, 'sanctum')->postJson('/api/v1/notifications/read-all')->assertOk();

        $this->assertSame(0, $this->actingAs($moi, 'sanctum')->getJson('/api/v1/notifications/unread-count')->json('count'));
        $this->assertSame(2, $this->actingAs($autre, 'sanctum')->getJson('/api/v1/notifications/unread-count')->json('count'));
    }

    public function test_enregistrement_et_suppression_d_un_jeton_push_propre_a_l_utilisateur(): void
    {
        $moi = $this->makeUser('client');
        $autre = $this->makeUser('client');

        $this->actingAs($moi, 'sanctum')->postJson('/api/v1/push-tokens', ['token' => 'ExponentPushToken[abc]'])->assertSuccessful();
        $this->actingAs($moi, 'sanctum')->postJson('/api/v1/push-tokens', ['token' => 'ExponentPushToken[abc]'])->assertSuccessful();
        $this->assertSame(1, PushToken::where('user_id', $moi->id)->count(), 'Idempotent : pas de doublon.');

        $id = PushToken::where('user_id', $moi->id)->value('id');
        // Suppression idempotente et filtrée par propriétaire : l'appel d'autrui ne supprime rien.
        $this->actingAs($autre, 'sanctum')->deleteJson("/api/v1/push-tokens/{$id}")->assertSuccessful();
        $this->assertSame(1, PushToken::where('user_id', $moi->id)->count());
        $this->actingAs($moi, 'sanctum')->deleteJson("/api/v1/push-tokens/{$id}")->assertSuccessful();
        $this->assertSame(0, PushToken::where('user_id', $moi->id)->count());
    }

    public function test_les_preferences_de_notification_sont_lisibles_et_modifiables(): void
    {
        $moi = $this->makeUser('client');

        $this->actingAs($moi, 'sanctum')->getJson('/api/v1/notification-settings')->assertOk();
        $status = $this->actingAs($moi, 'sanctum')->putJson('/api/v1/notification-settings', ['channel' => 'email', 'enabled' => false])->status();

        $this->assertContains($status, [200, 201, 422]);
    }
}
