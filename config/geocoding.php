<?php

return [
    'nominatim_url' => env('NOMINATIM_URL', 'https://nominatim.openstreetmap.org/search'),
    'user_agent' => env('NOMINATIM_USER_AGENT', 'AlugaCrajubar/1.0 (contact@alugacrajubar.local)'),
    'timeout' => (int) env('NOMINATIM_TIMEOUT', 10),
    'cache_ttl' => (int) env('NOMINATIM_CACHE_TTL', 10080),
];
