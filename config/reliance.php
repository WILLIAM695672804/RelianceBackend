<?php

return [
    // Seuls les techniciens/agences à abonnement actif sont référencés par le rapprochement.
    // Désactivé par défaut pour l'environnement de développement (aucun plan seedé).
    'require_subscription' => (bool) env('REQUIRE_SUBSCRIPTION', false),

    // Double authentification (2FA) exigée des clients pour les actions financières (cahier des charges §4.1
    // et « authentification 2FA robuste »). Désactivé par défaut : à activer en production.
    'require_2fa_clients' => (bool) env('REQUIRE_2FA_CLIENTS', false),
];
