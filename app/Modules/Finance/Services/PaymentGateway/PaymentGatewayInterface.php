<?php

namespace App\Modules\Finance\Services\PaymentGateway;

interface PaymentGatewayInterface
{
    /**
     * Initiate a payment request.
     * @return array{success: bool, external_ref: string, status: string, raw: array}
     */
    public function initiate(float $amount, string $currency, string $phoneNumber, array $metadata = []): array;

    /**
     * Check the status of a payment.
     * @return array{status: string, financial_transaction_id: ?string, raw: array}
     */
    public function checkStatus(string $externalRef): array;

    /**
     * Refund a payment.
     * @return array{status: string}
     */
    public function refund(string $externalRef, float $amount): array;
}
