<?php

return [
    // Use JWT_SECRET in production. APP_KEY is a safe local-development fallback.
    'secret' => env('JWT_SECRET') ?: env('APP_KEY'),
    'ttl' => (int) env('JWT_TTL', 60),
    'password_change_ttl' => (int) env('PASSWORD_CHANGE_TTL', 30),

    // Link do e-mail de recuperação de senha. Com PASSWORD_RESET_WEB_URL (página https deste
    // back-end, ex.: https://seu-dominio/reset-password), o e-mail aponta para ela e ela abre o app.
    // Sem ele, o e-mail usa direto o deep link do app.
    'reset_web_url' => env('PASSWORD_RESET_WEB_URL'),
    'reset_deep_link' => env('MOBILE_RESET_PASSWORD_URL', 'alugacrajubar://reset-password'),
];
