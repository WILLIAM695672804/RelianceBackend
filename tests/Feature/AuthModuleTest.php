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
                 ->assertJsonStructure(['message', 'user', 'token']);
        
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

        $user = User::create([
            'name' => 'Original Name',
            'email' => 'test_2fa@test.com',
            'password' => bcrypt('password123')
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
        $loginSuccess->assertStatus(200)->assertJsonStructure(['token']);
    }

    public function test_4_admin_role_management()
    {
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);

        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@test.com',
            'password' => bcrypt('password123'),
        ]);
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

        $response->assertStatus(201)
                 ->assertJsonPath('user.technician_profile.bio', 'Expert électricien avec 10 ans d\'expérience');

        $user = User::where('email', 'techpro@test.com')->first();
        $this->assertNotNull($user->technicianProfile);
        $this->assertEquals(['Électricité', 'Climatisation / Froid', 'Installation solaire'], $user->technicianProfile->competences);
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

        $response->assertStatus(201)
                 ->assertJsonPath('user.agency_profile.agency_name', 'TechAgence Douala');

        $user = User::where('email', 'agence@test.com')->first();
        $this->assertNotNull($user->agencyProfile);
        $this->assertEquals('Douala, Bonapriso', $user->agencyProfile->address);
    }
}
