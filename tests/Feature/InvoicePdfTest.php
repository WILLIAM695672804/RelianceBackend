<?php

namespace Tests\Feature;

use App\Modules\Finance\Models\PlatformFee;
use App\Modules\Operations\Models\Invoice;
use App\Support\SimplePdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsWorld;
use Tests\TestCase;

class InvoicePdfTest extends TestCase
{
    use RefreshDatabase, BuildsWorld;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        Storage::fake('local');
    }

    private function facture(): array
    {
        PlatformFee::create(['name' => 'Commission', 'fee_type' => 'percentage', 'value' => 0.10, 'applies_to' => 'job', 'active' => true]);
        $client = $this->makeUser('client');
        $tech = $this->makeTechnician();
        [$request] = $this->assignedMission($client, $tech);
        $quoteId = $this->sentQuote($tech, $request, 12000);
        $id = $this->actingAs($client, 'sanctum')->postJson("/api/v1/quotes/{$quoteId}/accept")->json('data.invoice_id');

        return [$client, $tech, Invoice::findOrFail($id)];
    }

    public function test_le_generateur_produit_un_pdf_valide_avec_accents(): void
    {
        $pdf = (new SimplePdf())->ligne(40, 60, 'Facture déjà réglée (12 000 €)', 12, true)->filet(40, 70, 300)->rectangle(40, 80, 100, 20)->render();

        $this->assertStringStartsWith('%PDF-1.4', $pdf);
        $this->assertStringEndsWith('%%EOF', $pdf);
        $this->assertStringContainsString('/Helvetica-Bold', $pdf);
        // La table de références pointe bien vers chaque objet.
        preg_match('/startxref\n(\d+)/', $pdf, $m);
        $this->assertStringStartsWith('xref', substr($pdf, (int) $m[1], 4));
    }

    public function test_le_client_et_le_prestataire_telechargent_la_facture_en_pdf(): void
    {
        [$client, $tech, $invoice] = $this->facture();

        foreach ([$client, $tech] as $user) {
            $r = $this->actingAs($user, 'sanctum')->get("/api/v1/invoices/{$invoice->id}/pdf");
            $r->assertOk();
            $this->assertSame('application/pdf', $r->headers->get('Content-Type'));
            $this->assertStringStartsWith('%PDF-1.4', $r->getContent());
            $this->assertStringContainsString('attachment; filename="facture-', $r->headers->get('Content-Disposition'));
        }
    }

    public function test_le_pdf_contient_la_reference_les_lignes_et_les_frais(): void
    {
        [$client, , $invoice] = $this->facture();

        $contenu = $this->actingAs($client, 'sanctum')->get("/api/v1/invoices/{$invoice->id}/pdf")->getContent();

        $this->assertStringContainsString($invoice->reference, $contenu);
        $this->assertStringContainsString("Main d'oeuvre", $contenu);
        $this->assertStringContainsString('12 000 XAF', $contenu);
        $this->assertStringContainsString('1 200 XAF', $contenu, 'Commission de 10 % affichée.');
        $this->assertStringContainsString('10 800 XAF', $contenu, 'Net du prestataire affiché.');
    }

    public function test_le_pdf_est_archive_et_sa_reference_enregistree(): void
    {
        [$client, , $invoice] = $this->facture();

        $this->actingAs($client, 'sanctum')->get("/api/v1/invoices/{$invoice->id}/pdf")->assertOk();

        $ref = $invoice->fresh()->external_pdf_ref;
        $this->assertNotEmpty($ref);
        Storage::disk('local')->assertExists($ref);
    }

    public function test_un_tiers_ne_peut_pas_telecharger_la_facture_mais_l_administrateur_le_peut(): void
    {
        [, , $invoice] = $this->facture();

        $this->actingAs($this->makeUser('client'), 'sanctum')->get("/api/v1/invoices/{$invoice->id}/pdf")->assertForbidden();
        $this->actingAs($this->makeUser('admin'), 'sanctum')->get("/api/v1/invoices/{$invoice->id}/pdf")->assertOk();
    }
}
