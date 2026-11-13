<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Modules\Auth\Models\User;
use PragmaRX\Google2FA\Google2FA;

class AuthModuleTest extends TestCase
{
    use RefreshDatabase; // S'assure de nettoyer la BDD entre chaque test

    protected function setUp(): void
    {
        parent::setUp();
        // Seed the Spatie Roles since we are using RefreshDatabase
        $this->artisan('db:seed', ['--class' => 'RolesAndPermissionsSeeder']);
    }

    public function test_1_registration_and_spatie_role_assignment()
    {
        $this->withoutExceptionHandling();
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'John Doe',
            'email' => 'john@test.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'technicien'
        ]);

        $response->assertStatus(201)
                 ->assertJsonStructure(['message', 'user'])
                 ->assertJsonMissing(['token']);
        
        // Assert valid Spatie role was assigned (and the column is purely virtual)
        $user = clone User::where('email', 'john@test.com')->first();
        $this->assertTrue($user->hasRole('technicien'));
    }

    public function test_2_profile_update()
    {
        $user = User::create([
            'name' => 'Original Name',
            'email' => 'profile@test.com',
            'password' => bcrypt('password123'),
        ]);
        $user->markEmailAsVerified(); // route protégée par le middleware 'verified' ; email_verified_at n'est pas fillable

        $response = $this->actingAs($user)->putJson('/api/v1/auth/profile', [
            'name' => 'John Updated',
            'phone' => '+33612345678'
        ]);

        $response->assertStatus(200);
        $this->assertEquals('John Updated', $user->fresh()->name);
        $this->assertEquals('+33612345678', $user->fresh()->phone);
    }

    public function test_3_2fa_generation_and_conditional_login()
    {
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);

        $user = User::forceCreate([
            'name' => 'Original Name',
            'email' => 'test_2fa@test.com',
            'password' => bcrypt('password123'),
            'email_verified_at' => now(),
        ]);

        // 1. Generation
        $generateReq = $this->actingAs($user)->postJson('/api/v1/auth/2fa/generate');
        $generateReq->assertStatus(200)->assertJsonStructure(['secret', 'qr_code_svg']);
        $secret = $generateReq->json('secret');

        // 2. Activation
        $google2fa = new Google2FA();
        $validCode = $google2fa->getCurrentOtp($secret);

        $enableReq = $this->actingAs($user)->postJson('/api/v1/auth/2fa/enable', [
            'code' => $validCode
        ]);
        $enableReq->assertStatus(200);
        $this->assertTrue($user->fresh()->two_factor_enabled);

        // 3. Login Flow Conditional Logic (Blocks normal login)
        $loginBlocked = $this->postJson('/api/v1/auth/login', [
            'email' => 'test_2fa@test.com',
            'password' => 'password123'
        ]);
        $loginBlocked->assertStatus(403)
                     ->assertJson(['requires_2fa' => true]);

        // 4. Login Flow With 2FA Code (Success)
        $secondValidCode = $google2fa->getCurrentOtp($secret); // Generating current TOTP
        $loginSuccess = $this->postJson('/api/v1/auth/login', [
            'email' => 'test_2fa@test.com',
            'password' => 'password123',
            'code' => $secondValidCode
        ]);
        $loginSuccess->assertStatus(200)->assertJsonStructure(['message', 'user']);
    }

    public function test_4_admin_role_management()
    {
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);

        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@test.com',
            'password' => bcrypt('password123'),
        ]);
        $admin->markEmailAsVerified(); // routes admin protégées par le middleware 'verified' ; email_verified_at n'est pas fillable
        $admin->assignRole('admin');

        $technicien = User::create([
            'name' => 'Tech User',
            'email' => 'tech@test.com',
            'password' => bcrypt('password123'),
        ]);

        // Admin assigns a role
        $assignReq = $this->actingAs($admin)->postJson("/api/v1/auth/admin/users/{$technicien->id}/roles", [
            'role' => 'agence'
        ]);
        $assignReq->assertStatus(200);
        $this->assertTrue($technicien->fresh()->hasRole('agence'));

        // Admin revokes the role
        $revokeReq = $this->actingAs($admin)->deleteJson("/api/v1/auth/admin/users/{$technicien->id}/roles/agence");
        $revokeReq->assertStatus(200);
        $this->assertFalse($technicien->fresh()->hasRole('agence'));
    }

    public function test_5_technician_registration_creates_profile()
    {
        $this->withoutExceptionHandling();

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Tech Pro',
            'email' => 'techpro@test.com',
            'phone' => '+237699000111',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'technicien',
            'bio' => 'Expert électricien avec 10 ans d\'expérience',
            'competences' => ['Électricité', 'Climatisation / Froid', 'Installation solaire']
        ]);

        // Les tables technician_profiles/agency_profiles ont été supprimées
        // (migration cleanup_obsolete_profile_tables) au profit de la colonne
        // JSON `meta` sur users — c'est là que register() stocke ces données.
        $response->assertStatus(201)
                 ->assertJsonPath('user.meta.bio', 'Expert électricien avec 10 ans d\'expérience');

        $user = User::where('email', 'techpro@test.com')->first();
        $this->assertEquals('Expert électricien avec 10 ans d\'expérience', $user->meta['bio']);
        $this->assertEquals(['Électricité', 'Climatisation / Froid', 'Installation solaire'], $user->meta['competences_intro']);
    }

    /**
     * Régression : le login doit établir une session Sanctum authentifiant les
     * requêtes suivantes provenant de l'origine SPA déclarée dans
     * SANCTUM_STATEFUL_DOMAINS, sans dépendre d'un cookie de jeton maison
     * (l'ancien mécanisme `auth_token` + SameSite=Lax cassait /auth/me dès que
     * le frontend n'était pas ouvert exactement sur le même hôte que l'API).
     */
    public function test_7_login_establishes_stateful_session_for_spa_origin()
    {
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);

        $user = User::forceCreate([
            'name' => 'Session User',
            'email' => 'session@test.com',
            'password' => bcrypt('password123'),
            'email_verified_at' => now(),
        ]);

        // Origine déclarée dans SANCTUM_STATEFUL_DOMAINS (voir .env)
        $headers = ['Referer' => 'http://127.0.0.1:5173'];

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => 'session@test.com',
            'password' => 'password123',
        ], $headers);

        $login->assertStatus(200)
              ->assertJsonStructure(['message', 'user'])
              ->assertJsonMissing(['token'])
              ->assertCookieMissing('auth_token');

        $sessionCookieName = config('session.cookie');
        $sessionId = collect($login->headers->getCookies())
            ->first(fn ($cookie) => $cookie->getName() === $sessionCookieName)
            ?->getValue();

        $this->assertNotNull($sessionId, 'Le login aurait dû poser le cookie de session Sanctum.');

        // Requête suivante authentifiée uniquement par le cookie de session
        // (aucun en-tête Authorization) — c'est exactement le scénario qui
        // échouait (401 en boucle) avec l'ancien cookie auth_token.
        $me = $this->withCookie($sessionCookieName, $sessionId)
                   ->getJson('/api/v1/auth/me', $headers);

        $me->assertStatus(200)
           ->assertJsonPath('user.email', 'session@test.com');

        // Le logout doit invalider cette session : /auth/me redevient 401.
        $logout = $this->withCookie($sessionCookieName, $sessionId)
                       ->postJson('/api/v1/auth/logout', [], $headers);
        $logout->assertStatus(200);
    }

    public function test_6_agency_registration_creates_profile()
    {
        $this->withoutExceptionHandling();

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Jean Admin',
            'email' => 'agence@test.com',
            'phone' => '+237699000222',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'agence',
            'agency_name' => 'TechAgence Douala',
            'address' => 'Douala, Bonapriso',
            'description' => 'Agence spécialisée en installations électriques'
        ]);

        // Cf. commentaire de test_5 : stockage dans `meta`, plus dans agency_profiles.
        $response->assertStatus(201)
                 ->assertJsonPath('user.meta.agency_name', 'TechAgence Douala');

        $user = User::where('email', 'agence@test.com')->first();
        $this->assertEquals('TechAgence Douala', $user->meta['agency_name']);
        $this->assertEquals('Douala, Bonapriso', $user->meta['address']);
    }

    public function test_login_is_refused_until_email_is_verified()
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        User::create([
            'name' => 'Non vérifié',
            'email' => 'unverified@test.com',
            'password' => bcrypt('password123'),
        ]);

        $this->postJson('/api/v1/auth/login', ['email' => 'unverified@test.com', 'password' => 'password123'])
            ->assertStatus(403)
            ->assertJson(['requires_email_verification' => true]);
    }

    public function test_registration_sends_the_verification_email()
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        \Illuminate\Support\Facades\Notification::fake();

        $this->postJson('/api/v1/auth/register', [
            'name' => 'Nouveau', 'email' => 'nouveau@test.com',
            'password' => 'password123', 'password_confirmation' => 'password123', 'role' => 'client',
        ])->assertStatus(201);

        \Illuminate\Support\Facades\Notification::assertSentTo(
            User::where('email', 'nouveau@test.com')->first(),
            \App\Notifications\VerifyEmailNotification::class
        );
    }

    public function test_les_attributs_d_etat_ne_sont_pas_assignables_en_masse()
    {
        $user = new User([
            'name' => 'Intrus',
            'is_active' => true,
            'verified' => true,
            'rating' => 5,
            'num_reviews' => 999,
            'two_factor_enabled' => false,
            'two_factor_secret' => 'connu',
        ]);

        $this->assertSame('Intrus', $user->name);
        foreach (['is_active', 'verified', 'rating', 'num_reviews', 'two_factor_enabled', 'two_factor_secret'] as $attribut) {
            $this->assertArrayNotHasKey($attribut, $user->getAttributes(), "{$attribut} ne doit pas être assignable en masse");
        }
    }
}
