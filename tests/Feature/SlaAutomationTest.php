<?php

namespace Tests\Feature;

use App\Modules\Gamification\Models\ReputationChange;
use App\Modules\Governance\Models\SlaEvent;
use App\Modules\Governance\Models\SlaRule;
use App\Modules\Governance\Services\SlaDetectionService;
use App\Modules\Notification\Models\Notification;
use App\Modules\Operations\Models\Appointment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsWorld;
use Tests\TestCase;

/** Pénalités SLA constatées automatiquement (retard, annulation, absence). */
class SlaAutomationTest extends TestCase
{
    use RefreshDatabase, BuildsWorld;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    /** @return array{0: \App\Modules\Auth\Models\User, 1: \App\Modules\Auth\Models\User, 2: \App\Modules\Matching\Models\ClientRequest, 3: Appointment} */
    private function mission(int $minutesAvant): array
    {
        $client = $this->makeUser('client');
        $tech = $this->makeTechnician();
        [$request] = $this->assignedMission($client, $tech);
        $rdv = Appointment::create([
            'request_id' => $request->id, 'technician_id' => $tech->id, 'status' => 'confirmed',
            'scheduled_start' => now()->subMinutes($minutesAvant),
        ]);

        return [$client, $tech, $request, $rdv];
    }

    private function job($tech, $request, ?Appointment $rdv = null): string
    {
        return $this->actingAs($tech, 'sanctum')->postJson('/api/v1/jobs', ['request_id' => $request->id, 'appointment_id' => $rdv?->id])->json('data.id');
    }

    private function statut($tech, string $job, string $statut): void
    {
        $this->actingAs($tech, 'sanctum')->patchJson("/api/v1/jobs/{$job}/status", ['status' => $statut])->assertSuccessful();
    }

    public function test_un_retard_superieur_a_la_tolerance_est_penalise_et_notifie(): void
    {
        [, $tech, $request, $rdv] = $this->mission(40);
        $job = $this->job($tech, $request, $rdv);

        $this->statut($tech, $job, 'en_route');
        $this->statut($tech, $job, 'arrived');

        $event = SlaEvent::where('event_key', 'late_arrival')->firstOrFail();
        $this->assertGreaterThanOrEqual(40, $event->payload['minutes_late']);
        $this->assertSame(-3, (int) ReputationChange::where('user_id', $tech->id)->sum('change'));
        $this->assertSame(1, Notification::where('user_id', $tech->id)->where('title', 'Pénalité de service')->count());
    }

    public function test_un_retard_dans_la_tolerance_ou_sans_rendez_vous_n_est_pas_penalise(): void
    {
        [, $tech, $request, $rdv] = $this->mission(5);
        $job = $this->job($tech, $request, $rdv);
        $this->statut($tech, $job, 'arrived');
        $this->assertSame(0, SlaEvent::count());

        [, $tech2, $request2] = $this->mission(90);
        $job2 = $this->job($tech2, $request2, null); // mission sans rendez-vous rattaché
        $this->statut($tech2, $job2, 'arrived');
        $this->assertSame(0, SlaEvent::count());
    }

    public function test_un_manquement_n_est_constate_qu_une_fois_par_mission(): void
    {
        [, $tech, $request, $rdv] = $this->mission(40);
        $job = $this->job($tech, $request, $rdv);

        foreach (['arrived', 'en_route', 'arrived'] as $s) {
            $this->statut($tech, $job, $s);
        }

        $this->assertSame(1, SlaEvent::where('event_key', 'late_arrival')->count());
        $this->assertSame(-3, (int) ReputationChange::where('user_id', $tech->id)->sum('change'));
    }

    public function test_l_annulation_par_le_technicien_est_penalisee(): void
    {
        [, $tech, $request] = $this->mission(0);
        $job = $this->job($tech, $request);

        $this->statut($tech, $job, 'cancelled');

        $this->assertSame(1, SlaEvent::where('event_key', 'technician_cancellation')->count());
        $this->assertSame(-10, (int) ReputationChange::where('user_id', $tech->id)->sum('change'));
    }

    public function test_une_regle_desactivee_enregistre_l_evenement_sans_penalite(): void
    {
        SlaRule::where('event_key', 'technician_cancellation')->update(['active' => false]);
        [, $tech, $request] = $this->mission(0);
        $job = $this->job($tech, $request);

        $this->statut($tech, $job, 'cancelled');

        $this->assertSame(1, SlaEvent::where('event_key', 'technician_cancellation')->count());
        $this->assertSame(0, ReputationChange::where('user_id', $tech->id)->count());
    }

    public function test_une_absence_au_rendez_vous_est_constatee_une_seule_fois(): void
    {
        [, $tech, , $rdv] = $this->mission(90);

        $this->assertSame(1, app(SlaDetectionService::class)->detectNoShows());
        $this->assertSame(0, app(SlaDetectionService::class)->detectNoShows());

        $this->assertSame('no_show', $rdv->fresh()->status);
        $this->assertSame(-15, (int) ReputationChange::where('user_id', $tech->id)->sum('change'));
    }

    public function test_pas_d_absence_si_le_technicien_s_est_mis_en_route_ou_si_le_delai_n_est_pas_ecoule(): void
    {
        [, $tech, $request, $rdv] = $this->mission(90);
        $job = $this->job($tech, $request, $rdv);
        $this->statut($tech, $job, 'en_route');
        $this->mission(30); // rendez-vous trop récent

        $this->assertSame(0, app(SlaDetectionService::class)->detectNoShows());
        $this->assertSame(0, SlaEvent::count());
    }

    public function test_la_commande_planifiee_detecte_les_absences(): void
    {
        $this->mission(120);

        $this->artisan('sla:detect-no-shows')->expectsOutput('1 absence(s) constatée(s).')->assertExitCode(0);
    }
}
