<?php

namespace App\Modules\Matching\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SseNotificationController extends Controller
{
    /**
     * SSE stream for real-time technician notifications.
     * The technician connects once and receives push events when new proposals arrive.
     */
    public function stream(Request $request): StreamedResponse
    {
        // EventSource cannot send Authorization headers, so we accept token via query param
        $user = $request->user();
        if (!$user && $request->query('token')) {
            $token = PersonalAccessToken::findToken($request->query('token'));
            $user = $token?->tokenable;
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

            // Keep connection alive, check for new notifications
            while (true) {
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
}
