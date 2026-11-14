<?php

namespace App\Modules\Finance\Services\PaymentGateway;

class PaymentGatewayFactory
{
    public static function make(string $method): PaymentGatewayInterface
    {
        // Préproduction : aucun appel ne sort du serveur, aucun débit réel.
        // « reliance:verifier-production » refuse ce réglage sur un serveur de
        // production qui ne s'est pas déclaré en préproduction.
        $simule = (bool) config('services.paiement_simule.actif', false);

        return match ($method) {
            // NotchPay (agrégateur) gère MoMo, Orange Money et carte via les channels.
            'momo', 'om', 'card' => $simule
                ? new SimulationGateway($method)
                : new NotchPayGateway($method),
            'bank_transfer' => new BankTransferGateway(),
            default => throw new \InvalidArgumentException("Méthode de paiement non supportée: {$method}"),
        };
    }
}
