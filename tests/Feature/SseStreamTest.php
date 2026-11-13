<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsWorld;
use Tests\TestCase;

class SseStreamTest extends TestCase
{
    use RefreshDatabase, BuildsWorld;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    private function jeton($user): string
    {
        $token = $this->actingAs($user, 'sanctum')->postJson('/api/v1/proposals/stream-token')
            ->assertOk()->assertJsonPath('expires_in', 60)->json('token');
        $this->app['auth']->forgetGuards();

        return $token;
    }

    public function test_le_jeton_de_flux_exige_une_session(): void
    {
        $this->postJson('/api/v1/proposals/stream-token')->assertUnauthorized();
    }

    public function test_le_jeton_ephemere_ouvre_le_flux_une_seule_fois(): void
    {
        $token = $this->jeton($this->makeUser('technicien'));

        $this->get("/api/v1/proposals/stream?token={$token}")
            ->assertOk()->assertHeader('Content-Type', 'text/event-stream; charset=UTF-8');
        $this->get("/api/v1/proposals/stream?token={$token}")->assertUnauthorized();
    }

    public function test_le_jeton_ephemere_expire(): void
    {
        $token = $this->jeton($this->makeUser('technicien'));

        $this->travel(61)->seconds();
        $this->get("/api/v1/proposals/stream?token={$token}")->assertUnauthorized();
    }

    public function test_un_jeton_sanctum_dans_l_url_est_refuse(): void
    {
        $sanctum = $this->makeUser('technicien')->createToken('mobile')->plainTextToken;

        $this->get('/api/v1/proposals/stream?token=' . urlencode($sanctum))->assertUnauthorized();
        $this->get('/api/v1/proposals/stream')->assertUnauthorized();
    }
}
