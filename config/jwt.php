<?php

return [
    // Use JWT_SECRET in production. APP_KEY is a safe local-development fallback.
    'secret' => env('JWT_SECRET') ?: env('APP_KEY'),
    'ttl' => (int) env('JWT_TTL', 60),
];
