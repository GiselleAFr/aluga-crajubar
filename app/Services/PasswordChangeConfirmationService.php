<?php

namespace App\Services;

use App\Models\User;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Throwable;

class PasswordChangeConfirmationService
{
    /** Tentativas de código incorreto antes de invalidar a solicitação. */
    public const MAX_CODE_ATTEMPTS = 5;

    /**
     * Mantido por compatibilidade: devolve apenas o JWT do link de confirmação.
     */
    public function create(User $user, string $newPassword): string
    {
        return $this->issue($user, $newPassword)['token'];
    }

    /**
     * Registra a solicitação pendente e devolve o JWT (link) e o código de 6 dígitos (e-mail).
     * A nova senha fica apenas em hash; nunca vai no JWT nem no código.
     *
     * @return array{token: string, code: string}
     */
    public function issue(User $user, string $newPassword): array
    {
        return $this->store($user, Hash::make($newPassword));
    }

    /**
     * Gera novo link e novo código para a solicitação pendente do usuário, sem pedir a senha de novo.
     *
     * @return array{token: string, code: string}|null null quando não há solicitação pendente válida
     */
    public function reissue(User $user): ?array
    {
        $record = DB::table('password_change_confirmations')->where('email', $user->email)->first();

        if (! $record || $this->isExpired($record->created_at)) {
            return null;
        }

        return $this->store($user, $record->password);
    }

    public function confirm(string $token): bool
    {
        try {
            $payload = JWT::decode($token, new Key($this->secret(), 'HS256'));
        } catch (Throwable) {
            return false;
        }

        if (($payload->purpose ?? null) !== 'password_change_confirmation'
            || ! isset($payload->sub, $payload->jti)) {
            return false;
        }

        return DB::transaction(function () use ($payload): bool {
            $email = mb_strtolower((string) $payload->sub);
            $record = DB::table('password_change_confirmations')->where('email', $email)->lockForUpdate()->first();

            if (! $record || ! Hash::check((string) $payload->jti, $record->token)) {
                return false;
            }

            return $this->apply($email, $record->password);
        });
    }

    /**
     * Confirma a alteração com o código de 6 dígitos digitado no app pelo usuário autenticado.
     */
    public function confirmWithCode(User $user, string $code): bool
    {
        return DB::transaction(function () use ($user, $code): bool {
            $record = DB::table('password_change_confirmations')
                ->where('email', $user->email)
                ->lockForUpdate()
                ->first();

            if (! $record || $record->code === null) {
                return false;
            }

            if ($this->isExpired($record->created_at) || $record->attempts >= self::MAX_CODE_ATTEMPTS) {
                DB::table('password_change_confirmations')->where('email', $user->email)->delete();

                return false;
            }

            if (! Hash::check($code, $record->code)) {
                DB::table('password_change_confirmations')
                    ->where('email', $user->email)
                    ->increment('attempts');

                return false;
            }

            return $this->apply($user->email, $record->password);
        });
    }

    /**
     * @return array{token: string, code: string}
     */
    private function store(User $user, string $hashedPassword): array
    {
        $now = now();
        $jti = Str::random(64);
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        DB::table('password_change_confirmations')->updateOrInsert(
            ['email' => $user->email],
            [
                'token' => Hash::make($jti),
                'code' => Hash::make($code),
                'attempts' => 0,
                'password' => $hashedPassword,
                'created_at' => $now,
            ],
        );

        $token = JWT::encode([
            'sub' => $user->email,
            'purpose' => 'password_change_confirmation',
            'jti' => $jti,
            'iat' => $now->timestamp,
            'exp' => $now->copy()->addMinutes(config('jwt.password_change_ttl'))->timestamp,
        ], $this->secret(), 'HS256');

        return ['token' => $token, 'code' => $code];
    }

    /**
     * Aplica a nova senha, encerra todas as sessões e consome a solicitação (uso único).
     * Deve ser chamado dentro de uma transação, com o registro já bloqueado.
     */
    private function apply(string $email, string $hashedPassword): bool
    {
        $user = User::where('email', $email)->first();

        if (! $user) {
            return false;
        }

        DB::table('users')->where('id', $user->id)->update([
            'password' => $hashedPassword,
            'remember_token' => Str::random(60),
            'updated_at' => now(),
        ]);
        $user->tokens()->delete();
        DB::table('password_change_confirmations')->where('email', $email)->delete();

        return true;
    }

    private function isExpired(?string $createdAt): bool
    {
        if ($createdAt === null) {
            return true;
        }

        return now()->greaterThan(
            \Illuminate\Support\Carbon::parse($createdAt)->addMinutes((int) config('jwt.password_change_ttl'))
        );
    }

    private function secret(): string
    {
        $secret = (string) config('jwt.secret');

        if ($secret === '') {
            throw new \RuntimeException('JWT_SECRET não configurado.');
        }

        return $secret;
    }
}