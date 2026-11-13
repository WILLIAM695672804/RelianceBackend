<?php

namespace Tests\Feature;

use App\Modules\Social\Models\MessageAttachment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsWorld;
use Tests\TestCase;

class MessageAttachmentTest extends TestCase
{
    use RefreshDatabase, BuildsWorld;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        Storage::fake('public');
        Storage::fake('local');
    }

    /** @return array{0: string, 1: \App\Modules\Auth\Models\User} identifiant de conversation et expéditeur */
    private function envoyerPieceJointe(): array
    {
        $client = $this->makeUser('client');
        $tech = $this->makeUser('technicien');
        $conv = $this->actingAs($client, 'sanctum')->postJson('/api/v1/conversations', [
            'members' => [$tech->id], 'subject' => 'Devis', 'initial_message' => 'Bonjour',
        ])->assertSuccessful()->json('data.id');

        $this->actingAs($client, 'sanctum')->post("/api/v1/conversations/{$conv}/messages", [
            'attachments' => [UploadedFile::fake()->image('facture.jpg')],
        ], ['Accept' => 'application/json'])->assertCreated();

        return [$conv, $client];
    }

    public function test_la_piece_jointe_est_stockee_hors_du_disque_public(): void
    {
        $this->envoyerPieceJointe();

        $chemin = MessageAttachment::sole()->file_path;
        Storage::disk('local')->assertExists($chemin);
        Storage::disk('public')->assertMissing($chemin);
    }

    public function test_l_url_signee_donne_acces_au_fichier_et_expire(): void
    {
        $this->envoyerPieceJointe();
        $url = MessageAttachment::sole()->url;

        $this->app['auth']->forgetGuards();
        $this->get($url)->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');

        $this->travel(61)->minutes();
        $this->get($url)->assertForbidden();
    }

    public function test_une_signature_kyc_ne_vaut_pas_pour_une_piece_jointe(): void
    {
        $this->envoyerPieceJointe();
        $piece = MessageAttachment::sole();
        $url = \App\Support\SignedFileUrl::make('messagerie.piece-jointe', 'kyc', $piece->id, 10);

        $this->get($url)->assertForbidden();
        $this->get("/api/v1/attachments/{$piece->id}/fichier")->assertForbidden();
    }

    public function test_la_suppression_du_message_efface_le_fichier_prive(): void
    {
        [$conv, $client] = $this->envoyerPieceJointe();
        $piece = MessageAttachment::sole();

        $this->actingAs($client, 'sanctum')->deleteJson("/api/v1/conversations/{$conv}/messages/{$piece->message_id}")->assertOk();

        Storage::disk('local')->assertMissing($piece->file_path);
    }

    public function test_la_commande_privatise_aussi_les_pieces_jointes(): void
    {
        $this->envoyerPieceJointe();
        $piece = MessageAttachment::sole();
        Storage::disk('public')->put($piece->file_path, Storage::disk('local')->get($piece->file_path));
        Storage::disk('local')->delete($piece->file_path);

        $this->artisan('stockage:privatiser')->assertSuccessful();

        Storage::disk('public')->assertMissing($piece->file_path);
        Storage::disk('local')->assertExists($piece->file_path);
    }
}
