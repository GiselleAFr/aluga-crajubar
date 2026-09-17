<?php

namespace App\Services;

use App\Models\User;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Throwable;

class PasswordResetJwtService
{
    public function create(User $user): string
    {
        $now = now();
        $jti = Str::random(64);

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $user->email],
            ['token' => Hash::make($jti), 'created_at' => $now],
        );

        return JWT::encode([
            'sub' => $user->email,
            'purpose' => 'password_reset',
            'jti' => $jti,
            'iat' => $now->timestamp,
            'exp' => $now->copy()->addMinutes(config('jwt.ttl'))->timestamp,
        ], $this->secret(), 'HS256');
    }

    public function reset(string $token, string $email, string $password): bool
    {
        try {
            $payload = JWT::decode($token, new Key($this->secret(), 'HS256'));
        } catch (Throwable) {
            return false;
        }

        if (($payload->purpose ?? null) !== 'password_reset'
            || ! isset($payload->sub, $payload->jti)
            || ! hash_equals(mb_strtolower($email), mb_strtolower((string) $payload->sub))) {
            return false;
        }

        $record = DB::table('password_reset_tokens')->where('email', $email)->first();

        if (! $record || ! Hash::check((string) $payload->jti, $record->token)) {
            return false;
        }

        $user = User::where('email', $email)->first();

        if (! $user) {
            return false;
        }

        $user->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();
        $user->tokens()->delete();
        DB::table('password_reset_tokens')->where('email', $email)->delete();

        return true;
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
