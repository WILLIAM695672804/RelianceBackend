<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies as Middleware;
use Illuminate\Http\Request;

class TrustProxies extends Middleware
{
    /**
     * The trusted proxies for this application.
     *
     * @var array<int, string>|string|null
     */
    protected $proxies;

    /**
     * The headers that should be used to detect proxies.
     *
     * @var int
     */
    protected $headers =
        Request::HEADER_X_FORWARDED_FOR |
        Request::HEADER_X_FORWARDED_HOST |
        Request::HEADER_X_FORWARDED_PORT |
        Request::HEADER_X_FORWARDED_PROTO |
        Request::HEADER_X_FORWARDED_AWS_ELB;

    /**
     * Mandataires de confiance lus dans TRUSTED_PROXIES (liste d'IP/CIDR séparées par des virgules, ou « * »).
     * Derrière un proxy inverse, sans cette liste, request()->ip() renvoie l'IP du proxy : les limiteurs
     * par IP deviennent globaux et les URL générées perdent le schéma https.
     */
    protected function proxies()
    {
        $proxies = trim((string) config('reliance.trusted_proxies', ''));

        if ($proxies === '') {
            return $this->proxies;
        }

        return $proxies === '*' ? '*' : array_map('trim', explode(',', $proxies));
    }
}
