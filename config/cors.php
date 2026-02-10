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

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['POST', 'GET', 'OPTIONS', 'PUT', 'PATCH', 'DELETE'],

    'allowed_origins' => [
        '*',
        // Ajoutez d'autres origines si nécessaire
    ],

    'allowed_origins_patterns' => [
        // Patterns regex pour les tunnels et localhost
        'https?://.*\.pinggy\.link', // Pinggy tunnels
        'https?://.*\.serveousercontent\.com', // Serveo tunnels
        'https?://.*\.loca\.lt', // Tunnelmole
        'https?://.*\.localhost', // Localhost tunnels
        'https?://.*\.nport\.link', // Nport tunnels
        'https?://.*\.ngrok\.io', // Ngrok
        'https?://.*\.ngrok-free\.app', // Ngrok free
        'http?://localhost:[0-9]+', // Localhost avec port
        'http?://127\.0\.0\.1:[0-9]+', // 127.0.0.1 avec port
        'http?://192\.168\.[0-9]+\.[0-9]+:[0-9]+', // Réseau privé classe C
        'http?://10\.[0-9]+\.[0-9]+\.[0-9]+:[0-9]+', // Réseau privé classe A
        'http?://172\.(1[6-9]|2[0-9]|3[0-1])\.[0-9]+\.[0-9]+:[0-9]+', // Réseau privé classe B
    ],

    'allowed_headers' => [
        'Content-Type',
        'X-Auth-Token',
        'Origin',
        'Authorization',
        'X-CSRF-TOKEN',
        'X-Requested-With',
        'Accept',
        'X-XSRF-TOKEN',
        'X-Socket-Id',
        'ngrok-skip-browser-warning'
    ],

    'exposed_headers' => [
        'Cache-Control',
        'Content-Language',
        'Content-Type',
        'Expires',
        'Last-Modified',
        'Pragma',
        'X-CSRF-TOKEN'
    ],

    'max_age' => 60 * 60 * 48, // 48 heures (172800 secondes) - Cache plus long pour réduire les requêtes OPTIONS

    'supports_credentials' => true,

];
