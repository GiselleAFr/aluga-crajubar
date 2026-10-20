<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    /**
     * Uso nas rotas: ->middleware('role:landlord') ou ->middleware('role:admin').
     * Deve vir depois de "auth:sanctum".
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user || ! in_array($user->role->value, $roles, true)) {
            return response()->json(['message' => 'Você não tem permissão para acessar este recurso.'], 403);
        }

        return $next($request);
    }
}