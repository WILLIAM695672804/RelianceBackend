<?php

namespace Tests\Feature;

use App\Models\ContactMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsWorld;
use Tests\TestCase;

class PublicEndpointsTest extends TestCase
{
    use RefreshDatabase, BuildsWorld;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    // ── Formulaire de contact ──────────────────────────────

    public function test_le_formulaire_de_contact_enregistre_le_message(): void
    {
        $this->postJson('/api/v1/contact', [
            'name' => 'M. Jean Dupont', 'email' => 'jean@test.local', 'phone' => '+237600000000', 'message' => 'Bonjour',
        ])->assertStatus(201)->assertJsonStructure(['message', 'id']);

        $this->assertSame(1, ContactMessage::count());
    }

    public function test_le_formulaire_de_contact_valide_ses_champs(): void
    {
        $this->postJson('/api/v1/contact', [])->assertStatus(422)->assertJsonValidationErrors(['name', 'email', 'message']);
        $this->postJson('/api/v1/contact', ['name' => 'X', 'email' => 'pas-un-email', 'message' => 'm'])->assertStatus(422)->assertJsonValidationErrors(['email']);
        $this->postJson('/api/v1/contact', ['name' => 'X', 'email' => 'a@b.cd', 'message' => str_repeat('a', 5001)])->assertStatus(422)->assertJsonValidationErrors(['message']);
        $this->assertSame(0, ContactMessage::count());
    }

    public function test_le_formulaire_de_contact_est_limite_en_debit(): void
    {
        $payload = ['name' => 'Spam', 'email' => 'spam@test.local', 'message' => 'x'];

        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/v1/contact', $payload)->assertStatus(201);
        }
        $this->postJson('/api/v1/contact', $payload)->assertStatus(429);
        $this->assertSame(10, ContactMessage::count());
    }

    // ── Bourrage d'identifiants ────────────────────────────

    public function test_la_connexion_est_limitee_a_cinq_tentatives_par_minute_et_par_compte(): void
    {
        $user = $this->makeUser('client');
        $essai = fn (string $mdp) => $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => $mdp]);

        for ($i = 0; $i < 5; $i++) {
            $essai('mauvais')->assertStatus(401);
        }
        // La sixième tentative est bloquée, même avec le bon mot de passe.
        $essai('Passw0rd!123')->assertStatus(429);
    }

    public function test_la_limite_de_connexion_est_propre_a_chaque_compte(): void
    {
        $a = $this->makeUser('client');
        $b = $this->makeUser('client');

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/login', ['email' => $a->email, 'password' => 'mauvais'])->assertStatus(401);
        }

        $this->postJson('/api/v1/auth/login', ['email' => $b->email, 'password' => 'Passw0rd!123'])->assertOk();
    }

    public function test_la_reinitialisation_de_mot_de_passe_ne_revele_pas_l_existence_du_compte(): void
    {
        $existant = $this->makeUser('client');

        $r1 = $this->postJson('/api/v1/auth/forgot-password', ['email' => $existant->email]);
        $r2 = $this->postJson('/api/v1/auth/forgot-password', ['email' => 'inconnu@test.local']);

        $this->assertSame($r1->status(), $r2->status());
    }
}
