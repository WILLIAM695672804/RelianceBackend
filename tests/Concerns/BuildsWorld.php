<?php

namespace Tests\Concerns;

use App\Modules\Auth\Models\User;
use App\Modules\Finance\Models\Wallet;
use App\Modules\Matching\Models\ClientRequest;
use App\Modules\Matching\Models\Proposal;
use App\Modules\Profile\Models\Location;
use App\Modules\Profile\Models\Skill;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Fabrique de fixtures partagée par les tests fonctionnels : rôles, utilisateurs vérifiés,
 * portefeuilles, technicien géolocalisé, demande et proposition. Aucune donnée personnelle.
 */
trait BuildsWorld
{
    protected function seedRoles(): void
    {
        $this->artisan('db:seed', ['--class' => 'RolesAndPermissionsSeeder']);
    }

    protected function makeUser(string $role, array $attrs = []): User
    {
        static $n = 0;
        $n++;

        $user = User::create([
            'name' => ucfirst($role) . " Test {$n}",
            'email' => "{$role}.{$n}." . Str::random(4) . '@test.local',
            'password' => bcrypt('Passw0rd!123'),
        ]);
        $user->assignRole($role);
        $user->forceFill(array_merge([
            'email_verified_at' => now(),
            'verified' => true,
            'is_active' => true,
        ], $attrs))->save();

        return $user->fresh();
    }

    protected function fund(User $user, float $amount): Wallet
    {
        $wallet = Wallet::firstOrCreate(
            ['user_id' => $user->id, 'currency' => 'XAF'],
            ['id' => (string) Str::uuid(), 'balance' => 0]
        );
        $wallet->forceFill(['balance' => $amount])->save();

        return $wallet->fresh();
    }

    protected function balanceOf(User $user): float
    {
        return (float) Wallet::xaf()->where('user_id', $user->id)->value('balance');
    }

    protected function plumbingSkill(): Skill
    {
        return Skill::firstOrCreate(
            ['slug' => 'plomberie-generale'],
            ['name' => 'Plomberie générale', 'category' => 'plomberie']
        );
    }

    /** Technicien vérifié, compétent en plomberie, géolocalisé à Douala (rayon par défaut 50 km, surchargeable via `meta.radius` de la localisation). */
    protected function makeTechnician(array $locationMeta = [], float $lng = 9.7700, float $lat = 4.0500): User
    {
        $tech = $this->makeUser('technicien', ['rating' => 4.5]);
        $tech->skills()->attach($this->plumbingSkill()->id, [
            'proficiency' => 80, 'hourly_rate' => 5000, 'travel_fee' => 1000,
        ]);
        $location = Location::create([
            'user_id' => $tech->id, 'label' => 'Zone', 'address' => 'Akwa', 'is_default' => true,
            'meta' => $locationMeta ?: null,
        ]);
        DB::statement(
            'UPDATE locations SET geom = ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography WHERE id = ?',
            [$lng, $lat, $location->id]
        );

        return $tech;
    }

    protected function makeRequestFor(User $client, array $attrs = []): ClientRequest
    {
        $this->plumbingSkill();

        $response = $this->actingAs($client, 'sanctum')->post('/api/v1/requests', array_merge([
            'title' => 'Fuite sous évier',
            'description' => "Une fuite importante sous l'évier de la cuisine.",
            'category_slug' => 'plomberie-generale',
            'latitude' => 4.0511,
            'longitude' => 9.7679,
            'address_label' => 'Bonapriso',
        ], $attrs), ['Accept' => 'application/json']);
        $response->assertSuccessful();

        return ClientRequest::findOrFail($response->json('data.id'));
    }

    /** Demande dont la proposition du technicien est acceptée (mission assignée). */
    protected function assignedMission(User $client, User $tech, float $price = 9000): array
    {
        $request = $this->makeRequestFor($client);

        $proposalId = $this->actingAs($client, 'sanctum')
            ->postJson("/api/v1/requests/{$request->id}/select-technician", [
                'technician_id' => $tech->id, 'price' => $price,
            ])->assertSuccessful()->json('data.id');

        $this->actingAs($tech, 'sanctum')
            ->postJson("/api/v1/proposals/{$proposalId}/accept")->assertSuccessful();

        return [$request->fresh(), Proposal::findOrFail($proposalId)];
    }

    /** Devis envoyé par le technicien mandaté ; renvoie l'identifiant du devis. */
    protected function sentQuote(User $tech, ClientRequest $request, float $total = 12000): string
    {
        $quoteId = $this->actingAs($tech, 'sanctum')->postJson('/api/v1/quotes', [
            'request_id' => $request->id,
            'total_amount' => $total,
            'breakdown' => [['label' => "Main d'oeuvre", 'amount' => $total]],
        ])->assertSuccessful()->json('data.id');

        $this->actingAs($tech, 'sanctum')->postJson("/api/v1/quotes/{$quoteId}/send")->assertSuccessful();

        return $quoteId;
    }
}
