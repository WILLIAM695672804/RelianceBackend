<?php

namespace App\Modules\Matching\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Auth\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SseNotificationController extends Controller
{
    /** Durée de validité d'un jeton de flux, en secondes. */
    private const TOKEN_TTL = 60;

    /**
     * POST /v1/proposals/stream-token — délivre un jeton de flux éphémère et à usage unique.
     * EventSource ne pouvant pas envoyer d'en-tête Authorization, le jeton voyage dans l'URL :
     * il ne doit donc jamais s'agir du jeton Sanctum, long-vivant et exposé aux journaux d'accès.
     */
    public function issueToken(Request $request): JsonResponse
    {
        $token = Str::random(64);
        Cache::put(self::cacheKey($token), $request->user()->id, self::TOKEN_TTL);

        return response()->json(['token' => $token, 'expires_in' => self::TOKEN_TTL]);
    }

    /**
     * SSE stream for real-time technician notifications.
     * The technician connects once and receives push events when new proposals arrive.
     */
    public function stream(Request $request): StreamedResponse
    {
        $user = $request->user();
        if (!$user && is_string($request->query('token'))) {
            // Cache::pull : le jeton est consommé à la première connexion.
            $userId = Cache::pull(self::cacheKey($request->query('token')));
            $user = $userId ? User::find($userId) : null;
        }

        if (!$user) {
            abort(401, 'Unauthorized');
        }

        $technicianId = $user->id;

        return new StreamedResponse(function () use ($technicianId) {
            // Disable output buffering
            if (ob_get_level()) {
                ob_end_clean();
            }

            // Send initial connection event
            echo "event: connected\n";
            echo "data: " . json_encode(['status' => 'connected']) . "\n\n";
            ob_flush();
            flush();

            $lastEventId = 0;
            // Durée bornée : un flux sans fin immobiliserait un processus PHP-FPM par client.
            // Le client rouvre le flux avec un nouveau jeton à la fermeture.
            $deadline = time() + (int) config('reliance.sse_max_seconds', 300);

            // Keep connection alive, check for new notifications
            while (time() < $deadline) {
                // Check if client disconnected
                if (connection_aborted()) {
                    break;
                }

                $cacheKey = "sse_proposals_{$technicianId}";
                $notification = Cache::get($cacheKey);

                if ($notification && ($notification['id'] ?? 0) > $lastEventId) {
                    $lastEventId = $notification['id'];

                    echo "event: new_proposal\n";
                    echo "data: " . json_encode($notification) . "\n\n";
                    ob_flush();
                    flush();

                    // Clear after sending
                    Cache::forget($cacheKey);
                } else {
                    // Send heartbeat to keep connection alive
                    echo ": heartbeat\n\n";
                    ob_flush();
                    flush();
                }

                // Check every 2 seconds
                sleep(2);
            }
        }, 200, [
            'Content-Type'  => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection'    => 'keep-alive',
            'X-Accel-Buffering' => 'no', // Disable nginx buffering
        ]);
    }

    private static function cacheKey(string $token): string
    {
        return 'sse_token:' . hash('sha256', $token);
    }
}
