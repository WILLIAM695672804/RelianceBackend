<?php

namespace Tests\Feature;

use App\Modules\Kyc\Models\KycDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsWorld;
use Tests\TestCase;

class KycTest extends TestCase
{
    use RefreshDatabase, BuildsWorld;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        Storage::fake('public');
        Storage::fake('local');
    }

    private function deposer($user, string $type = 'cni', ?UploadedFile $fichier = null)
    {
        return $this->actingAs($user, 'sanctum')->post('/api/v1/auth/kyc/upload', [
            'type' => $type,
            'document' => $fichier ?? UploadedFile::fake()->image('cni.jpg'),
        ], ['Accept' => 'application/json']);
    }

    public function test_un_technicien_depose_un_document_en_attente_et_le_retrouve(): void
    {
        $tech = $this->makeUser('technicien');

        $reponse = $this->deposer($tech)->assertSuccessful();

        $this->assertSame('pending', $reponse->json('document.status'));
        Storage::disk('local')->assertExists(KycDocument::firstOrFail()->file_path);
        $mes = $this->actingAs($tech, 'sanctum')->getJson('/api/v1/auth/kyc/mine')->assertOk()->json();
        $this->assertCount(1, $mes);
    }

    public function test_les_documents_d_un_utilisateur_ne_sont_pas_visibles_d_un_autre(): void
    {
        $this->deposer($this->makeUser('technicien'));

        $this->assertCount(0, $this->actingAs($this->makeUser('technicien'), 'sanctum')->getJson('/api/v1/auth/kyc/mine')->json());
    }

    public function test_le_depot_refuse_un_type_de_fichier_ou_une_taille_invalides(): void
    {
        $tech = $this->makeUser('technicien');

        $this->deposer($tech, 'cni', UploadedFile::fake()->create('virus.exe', 10))->assertStatus(422);
        $this->deposer($tech, 'cni', UploadedFile::fake()->create('lourd.pdf', 6000, 'application/pdf'))->assertStatus(422);
        $this->actingAs($tech, 'sanctum')->postJson('/api/v1/auth/kyc/upload', ['type' => 'cni'])->assertStatus(422);
        $this->assertSame(0, KycDocument::count());
    }

    public function test_l_administrateur_filtre_les_documents_par_statut_et_par_recherche(): void
    {
        $alice = $this->makeUser('technicien', ['name' => 'Alice Plombier']);
        $bob = $this->makeUser('technicien', ['name' => 'Bob Electricien']);
        $this->deposer($alice);
        $this->deposer($bob);
        KycDocument::where('user_id', $bob->id)->update(['status' => 'approved']);
        $admin = $this->actingAs($this->makeUser('admin'), 'sanctum');

        $this->assertCount(1, $admin->getJson('/api/v1/auth/kyc/admin/documents?status=pending')->json('data'));
        $this->assertCount(1, $admin->getJson('/api/v1/auth/kyc/admin/documents?search=bob')->json('data'));
        $this->assertCount(2, $admin->getJson('/api/v1/auth/kyc/admin/documents')->json('data'));
    }

    public function test_seul_l_administrateur_valide_ou_liste(): void
    {
        $tech = $this->makeUser('technicien');
        $this->deposer($tech);
        $doc = KycDocument::firstOrFail();

        $this->actingAs($tech, 'sanctum')->postJson("/api/v1/auth/kyc/{$doc->id}/validate", ['status' => 'approved'])->assertForbidden();
        $this->actingAs($tech, 'sanctum')->getJson('/api/v1/auth/kyc/admin/documents')->assertForbidden();
        $this->assertSame('pending', $doc->fresh()->status);
    }

    public function test_l_approbation_enregistre_le_validateur(): void
    {
        $tech = $this->makeUser('technicien');
        $admin = $this->makeUser('admin');
        $this->deposer($tech);
        $doc = KycDocument::firstOrFail();

        $this->actingAs($admin, 'sanctum')->postJson("/api/v1/auth/kyc/{$doc->id}/validate", ['status' => 'approved'])->assertOk();

        $this->assertSame('approved', $doc->fresh()->status);
        $this->assertSame($admin->id, $doc->fresh()->validated_by);
    }

    public function test_un_rejet_exige_un_motif(): void
    {
        $tech = $this->makeUser('technicien');
        $adminUser = $this->makeUser('admin');
        $this->deposer($tech);
        $doc = KycDocument::firstOrFail();
        $admin = $this->actingAs($adminUser, 'sanctum'); // après le dépôt, qui change l'utilisateur courant

        $admin->postJson("/api/v1/auth/kyc/{$doc->id}/validate", ['status' => 'rejected'])->assertStatus(422);
        $this->assertSame('pending', $doc->fresh()->status);

        $admin->postJson("/api/v1/auth/kyc/{$doc->id}/validate", ['status' => 'rejected', 'rejection_reason' => 'Photo illisible'])->assertOk();
        $this->assertSame('Photo illisible', $doc->fresh()->rejection_reason);
    }

    public function test_un_statut_inconnu_est_refuse(): void
    {
        $this->deposer($this->makeUser('technicien'));
        $doc = KycDocument::firstOrFail();

        $this->actingAs($this->makeUser('admin'), 'sanctum')
            ->postJson("/api/v1/auth/kyc/{$doc->id}/validate", ['status' => 'pending'])->assertStatus(422);
    }

    // ── Confidentialité des pièces ──────────────────────────

    public function test_la_piece_est_stockee_hors_du_disque_public(): void
    {
        $this->deposer($this->makeUser('technicien'))->assertSuccessful();

        $chemin = KycDocument::firstOrFail()->file_path;
        Storage::disk('local')->assertExists($chemin);
        Storage::disk('public')->assertMissing($chemin);
    }

    public function test_l_url_signee_donne_acces_au_fichier_sans_authentification(): void
    {
        $url = $this->deposer($this->makeUser('technicien'))->json('document.url');

        $this->app['auth']->forgetGuards();
        $reponse = $this->get($url)->assertOk();
        $this->assertStringContainsString('no-store', $reponse->headers->get('Cache-Control'));
    }

    public function test_une_url_alteree_ou_expiree_est_refusee(): void
    {
        $this->deposer($this->makeUser('technicien'));
        $doc = KycDocument::firstOrFail();
        $url = $doc->temporaryUrl();

        $this->get(preg_replace('/signature=[0-9a-f]+/', 'signature=' . str_repeat('0', 64), $url))->assertForbidden();
        $this->get("/api/v1/auth/kyc/documents/{$doc->id}/fichier")->assertForbidden();

        $this->travel(11)->minutes();
        $this->get($url)->assertForbidden();
    }

    public function test_une_signature_ne_vaut_que_pour_son_document(): void
    {
        $this->deposer($this->makeUser('technicien'));
        $this->deposer($this->makeUser('technicien'));
        [$a, $b] = KycDocument::orderBy('created_at')->get()->all();

        $this->get(str_replace($a->id, $b->id, $a->temporaryUrl()))->assertForbidden();
    }

    public function test_la_commande_deplace_les_pieces_existantes_vers_le_disque_prive(): void
    {
        $tech = $this->makeUser('technicien');
        Storage::disk('public')->put('kyc/ancien.jpg', 'contenu');
        KycDocument::create(['user_id' => $tech->id, 'type' => 'cni', 'file_path' => 'kyc/ancien.jpg', 'status' => 'pending']);

        $this->artisan('kyc:privatiser-stockage')->assertSuccessful();
        $this->artisan('kyc:privatiser-stockage')->assertSuccessful(); // idempotente

        Storage::disk('public')->assertMissing('kyc/ancien.jpg');
        $this->assertSame('contenu', Storage::disk('local')->get('kyc/ancien.jpg'));
    }
}
