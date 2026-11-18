<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAdminRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * Gestão de administradores. Rotas protegidas por role:super_admin.
 *
 * Esta API só cria e remove administradores comuns. Super administradores
 * existem apenas pelo comando "php artisan admin:create-super", o que
 * impede escalada de privilégio e a remoção do último super administrador.
 */
class AdminUserController extends Controller
{
    public function index(): JsonResponse
    {
        $admins = User::query()
            ->whereIn('role', [UserRole::ADMIN->value, UserRole::SUPER_ADMIN->value])
            ->orderByDesc('role') // super_admin antes de admin
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $admins->map(fn (User $user) => $this->present($user))->values(),
        ]);
    }

    public function store(StoreAdminRequest $request): JsonResponse
    {
        $admin = new User($request->safe()->only(['name', 'email', 'password']));
        $admin->forceFill([
            'role' => UserRole::ADMIN,
            'email_verified_at' => now(),
        ])->save();

        return response()->json([
            'message' => 'Administrador criado com sucesso.',
            'data' => $this->present($admin),
        ], 201);
    }

    /**
     * Remove o acesso administrativo. A conta vira "client" e as sessões são
     * encerradas; o registro continua existindo para preservar o histórico
     * das decisões já tomadas por essa pessoa.
     */
    public function destroy(User $admin): JsonResponse
    {
        if ($admin->role === UserRole::SUPER_ADMIN) {
            return response()->json([
                'message' => 'Super administradores não podem ser removidos por aqui.',
            ], 422);
        }

        // Qualquer outro usuário é tratado como inexistente.
        abort_unless($admin->role === UserRole::ADMIN, 404);

        DB::transaction(function () use ($admin): void {
            $admin->forceFill(['role' => UserRole::CLIENT])->save();
            $admin->tokens()->delete();
        });

        return response()->json(['message' => 'Acesso administrativo removido.']);
    }

    private function present(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role->value,
            'created_at' => $user->created_at?->toISOString(),
        ];
    }
}