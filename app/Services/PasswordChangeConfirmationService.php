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
    public function create(User $user, string $newPassword): string
    {
        $now = now();
        $jti = Str::random(64);

        DB::table('password_change_confirmations')->updateOrInsert(
            ['email' => $user->email],
            [
                'token' => Hash::make($jti),
                'password' => Hash::make($newPassword),
                'created_at' => $now,
            ],
        );

        return JWT::encode([
            'sub' => $user->email,
            'purpose' => 'password_change_confirmation',
            'jti' => $jti,
            'iat' => $now->timestamp,
            'exp' => $now->copy()->addMinutes(config('jwt.password_change_ttl'))->timestamp,
        ], $this->secret(), 'HS256');
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

            $user = User::where('email', $email)->first();

            if (! $user) {
                return false;
            }

            DB::table('users')->where('id', $user->id)->update([
                'password' => $record->password,
                'remember_token' => Str::random(60),
                'updated_at' => now(),
            ]);
            $user->tokens()->delete();
            DB::table('password_change_confirmations')->where('email', $email)->delete();

            return true;
        });
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
