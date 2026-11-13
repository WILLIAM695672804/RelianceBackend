<?php

namespace Tests\Feature;

use App\Modules\Operations\Models\Appointment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsWorld;
use Tests\TestCase;

/** Les dates écrites par l'application et celles calculées par PostgreSQL doivent désigner le même instant. */
class TimezoneTest extends TestCase
{
    use RefreshDatabase, BuildsWorld;

    public function test_la_session_postgresql_est_en_utc(): void
    {
        $this->assertSame('UTC', DB::selectOne('show timezone')->TimeZone);
    }

    public function test_une_date_ecrite_par_eloquent_correspond_a_l_instant_vu_par_la_base(): void
    {
        $this->seedRoles();
        $tech = $this->makeUser('technicien');
        $client = $this->makeUser('client');
        $request = $this->actingAs($client, 'sanctum');
        $demande = $this->makeRequestFor($client);
        $rdv = Appointment::create(['request_id' => $demande->id, 'technician_id' => $tech->id, 'status' => 'confirmed', 'scheduled_start' => now()]);

        $ecart = DB::selectOne('select abs(extract(epoch from (scheduled_start - now()))) as secondes from appointments where id = ?', [$rdv->id])->secondes;

        $this->assertLessThan(10, (float) $ecart, 'Écart anormal entre l\'instant écrit par Eloquent et now() de la base.');
        $this->assertLessThan(10, abs($rdv->fresh()->scheduled_start->diffInSeconds(now(), false)));
    }
}
