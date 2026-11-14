<?php

namespace Tests\Unit;

use App\Modules\Finance\Services\PaymentGateway\NotchPayGateway;
use App\Modules\Finance\Services\PaymentGateway\PaymentGatewayFactory;
use App\Modules\Finance\Services\PaymentGateway\SimulationGateway;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * La passerelle de simulation, utilisée en préproduction.
 *
 * Ce qu'il faut absolument vérifier : qu'elle confirme les paiements qu'elle a
 * elle-même initiés — et seulement ceux-là —, qu'elle respecte le délai
 * d'attente, et que le serveur ne tombe jamais dessus sans l'avoir demandé.
 */
class SimulationGatewayTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Aucune requête ne doit sortir : la simulation remplace NotchPay,
        // elle ne l'appelle pas.
        Http::preventStrayRequests();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_un_paiement_part_en_attente_comme_un_push_ussd(): void
    {
        $resultat = (new SimulationGateway('momo'))
            ->initiate(5000.0, 'XAF', '+237600000000');

        $this->assertTrue($resultat['success']);
        $this->assertSame('pending', $resultat['status']);
        $this->assertStringStartsWith('SIM-', $resultat['external_ref']);
        $this->assertTrue($resultat['raw']['simulation']);
    }

    public function test_le_paiement_reste_en_attente_avant_le_delai(): void
    {
        config(['services.paiement_simule.delai_secondes' => 5]);
        $passerelle = new SimulationGateway();

        $reference = $passerelle->initiate(5000.0, 'XAF', '+237600000000')['external_ref'];
        $statut = $passerelle->checkStatus($reference);

        $this->assertSame('PENDING', $statut['status']);
        $this->assertNull($statut['financial_transaction_id']);
    }

    public function test_le_paiement_aboutit_une_fois_le_delai_passe(): void
    {
        config(['services.paiement_simule.delai_secondes' => 5]);
        $passerelle = new SimulationGateway();

        Carbon::setTestNow(Carbon::parse('2026-11-14 10:00:00'));
        $reference = $passerelle->initiate(5000.0, 'XAF', '+237600000000')['external_ref'];

        Carbon::setTestNow(Carbon::parse('2026-11-14 10:00:06'));
        $statut = $passerelle->checkStatus($reference);

        // Les quatre libellés que WebhookController et PaymentController
        // tiennent pour un paiement abouti.
        $this->assertContains($statut['status'], ['SUCCESSFUL', 'SUCCESS', 'COMPLETED', 'COMPLETE']);
        $this->assertNotNull($statut['financial_transaction_id']);
        $this->assertSame(5000.0, $statut['raw']['montant']);
    }

    public function test_un_delai_nul_confirme_immediatement(): void
    {
        config(['services.paiement_simule.delai_secondes' => 0]);
        $passerelle = new SimulationGateway();

        $reference = $passerelle->initiate(1000.0, 'XAF', '+237600000000')['external_ref'];

        $this->assertSame('COMPLETE', $passerelle->checkStatus($reference)['status']);
    }

    public function test_une_reference_inconnue_n_est_jamais_confirmee(): void
    {
        // Un portefeuille crédité sur une référence que le simulateur n'a pas
        // émise serait de l'argent créé de rien.
        $statut = (new SimulationGateway())->checkStatus('SIM-INVENTEE12');

        $this->assertSame('FAILED', $statut['status']);
        $this->assertNull($statut['financial_transaction_id']);
    }

    public function test_la_fabrique_retient_le_simulateur_quand_il_est_actif(): void
    {
        config(['services.paiement_simule.actif' => true]);

        foreach (['momo', 'om', 'card'] as $methode) {
            $this->assertInstanceOf(
                SimulationGateway::class,
                PaymentGatewayFactory::make($methode),
                "la méthode « {$methode} » doit passer par le simulateur"
            );
        }
    }

    public function test_la_fabrique_revient_a_notchpay_quand_il_ne_l_est_pas(): void
    {
        // Le cas qui compte vraiment : un serveur qui encaisse pour de bon ne
        // doit jamais tomber sur le simulateur par inadvertance.
        config(['services.paiement_simule.actif' => false]);

        $this->assertInstanceOf(NotchPayGateway::class, PaymentGatewayFactory::make('momo'));
    }

    public function test_le_remboursement_aboutit(): void
    {
        $this->assertSame(
            'refunded',
            (new SimulationGateway())->refund('SIM-ABCDEFGH1234', 5000.0)['status']
        );
    }
}
