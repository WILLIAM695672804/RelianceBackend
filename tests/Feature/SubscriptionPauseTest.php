<?php

namespace Tests\Feature;

use App\Modules\Finance\Models\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsWorld;
use Tests\TestCase;

class SubscriptionPauseTest extends TestCase
{
    use RefreshDatabase, BuildsWorld;

    public function test_la_reprise_prolonge_l_abonnement_de_la_duree_de_la_pause(): void
    {
        $this->seedRoles();
        $this->freezeTime();
        $abonnement = Subscription::create([
            'subscriber_id' => $this->makeUser('technicien')->id, 'plan_name' => 'pro', 'status' => 'active',
            'start_date' => now(), 'end_date' => now()->addDays(30), 'recurring' => true,
        ]);
        $finInitiale = $abonnement->end_date->copy();

        $abonnement->pause();
        $this->travel(5)->days();
        $this->travel(6)->hours(); // les jours entamés ne comptent pas
        $abonnement->fresh()->resume();

        $this->assertSame('active', $abonnement->fresh()->status);
        $this->assertSame($finInitiale->addDays(5)->toDateString(), $abonnement->fresh()->end_date->toDateString());
    }
}
