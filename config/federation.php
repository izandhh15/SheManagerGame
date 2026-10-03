<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Federation between platform instances
    |--------------------------------------------------------------------------
    |
    | Lets players on two deployments (e.g. Wasmer and Vercel, each with its
    | own database) see each other and become friends across instances.
    |
    | FEDERATION_PEER_URL  Base URL of the peer instance, e.g.
    |                      https://she-manager-game.vercel.app
    | FEDERATION_SECRET    Shared secret used to HMAC-sign cross-instance
    |                      requests. Must be identical on both instances.
    |
    | When either is missing, federation is silently disabled: the friends
    | page simply shows no cross-platform section and the API endpoints
    | return 404.
    |
    */
    'peer_url' => rtrim((string) env('FEDERATION_PEER_URL', ''), '/'),
    'secret' => (string) env('FEDERATION_SECRET', ''),

    'enabled' => (bool) env('FEDERATION_PEER_URL') && (bool) env('FEDERATION_SECRET'),

    // Signed requests older than this (seconds) are rejected.
    'timestamp_tolerance' => 300,

    // How long the peer's player directory is cached locally (seconds).
    'directory_ttl' => 900,

    'directory_per_page' => 15,
];
