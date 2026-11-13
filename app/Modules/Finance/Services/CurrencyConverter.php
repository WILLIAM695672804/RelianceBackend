<?php

namespace App\Modules\Finance\Services;

use App\Modules\Finance\Models\ExchangeRate;
use App\Modules\Finance\Models\Transaction;
use App\Modules\Finance\Models\Wallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Portefeuilles multi-devises (XAF, EUR, USD) : conversion entre les portefeuilles d'un même
 * utilisateur au taux défini par l'administrateur (1 devise = N XAF), de façon atomique.
 */
class CurrencyConverter
{
    public const CURRENCIES = ['XAF', 'EUR', 'USD'];

    /** Valeur d'une unité de devise en XAF ; null si aucun taux actif. */
    public function rateToXaf(string $currency): ?float
    {
        if ($currency === 'XAF') {
            return 1.0;
        }
        $rate = ExchangeRate::where('from_currency', $currency)->where('to_currency', 'XAF')->where('active', true)->latest('created_at')->value('rate');

        return $rate ? (float) $rate : null;
    }

    public function quote(float $amount, string $from, string $to): ?float
    {
        $fromRate = $this->rateToXaf($from);
        $toRate = $this->rateToXaf($to);
        if (!$fromRate || !$toRate) {
            return null;
        }

        // XAF n'a pas de décimales ; les autres devises en ont deux.
        return round($amount * $fromRate / $toRate, $to === 'XAF' ? 0 : 2);
    }

    /**
     * @return array{debited: float, credited: float, from: string, to: string}
     * @throws \DomainException solde insuffisant ou taux indisponible
     */
    public function convert(string $userId, float $amount, string $from, string $to): array
    {
        $credited = $this->quote($amount, $from, $to);
        if ($credited === null || $credited <= 0) {
            throw new \DomainException('Taux de change indisponible pour cette conversion.');
        }

        return DB::transaction(function () use ($userId, $amount, $from, $to, $credited) {
            foreach ([$from, $to] as $currency) {
                Wallet::firstOrCreate(['user_id' => $userId, 'currency' => $currency], ['id' => (string) Str::uuid(), 'balance' => 0]);
            }
            // Verrouillage dans un ordre stable (par devise) pour éviter les interblocages.
            $wallets = Wallet::where('user_id', $userId)->whereIn('currency', [$from, $to])->orderBy('currency')->lockForUpdate()->get()->keyBy('currency');
            $source = $wallets[$from];
            $target = $wallets[$to];

            if ($source->availableBalance() < $amount) {
                throw new \DomainException('Solde insuffisant : ' . $from);
            }

            $source->decrement('balance', $amount);
            $target->increment('balance', $credited);

            foreach ([[$source, -$amount, 'debit'], [$target, $credited, 'credit']] as [$wallet, $signed, $type]) {
                Transaction::create([
                    'id' => (string) Str::uuid(), 'wallet_id' => $wallet->id, 'amount' => $signed,
                    'balance_after' => $wallet->fresh()->balance, 'type' => $type,
                    'meta' => ['source' => 'currency_conversion', 'from' => $from, 'to' => $to],
                ]);
            }

            return ['debited' => $amount, 'credited' => $credited, 'from' => $from, 'to' => $to];
        });
    }
}
