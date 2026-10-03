<?php

return [
    'paths' => ['api/*'],
    'allowed_methods' => ['POST'],
    'allowed_origins' => [
        'https://shemanager.wasmer.app',
        // Local dev (no hay www: el dominio de prod es el subdominio directo).
        'http://localhost:8000',
        'http://127.0.0.1:8000',
    ],
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['Content-Type', 'Accept'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => false,
];
