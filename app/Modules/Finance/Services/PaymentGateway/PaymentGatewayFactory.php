<?php

namespace App\Modules\Finance\Services\PaymentGateway;

class PaymentGatewayFactory
{
    public static function make(string $method): PaymentGatewayInterface
    {
        return match ($method) {
            'momo' => new MomoGateway(),
            'om' => new OrangeMoneyGateway(),
            default => throw new \InvalidArgumentException("Méthode de paiement non supportée: {$method}"),
        };
    }
}
