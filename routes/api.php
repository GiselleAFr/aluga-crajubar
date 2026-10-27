<?php

use App\Http\Controllers\Api\Admin\LandlordRequestController as AdminLandlordRequestController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\ImovelController;
use App\Http\Controllers\Api\LandlordRequestController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\NotificationPreferenceController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\FavoritoController;
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

// Solicitação para se tornar locador e notificações.
Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/landlord-request', [LandlordRequestController::class, 'show']);
    Route::post('/landlord-request', [LandlordRequestController::class, 'store'])->middleware('throttle:5,1');

    // Preferências de notificação.
    Route::get('/notification-preferences', [NotificationPreferenceController::class, 'index']);
    Route::patch('/notification-preferences', [NotificationPreferenceController::class, 'update']);

    // Caixa de entrada.
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);
    Route::patch('/notifications/{notification}/read', [NotificationController::class, 'markAsRead'])->whereUuid('notification');
});

// Análise das solicitações: somente administradores.
Route::prefix('admin')->middleware(['auth:sanctum', 'role:admin'])->group(function (): void {
    Route::get('/landlord-requests', [AdminLandlordRequestController::class, 'index']);
    Route::post('/landlord-requests/{landlordRequest}/approve', [AdminLandlordRequestController::class, 'approve']);
    Route::post('/landlord-requests/{landlordRequest}/reject', [AdminLandlordRequestController::class, 'reject']);
});

// Consulta pública de imóveis.
Route::apiResource('imoveis', ImovelController::class)->only(['index', 'show']);

// Escrita de imóveis restrita a locadores autenticados.
Route::middleware(['auth:sanctum', 'role:landlord'])->group(function (): void {
    Route::apiResource('imoveis', ImovelController::class)->only(['store', 'update', 'destroy']);
});

// Favoritos: somente usuários autenticados.
Route::middleware('auth:sanctum')->prefix('favoritos')->group(function (): void {
    Route::get('/', [FavoritoController::class, 'listar']);
    Route::get('/pesquisar', [FavoritoController::class, 'pesquisar']);
    Route::post('/{imovel}', [FavoritoController::class, 'adicionar']);
    Route::delete('/{imovel}', [FavoritoController::class, 'remover']);
});

// Reservas: somente usuários autenticados.
Route::middleware('auth:sanctum')->prefix('bookings')->group(function (): void {
    Route::post('/', [BookingController::class, 'store']);
    Route::get('/', [BookingController::class, 'index']);
    Route::get('/disponibilidade', [BookingController::class, 'disponibilidade']);
    Route::get('/{booking}/status', [BookingController::class, 'status']);
    Route::get('/{booking}', [BookingController::class, 'show']);
});
