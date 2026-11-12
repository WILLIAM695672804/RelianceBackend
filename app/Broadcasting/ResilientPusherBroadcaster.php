<?php

namespace App\Broadcasting;

use Illuminate\Broadcasting\Broadcasters\PusherBroadcaster;
use Illuminate\Broadcasting\BroadcastException;
use Illuminate\Support\Facades\Log;

/**
 * Diffuseur Pusher tolérant aux pannes : l'indisponibilité du serveur WebSocket
 * dégrade le temps réel (consigné dans les journaux) sans interrompre la requête HTTP
 * métier ni annuler sa transaction.
 */
class ResilientPusherBroadcaster extends PusherBroadcaster
{
    public function broadcast(array $channels, $event, array $payload = [])
    {
        try {
            parent::broadcast($channels, $event, $payload);
        } catch (BroadcastException $e) {
            Log::warning('Diffusion temps réel indisponible', [
                'event' => $event,
                'erreur' => $e->getMessage(),
            ]);
        }
    }
}
