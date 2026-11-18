<?php

use App\Http\Controllers\Api\Admin\AdminUserController;
use App\Http\Controllers\Api\Admin\LandlordRequestController as AdminLandlordRequestController;
use App\Http\Controllers\Api\Admin\OverviewController as AdminOverviewController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\ImovelController;
use App\Http\Controllers\Api\ImovelFotoController;
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
        Route::post('/change-password/verify-code', [AuthController::class, 'verifyPasswordChangeCode'])->middleware('throttle:6,1,password-change-verify');
        Route::post('/change-password/resend-code', [AuthController::class, 'resendPasswordChangeCode'])->middleware('throttle:3,1,password-change-resend');
        Route::get('/profile', [ProfileController::class, 'show']);
        Route::patch('/profile', [ProfileController::class, 'update']);
        Route::get('/profile/stays', [ProfileController::class, 'stays']);
        Route::get('/profile/active-stay', [ProfileController::class, 'activeStay']);
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
    Route::patch('/notifications/{notification}/read', [NotificationController::class, 'markAsRead'])
        ->whereUuid('notification');
});

// Painel administrativo: administradores e super administradores.
Route::prefix('admin')->middleware(['auth:sanctum', 'role:admin,super_admin'])->group(function (): void {
    Route::get('/overview', AdminOverviewController::class);
    Route::get('/landlord-requests', [AdminLandlordRequestController::class, 'index']);
    Route::get('/landlord-requests/{landlordRequest}', [AdminLandlordRequestController::class, 'show']);
    Route::get('/landlord-requests/{landlordRequest}/documents/{side}', [AdminLandlordRequestController::class, 'document'])
        ->whereIn('side', ['front', 'back'])
        ->name('admin.landlord-requests.documents.show');
    Route::post('/landlord-requests/{landlordRequest}/approve', [AdminLandlordRequestController::class, 'approve']);
    Route::post('/landlord-requests/{landlordRequest}/reject', [AdminLandlordRequestController::class, 'reject']);
});

// Gestão de administradores: somente o super administrador.
Route::prefix('admin')->middleware(['auth:sanctum', 'role:super_admin'])->group(function (): void {
    Route::get('/admins', [AdminUserController::class, 'index']);
    Route::post('/admins', [AdminUserController::class, 'store'])->middleware('throttle:10,1');
    Route::delete('/admins/{admin}', [AdminUserController::class, 'destroy'])->whereNumber('admin');
});

// Consulta pública de imóveis.
Route::apiResource('imoveis', ImovelController::class)
    ->only(['index', 'show'])
    ->parameters(['imoveis' => 'imovel']);

// Escrita de imóveis restrita a locadores autenticados.
Route::middleware(['auth:sanctum', 'role:landlord'])->group(function (): void {
    Route::get('/landlord/imoveis', [ImovelController::class, 'landlordIndex']);
    Route::post('/imoveis/{imovel}/fotos', [ImovelFotoController::class, 'store']);
    Route::patch('/imoveis/{imovel}/fotos/{foto}/principal', [ImovelFotoController::class, 'setCover']);
    Route::delete('/imoveis/{imovel}/fotos/{foto}', [ImovelFotoController::class, 'destroy']);
    Route::apiResource('imoveis', ImovelController::class)
        ->only(['store', 'update', 'destroy'])
        ->parameters(['imoveis' => 'imovel']);
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
    // Gestão das solicitações recebidas pelos imóveis do locatário.
    Route::middleware('role:landlord')->group(function (): void {
        Route::get('/solicitacoes', [BookingController::class, 'solicitacoes']);
        Route::patch('/{booking}/confirmar', [BookingController::class, 'confirmar']);
        Route::patch('/{booking}/recusar', [BookingController::class, 'recusar']);
    });

    // Solicitações e consultas do usuário.
    Route::post('/', [BookingController::class, 'store']);
    Route::get('/', [BookingController::class, 'index']);
    Route::get('/disponibilidade', [BookingController::class, 'disponibilidade']);
    Route::patch('/{booking}/cancelar', [BookingController::class, 'cancelar']);
    Route::get('/{booking}/status', [BookingController::class, 'status']);
    Route::get('/{booking}', [BookingController::class, 'show']);
});