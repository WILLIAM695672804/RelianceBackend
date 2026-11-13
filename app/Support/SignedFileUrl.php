<?php

namespace App\Support;

/**
 * URL temporaire d'un fichier du disque privé, signée par HMAC.
 * Permet l'affichage par <img src> sans en-tête Authorization. La signature est propre à
 * l'application : la vérification des URL signées de Laravel 10 est sujette à une confusion
 * de chemin. Le « domaine » (kyc, piece-jointe…) cloisonne les signatures entre usages.
 */
final class SignedFileUrl
{
    public static function make(string $route, string $domaine, string $id, int $minutes): string
    {
        $expires = now()->addMinutes($minutes)->getTimestamp();

        return route($route, [
            'id' => $id,
            'expires' => $expires,
            'signature' => self::signature($domaine, $id, $expires),
        ]);
    }

    public static function isValid(string $domaine, string $id, mixed $expires, mixed $signature): bool
    {
        $expires = (int) $expires;

        return $expires >= now()->getTimestamp()
            && is_string($signature)
            && hash_equals(self::signature($domaine, $id, $expires), $signature);
    }

    private static function signature(string $domaine, string $id, int $expires): string
    {
        return hash_hmac('sha256', "{$domaine}|{$id}|{$expires}", (string) config('app.key'));
    }
}
