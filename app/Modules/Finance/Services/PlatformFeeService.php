<?php

namespace App\Modules\Finance\Services;

use App\Modules\Finance\Models\PlatformFee;

/**
 * Calcul unique des frais de plateforme : utilisé à la capture du séquestre et pour informer
 * le client des frais d'intervention avant paiement (cahier des charges §4.4).
 */
class PlatformFeeService
{
    /** Frais applicables à un montant ; jamais supérieurs au montant (net du bénéficiaire ≥ 0). */
    public function forAmount(float $amount, string $appliesTo = 'job'): float
    {
        $fees = PlatformFee::where('active', true)
            ->where('applies_to', $appliesTo)
            ->get()
            ->sum(fn ($fee) => $fee->fee_type === 'percentage' ? $amount * (float) $fee->value : (float) $fee->value);

        return round(min((float) $fees, $amount), 2);
    }

    /** @return array{amount: float, fees: float, net: float} */
    public function breakdown(float $amount, string $appliesTo = 'job'): array
    {
        $fees = $this->forAmount($amount, $appliesTo);

        return ['amount' => round($amount, 2), 'fees' => $fees, 'net' => round($amount - $fees, 2)];
    }
}
