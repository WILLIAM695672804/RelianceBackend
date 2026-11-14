<?php

namespace App\Modules\Finance\Services\PaymentGateway;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Passerelle de simulation, pour la préproduction.
 *
 * Elle imite le déroulé de NotchPay sans sortir du serveur : un paiement part
 * en « pending », comme un push USSD qui attend la validation du client, puis
 * passe à « COMPLETE » au bout de quelques secondes. Le parcours complet est
 * donc exerçable — initiation, attente, confirmation, crédit du portefeuille —
 * sans compte marchand, sans argent et sans webhook venu de l'extérieur.
 *
 * Elle n'est retenue que si PAIEMENT_SIMULE vaut true, et la commande
 * « reliance:verifier-production » refuse cette combinaison dès que APP_ENV
 * vaut « production » sans que PREPRODUCTION soit déclaré : un serveur qui
 * encaisse pour de bon ne doit jamais tomber ici par inadvertance.
 *
 * @see PaymentGatewayFactory
 */
class SimulationGateway implements PaymentGatewayInterface
{
    /** Préfixe des références, pour les reconnaître d'un coup d'œil en base. */
    private const PREFIXE = 'SIM';

    public function __construct(private string $methode = 'momo')
    {
    }

    public function initiate(float $amount, string $currency, string $phoneNumber, array $metadata = []): array
    {
        $reference = self::PREFIXE . '-' . strtoupper(Str::random(12));

        // L'instant d'initiation décide du moment où le paiement « aboutit ».
        // Le cache suffit : une simulation n'a pas à survivre à un redémarrage.
        Cache::put($this->cle($reference), [
            'montant' => $amount,
            'devise' => $currency,
            'telephone' => $phoneNumber,
            'initie_a' => now()->timestamp,
        ], now()->addHours(6));

        Log::info('Paiement simulé initié', [
            'reference' => $reference,
            'montant' => $amount,
            'methode' => $this->methode,
        ]);

        return [
            'success' => true,
            'external_ref' => $reference,
            'status' => 'pending',
            'raw' => [
                'simulation' => true,
                'methode' => $this->methode,
                'message' => 'Paiement simulé : il sera confirmé dans '
                    . $this->delai() . ' secondes, sans aucun débit réel.',
                'confirme_dans_secondes' => $this->delai(),
            ],
        ];
    }

    public function checkStatus(string $externalRef): array
    {
        $paiement = Cache::get($this->cle($externalRef));

        // Référence inconnue : soit elle vient d'un autre pilote, soit le cache
        // a été vidé. On ne confirme jamais ce qu'on ne reconnaît pas.
        if (!$paiement) {
            return [
                'status' => 'FAILED',
                'financial_transaction_id' => null,
                'raw' => ['simulation' => true, 'motif' => 'référence inconnue du simulateur'],
            ];
        }

        $ecoule = now()->timestamp - $paiement['initie_a'];
        $abouti = $ecoule >= $this->delai();

        return [
            'status' => $abouti ? 'COMPLETE' : 'PENDING',
            'financial_transaction_id' => $abouti ? self::PREFIXE . '-TX-' . strtoupper(Str::random(10)) : null,
            'raw' => [
                'simulation' => true,
                'secondes_ecoulees' => $ecoule,
                'montant' => $paiement['montant'],
                'devise' => $paiement['devise'],
            ],
        ];
    }

    public function refund(string $externalRef, float $amount): array
    {
        Log::info('Remboursement simulé', ['reference' => $externalRef, 'montant' => $amount]);

        return ['status' => 'refunded'];
    }

    /** Délai avant confirmation, en secondes. Zéro rend le paiement immédiat. */
    private function delai(): int
    {
        return max(0, (int) config('services.paiement_simule.delai_secondes', 5));
    }

    private function cle(string $reference): string
    {
        return 'paiement_simule:' . $reference;
    }
}
