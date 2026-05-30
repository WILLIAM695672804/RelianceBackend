<?php

namespace App\Modules\Finance\Services\PaymentGateway;

class PaymentGatewayFactory
{
    public static function make(string $method): PaymentGatewayInterface
    {
        return match ($method) {
            // NotchPay (agrégateur) gère MoMo, Orange Money et carte via les channels.
            'momo', 'om', 'card' => new NotchPayGateway($method),
            'bank_transfer' => new BankTransferGateway(),
            default => throw new \InvalidArgumentException("Méthode de paiement non supportée: {$method}"),
        };
    }
}
