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

    'allowed_origins' => ['*'], // Accepter toutes les origines pour le développement

    'allowed_origins_patterns' => [
        '*'
        // 'http?://localhost:[0-9]+',
        // 'http?://127\.0\.0\.1:[0-9]+',
        // 'http?://192\.168\.[0-9]+\.[0-9]+:[0-9]+', // Réseau privé classe C
        // 'http?://10\.[0-9]+\.[0-9]+\.[0-9]+:[0-9]+', // Réseau privé classe A
        // 'http?://172\.(1[6-9]|2[0-9]|3[0-1])\.[0-9]+\.[0-9]+:[0-9]+', // Réseau privé classe B
        // 'http?://[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+:[0-9]+', // Toute adresse IP pour les tunnels
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
        'X-Socket-Id'
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

    'max_age' => 60 * 60 * 24, // 24 heures

    'supports_credentials' => true,

];
