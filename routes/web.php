<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});
Route::get('/reset-password', function () {
    return response()
        ->view('mobile-reset-password', ['appUrl' => config('jwt.reset_deep_link')])
        ->header('Cache-Control', 'no-store');
});