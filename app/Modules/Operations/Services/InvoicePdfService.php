<?php

namespace App\Modules\Operations\Services;

use App\Modules\Finance\Services\PlatformFeeService;
use App\Modules\Operations\Models\Invoice;
use App\Support\SimplePdf;

/** Génère la facture au format PDF (référence, parties, lignes du devis, frais d'intervention, statut). */
class InvoicePdfService
{
    private const STATUTS = ['unpaid' => 'À payer', 'paid' => 'Payée', 'cancelled' => 'Annulée', 'refunded' => 'Remboursée'];

    private function xaf(float $montant): string
    {
        return number_format($montant, 0, ',', ' ') . ' XAF';
    }

    public function render(Invoice $invoice): string
    {
        $invoice->loadMissing(['quote', 'client', 'issuer']);
        $frais = app(PlatformFeeService::class)->breakdown((float) $invoice->amount, 'job');
        $pdf = new SimplePdf();

        $pdf->rectangle(0, 0, 595.28, 80, 0.12);
        $pdf->ligne(40, 52, 'RELIANCE', 22, true);
        $pdf->droite(555, 52, 'FACTURE', 18, true);

        $pdf->ligne(40, 115, 'Référence : ' . $invoice->reference, 11, true);
        $pdf->ligne(40, 132, 'Date d\'émission : ' . $invoice->created_at?->format('d/m/Y'), 10);
        $pdf->ligne(40, 147, 'Statut : ' . (self::STATUTS[$invoice->status] ?? $invoice->status), 10);

        $pdf->ligne(320, 115, 'Facturé à', 10, true);
        $pdf->ligne(320, 130, (string) ($invoice->client->name ?? '—'), 10);
        $pdf->ligne(320, 145, (string) ($invoice->client->email ?? ''), 9);
        $pdf->ligne(320, 168, 'Prestataire', 10, true);
        $pdf->ligne(320, 183, (string) ($invoice->issuer->name ?? '—'), 10);

        // Tableau des lignes
        $pdf->rectangle(40, 215, 515, 20, 0.88);
        $pdf->ligne(48, 229, 'Désignation', 10, true);
        $pdf->droite(547, 229, 'Montant', 10, true);
        $y = 253;
        $lignes = $invoice->quote?->breakdown ?? [];
        if (!$lignes) {
            $lignes = [['label' => 'Prestation technique', 'amount' => (float) $invoice->amount]];
        }
        foreach ($lignes as $l) {
            $pdf->ligne(48, $y, (string) ($l['label'] ?? 'Ligne'), 10);
            $pdf->droite(547, $y, $this->xaf((float) ($l['amount'] ?? 0)), 10);
            $y += 18;
        }
        $pdf->filet(40, $y, 555);

        $y += 24;
        $pdf->ligne(300, $y, 'Total TTC', 12, true);
        $pdf->droite(547, $y, $this->xaf((float) $invoice->amount), 12, true);
        if ((float) $invoice->tax_amount > 0) {
            $y += 18;
            $pdf->ligne(300, $y, 'dont taxes', 10);
            $pdf->droite(547, $y, $this->xaf((float) $invoice->tax_amount), 10);
        }

        // Frais d'intervention (transparence, cahier des charges §4.4)
        $y += 36;
        $pdf->ligne(40, $y, 'Frais d\'intervention de la plateforme', 10, true);
        $y += 16;
        $pdf->ligne(48, $y, 'Commission Reliance prélevée sur la prestation', 9);
        $pdf->droite(547, $y, $this->xaf($frais['fees']), 9);
        $y += 14;
        $pdf->ligne(48, $y, 'Net reversé au prestataire', 9);
        $pdf->droite(547, $y, $this->xaf($frais['net']), 9);

        $pdf->filet(40, 790, 555, 0.4);
        $pdf->ligne(40, 806, 'Reliance — plateforme de mise en relation entre clients et techniciens. Paiement sécurisé par séquestre.', 8);

        return $pdf->render();
    }
}
