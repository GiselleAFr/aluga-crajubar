<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\PropertyLocationController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function (): void {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:6,1');
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:3,1');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:3,1');
    Route::post('/confirm-password-change', [AuthController::class, 'confirmPasswordChange'])->middleware('throttle:3,1');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/change-password', [AuthController::class, 'changePassword'])->middleware('throttle:3,1');
        Route::get('/profile', [ProfileController::class, 'show']);
        Route::patch('/profile', [ProfileController::class, 'update']);
        Route::get('/profile/stays', [ProfileController::class, 'stays']);
    });
});

Route::get('/maps/geocode', [PropertyLocationController::class, 'geocode'])->middleware('throttle:30,1');
Route::get('/properties/{property}/location', [PropertyLocationController::class, 'show']);
