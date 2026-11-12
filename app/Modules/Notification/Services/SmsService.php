<?php

namespace App\Modules\Notification\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Envoi de SMS. Pilote `http` : passerelle générique (POST JSON {to, from, message},
 * jeton Bearer) ; pilote `log` (défaut) : journalisation seule, sans envoi réel.
 */
class SmsService
{
    public function send(string $phone, string $message): bool
    {
        $driver = config('services.sms.driver', 'log');

        if ($driver !== 'http' || !config('services.sms.url')) {
            Log::info('SMS (pilote log)', ['to' => $phone, 'message' => $message]);
            return false;
        }

        try {
            $response = Http::timeout(8)
                ->withToken((string) config('services.sms.key'))
                ->acceptJson()
                ->post(config('services.sms.url'), [
                    'to' => $phone,
                    'from' => config('services.sms.sender', 'Reliance'),
                    'message' => $message,
                ]);

            return $response->successful();
        } catch (\Throwable $e) {
            Log::warning('Envoi SMS échoué : ' . $e->getMessage());
            return false;
        }
    }
}
