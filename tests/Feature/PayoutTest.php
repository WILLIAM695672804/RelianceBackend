<?php

namespace Tests\Feature;

use App\Modules\Finance\Models\Hold;
use App\Modules\Finance\Models\Payment;
use App\Modules\Finance\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsWorld;
use Tests\TestCase;

/**
 * Retraits : demande du technicien, puis approbation ou refus par l'administrateur.
 */
class PayoutTest extends TestCase
{
    use RefreshDatabase, BuildsWorld;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    private function demande($tech, float $amount = 5000, string $method = 'momo'): string
    {
        return $this->actingAs($tech, 'sanctum')->postJson('/api/v1/wallet/withdraw', [
            'amount' => $amount, 'method' => $method, 'phone_number' => '+237600000000',
        ])->assertSuccessful()->json('payment_id');
    }

    public function test_la_demande_de_retrait_ne_debite_pas_avant_approbation(): void
    {
        $tech = $this->makeUser('technicien');
        $this->fund($tech, 20000);

        $id = $this->demande($tech, 5000);

        $this->assertSame('pending', Payment::findOrFail($id)->status);
        $this->assertEqualsWithDelta(20000, $this->balanceOf($tech), 0.01);
    }

    public function test_validation_de_la_demande(): void
    {
        $tech = $this->makeUser('technicien');
        $this->fund($tech, 20000);
        $post = fn (array $d) => $this->actingAs($tech, 'sanctum')->postJson('/api/v1/wallet/withdraw', $d);

        $post(['amount' => 100, 'method' => 'momo', 'phone_number' => '+237600000000'])->assertStatus(422);       // sous le minimum
        $post(['amount' => 5000, 'method' => 'cheque'])->assertStatus(422);                                        // méthode inconnue
        $post(['amount' => 5000, 'method' => 'momo'])->assertStatus(422);                                           // téléphone requis
        $post(['amount' => 50000, 'method' => 'bank_transfer'])->assertStatus(422);                                 // solde insuffisant
        $this->assertSame(0, Payment::count());
    }

    public function test_les_fonds_sequestres_ne_sont_pas_retirables(): void
    {
        $tech = $this->makeUser('technicien');
        $wallet = $this->fund($tech, 10000);
        Hold::create(['wallet_id' => $wallet->id, 'amount' => 8000, 'reason' => 'x', 'status' => 'held']);

        $this->actingAs($tech, 'sanctum')->postJson('/api/v1/wallet/withdraw', [
            'amount' => 5000, 'method' => 'bank_transfer',
        ])->assertStatus(422);
    }

    public function test_l_approbation_debite_une_seule_fois_et_trace_la_transaction(): void
    {
        $tech = $this->makeUser('technicien');
        $admin = $this->makeUser('admin');
        $this->fund($tech, 20000);
        $id = $this->demande($tech, 5000);

        $this->actingAs($admin, 'sanctum')->postJson("/api/v1/admin/payouts/{$id}/approve")->assertSuccessful();

        $this->assertEqualsWithDelta(15000, $this->balanceOf($tech), 0.01);
        $this->assertSame('settled', Payment::findOrFail($id)->status);
        $this->assertSame(1, Transaction::where('related_payment', $id)->count());

        // Idempotence : un retrait déjà traité n'est plus approuvable.
        $this->actingAs($admin, 'sanctum')->postJson("/api/v1/admin/payouts/{$id}/approve")->assertNotFound();
        $this->assertEqualsWithDelta(15000, $this->balanceOf($tech), 0.01);
    }

    public function test_l_approbation_est_refusee_si_le_solde_a_diminue_entre_temps(): void
    {
        $tech = $this->makeUser('technicien');
        $admin = $this->makeUser('admin');
        $wallet = $this->fund($tech, 6000);
        $premier = $this->demande($tech, 5000);
        $second = $this->demande($tech, 5000); // les demandes ne réservent pas les fonds

        $this->actingAs($admin, 'sanctum')->postJson("/api/v1/admin/payouts/{$premier}/approve")->assertSuccessful();
        $this->actingAs($admin, 'sanctum')->postJson("/api/v1/admin/payouts/{$second}/approve")->assertStatus(422);

        $this->assertEqualsWithDelta(1000, $this->balanceOf($tech), 0.01, 'Aucun découvert possible.');
        $this->assertSame('pending', Payment::findOrFail($second)->status);
    }

    public function test_le_refus_ne_debite_pas_et_clot_la_demande(): void
    {
        $tech = $this->makeUser('technicien');
        $admin = $this->makeUser('admin');
        $this->fund($tech, 20000);
        $id = $this->demande($tech, 5000);

        $this->actingAs($admin, 'sanctum')->postJson("/api/v1/admin/payouts/{$id}/reject", ['reason' => 'Pièce manquante'])->assertSuccessful();

        $this->assertSame('rejected', Payment::findOrFail($id)->status);
        $this->assertEqualsWithDelta(20000, $this->balanceOf($tech), 0.01);
        $this->actingAs($admin, 'sanctum')->postJson("/api/v1/admin/payouts/{$id}/approve")->assertNotFound();
    }

    public function test_les_retraits_en_attente_sont_listes_pour_l_administrateur_seulement(): void
    {
        $tech = $this->makeUser('technicien');
        $this->fund($tech, 20000);
        $this->demande($tech, 5000);

        $this->actingAs($tech, 'sanctum')->getJson('/api/v1/admin/payouts/pending')->assertForbidden();
        $this->actingAs($this->makeUser('admin'), 'sanctum')->getJson('/api/v1/admin/payouts/pending')->assertOk();
        $this->actingAs($this->makeUser('admin'), 'sanctum')->getJson('/api/v1/admin/payouts/stats')->assertOk();
    }
}
