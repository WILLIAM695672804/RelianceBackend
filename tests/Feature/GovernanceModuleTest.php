<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Modules\Auth\Models\User;
use App\Modules\Governance\Models\SlaRule;
use App\Modules\Governance\Models\SlaEvent;
use App\Modules\Governance\Models\AuditLog;
use App\Modules\Governance\Models\AnalyticsEvent;
use App\Modules\Governance\Services\AuditService;
use App\Modules\Governance\Services\AnalyticsService;
use App\Modules\Matching\Models\ClientRequest;

class GovernanceModuleTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $client;
    protected string $adminToken;
    protected string $clientToken;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'RolesAndPermissionsSeeder']);

        $this->admin = User::create([
            'name'     => 'Admin User',
            'email'    => 'admin@test.com',
            'password' => bcrypt('password'),
        ]);
        $this->admin->assignRole('admin');
        $this->admin->markEmailAsVerified();
        $this->adminToken = $this->admin->createToken('test')->plainTextToken;

        $this->client = User::create([
            'name'     => 'Client User',
            'email'    => 'client@test.com',
            'password' => bcrypt('password'),
        ]);
        $this->client->assignRole('client');
        $this->client->markEmailAsVerified();
        $this->clientToken = $this->client->createToken('test')->plainTextToken;
    }

    // ── SLA Rules (admin) ──────────────────────────────────

    public function test_admin_can_create_sla_rule()
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->postJson('/api/v1/sla-rules', [
                'name'          => 'Late Cancellation',
                'event_key'     => 'late_cancellation',
                'penalty_type'  => 'reputation',
                'penalty_value' => 10,
                'description'   => 'Penalty for cancelling less than 2h before appointment',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Late Cancellation')
            ->assertJsonPath('data.event_key', 'late_cancellation')
            ->assertJsonPath('data.active', true);
    }

    public function test_admin_can_list_sla_rules()
    {
        SlaRule::create([
            'name'          => 'No Show',
            'event_key'     => 'no_show',
            'penalty_type'  => 'fixed',
            'penalty_value' => 5000,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->getJson('/api/v1/sla-rules');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_admin_can_toggle_sla_rule()
    {
        $rule = SlaRule::create([
            'name'          => 'No Show',
            'event_key'     => 'no_show',
            'penalty_type'  => 'fixed',
            'penalty_value' => 5000,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->patchJson("/api/v1/sla-rules/{$rule->id}/toggle");

        $response->assertStatus(200)
            ->assertJsonPath('data.active', false);
    }

    public function test_non_admin_cannot_access_sla_rules()
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->clientToken}")
            ->getJson('/api/v1/sla-rules');

        $response->assertStatus(403);
    }

    // ── SLA Events ─────────────────────────────────────────

    public function test_authenticated_user_can_create_sla_event()
    {
        $clientRequest = ClientRequest::create([
            'client_id'   => $this->client->id,
            'title'       => 'Fix my plumbing',
            'description' => 'Leaking pipe',
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->clientToken}")
            ->postJson('/api/v1/sla-events', [
                'request_id' => $clientRequest->id,
                'event_key'  => 'late_cancellation',
                'payload'    => ['reason' => 'Technician did not show up'],
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.event_key', 'late_cancellation');
    }

    // ── Audit Logs ─────────────────────────────────────────

    public function test_audit_service_logs_explicit_action()
    {
        $this->actingAs($this->admin);

        AuditService::log('manual_action', 'TestObject', '00000000-0000-0000-0000-000000000001', ['key' => 'value']);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id'    => $this->admin->id,
            'object_type' => 'TestObject',
            'action'      => 'manual_action',
        ]);
    }

    public function test_audit_observer_logs_model_creation()
    {
        $this->actingAs($this->admin);

        SlaRule::create([
            'name'          => 'Test Rule',
            'event_key'     => 'test_event',
            'penalty_type'  => 'fixed',
            'penalty_value' => 100,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'object_type' => 'SlaRule',
            'action'      => 'created',
        ]);
    }

    public function test_admin_can_list_audit_logs()
    {
        AuditLog::create([
            'actor_id'    => $this->admin->id,
            'object_type' => 'Quote',
            'object_id'   => '00000000-0000-0000-0000-000000000002',
            'action'      => 'created',
            'details'     => ['test' => true],
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->getJson('/api/v1/audit-logs');

        $response->assertStatus(200);
    }

    public function test_non_admin_cannot_access_audit_logs()
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->clientToken}")
            ->getJson('/api/v1/audit-logs');

        $response->assertStatus(403);
    }

    // ── Analytics ──────────────────────────────────────────

    public function test_analytics_service_tracks_event()
    {
        AnalyticsService::track('test_event', $this->client->id, ['page' => '/home']);

        $this->assertDatabaseHas('analytics_events', [
            'user_id'    => $this->client->id,
            'event_name' => 'test_event',
        ]);
    }

    public function test_authenticated_user_can_track_event_via_api()
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->clientToken}")
            ->postJson('/api/v1/analytics/track', [
                'event_name'  => 'page_view',
                'event_props' => ['page' => '/dashboard'],
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.event_name', 'page_view');
    }

    public function test_admin_can_view_analytics_summary()
    {
        AnalyticsService::track('page_view', $this->client->id);
        AnalyticsService::track('page_view', $this->admin->id);
        AnalyticsService::track('button_click', $this->client->id);

        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->getJson('/api/v1/analytics/summary');

        $response->assertStatus(200)
            ->assertJsonStructure(['period_days', 'top_events', 'active_users', 'events_per_day']);
    }

    public function test_non_admin_cannot_access_analytics_index()
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->clientToken}")
            ->getJson('/api/v1/analytics');

        $response->assertStatus(403);
    }

    // ── Middleware tracking ─────────────────────────────────

    public function test_api_requests_are_tracked_by_middleware()
    {
        $clientRequest = ClientRequest::create([
            'client_id'   => $this->client->id,
            'title'       => 'Test middleware tracking',
            'description' => 'Testing',
        ]);

        $this->withHeader('Authorization', "Bearer {$this->clientToken}")
            ->getJson("/api/v1/sla-events/request/{$clientRequest->id}");

        $this->assertDatabaseHas('analytics_events', [
            'event_name' => 'api_request',
            'user_id'    => $this->client->id,
        ]);
    }
}
