<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ForgotPasswordRequest;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Requests\ResetPasswordRequest;
use App\Models\User;
use App\Notifications\PasswordResetJwtNotification;
use App\Services\PasswordResetJwtService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create($request->safe()->only(['name', 'email', 'phone', 'password']));

        return $this->authenticatedResponse($user, $request->input('device_name', 'flutter'))
            ->setStatusCode(201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->validated();
        $user = User::where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            return response()->json(['message' => 'E-mail ou senha inválidos.'], 422);
        }

        return $this->authenticatedResponse(
            $user,
            $credentials['device_name'] ?? 'flutter',
            (bool) ($credentials['remember'] ?? false),
        );
    }

    public function me(): JsonResponse
    {
        return response()->json(['user' => $this->userData(request()->user())]);
    }

    public function logout(): JsonResponse
    {
        request()->user()->currentAccessToken()?->delete();

        return response()->json(['message' => 'Sessão encerrada com sucesso.']);
    }

    public function forgotPassword(ForgotPasswordRequest $request, PasswordResetJwtService $passwordReset): JsonResponse
    {
        $user = User::where('email', $request->string('email')->toString())->first();

        if ($user) {
            $user->notify(new PasswordResetJwtNotification($passwordReset->create($user)));
        }

        // A mesma resposta evita revelar se um e-mail possui conta.
        return response()->json(['message' => 'Se o e-mail estiver cadastrado, enviaremos as instruções de recuperação.']);
    }

    public function resetPassword(ResetPasswordRequest $request, PasswordResetJwtService $passwordReset): JsonResponse
    {
        if (! $passwordReset->reset(
            $request->string('token')->toString(),
            $request->string('email')->toString(),
            $request->string('password')->toString(),
        )) {
            return response()->json(['message' => 'O link de recuperação é inválido ou expirou.'], 422);
        }

        return response()->json(['message' => 'Senha alterada com sucesso. Faça login novamente.']);
    }

    private function authenticatedResponse(User $user, string $deviceName, bool $remember = false): JsonResponse
    {
        $expiresAt = now()->addDays($remember ? 30 : 1);
        $token = $user->createToken($deviceName, ['*'], $expiresAt)->plainTextToken;

        return response()->json([
            'message' => 'Login realizado com sucesso.',
            'token_type' => 'Bearer',
            'access_token' => $token,
            'expires_at' => $expiresAt->toISOString(),
            'user' => $this->userData($user),
        ]);
    }

    private function userData(User $user): array
    {
        return $user->only(['id', 'name', 'email', 'phone', 'created_at']);
    }
}
