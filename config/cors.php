<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie', 'broadcasting/auth'],

    'allowed_methods' => ['*'],

    // On autorise 127.0.0.1 ET localhost pour éviter les blocages CORS selon l'URL ouverte.
    // (La session ne persiste toutefois qu'en restant sur un seul hôte — voir 127.0.0.1.)
    // Les origines du serveur Vite ne sont admises qu'en développement : avec supports_credentials,
    // toute origine listée peut émettre des requêtes authentifiées par cookie.
    'allowed_origins' => array_values(array_unique(array_filter(array_merge(
        [env('FRONTEND_URL', 'http://127.0.0.1:5173')],
        env('APP_ENV') === 'local' ? ['http://127.0.0.1:5173', 'http://localhost:5173'] : [],
    )))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];
