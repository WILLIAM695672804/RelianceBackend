<?php

return [
    // Seuls les techniciens/agences à abonnement actif sont référencés par le rapprochement.
    // Désactivé par défaut pour l'environnement de développement (aucun plan seedé).
    'require_subscription' => (bool) env('REQUIRE_SUBSCRIPTION', false),
];
