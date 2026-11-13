<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Página aberta pelo link do e-mail de recuperação de senha: entrega o token ao app por deep link.
Route::get('/reset-password', function () {
    return response()
        ->view('mobile-reset-password', ['appUrl' => config('jwt.reset_deep_link')])
        ->header('Cache-Control', 'no-store');
});
