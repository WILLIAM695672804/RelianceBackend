<?php

namespace App\Modules\Finance\Services\PaymentGateway;

use Illuminate\Support\Str;

class BankTransferGateway implements PaymentGatewayInterface
{
    public function initiate(float $amount, string $currency, string $phoneNumber, array $metadata = []): array
    {
        $referenceId = (string) Str::uuid();

        return [
            'success' => true,
            'external_ref' => $referenceId,
            'status' => 'pending',
            'raw' => [
                'method' => 'bank_transfer',
                'instructions' => [
                    'bank_name' => config('services.bank_transfer.bank_name', 'Afriland First Bank'),
                    'account_number' => config('services.bank_transfer.account_number'),
                    'account_name' => config('services.bank_transfer.account_name', 'Reliance SAS'),
                    'reference' => $referenceId,
                    'amount' => $amount,
                    'currency' => $currency,
                ],
                'note' => 'Veuillez effectuer le virement avec la référence indiquée. Le paiement sera confirmé après vérification.',
            ],
        ];
    }

    public function checkStatus(string $externalRef): array
    {
        // Bank transfers are confirmed manually by admin
        return [
            'status' => 'PENDING_VERIFICATION',
            'financial_transaction_id' => null,
            'raw' => ['note' => 'En attente de vérification manuelle par l\'administrateur'],
        ];
    }

    public function refund(string $externalRef, float $amount): array
    {
        // Bank transfer refunds are processed manually
        return ['status' => 'pending_manual_refund'];
    }
}
