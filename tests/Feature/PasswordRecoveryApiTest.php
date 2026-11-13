<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\PasswordResetJwtNotification;
use App\Services\PasswordResetJwtService;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordRecoveryApiTest extends TestCase
{
    use RefreshDatabase;

    private const OLD_PASSWORD = 'senha-antiga-123';
    private const NEW_PASSWORD = 'nova-senha-segura-123';
    private const NEUTRAL_MESSAGE = 'Se o e-mail estiver cadastrado, enviaremos as instruções de recuperação.';
    private const INVALID_LINK_MESSAGE = 'O link de recuperação é inválido ou expirou.';

    public function test_unknown_email_gets_the_same_response_and_no_email_is_sent(): void
    {
        Notification::fake();
        $known = User::factory()->create(['email' => 'ana@example.com']);

        $knownResponse = $this->postJson('/api/auth/forgot-password', ['email' => 'ana@example.com']);
        $unknownResponse = $this->postJson('/api/auth/forgot-password', ['email' => 'ninguem@example.com']);

        $knownResponse->assertOk()->assertJsonPath('message', self::NEUTRAL_MESSAGE);
        $unknownResponse->assertOk()->assertJsonPath('message', self::NEUTRAL_MESSAGE);
        $this->assertSame($knownResponse->json(), $unknownResponse->json());

        Notification::assertSentToTimes($known, PasswordResetJwtNotification::class, 1);
        Notification::assertCount(1);
    }

    public function test_recovery_email_must_be_a_valid_address(): void
    {
        Notification::fake();

        $this->postJson('/api/auth/forgot-password', ['email' => 'isso-nao-e-email'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');

        Notification::assertNothingSent();
    }

    public function test_recovery_email_contains_the_deep_link_with_token_and_email(): void
    {
        $user = User::factory()->create(['email' => 'ana@example.com']);

        $mail = (new PasswordResetJwtNotification('aaa.bbb.ccc'))->toMail($user);

        $this->assertStringContainsString('token=aaa.bbb.ccc', (string) $mail->actionUrl);
        $this->assertStringContainsString('email=ana%40example.com', (string) $mail->actionUrl);
    }

    public function test_garbage_token_is_rejected_and_password_is_kept(): void
    {
        $user = $this->userWithOldPassword();

        $this->postJson('/api/auth/reset-password', $this->payload($user->email, 'token-invalido'))
            ->assertUnprocessable()
            ->assertJsonPath('message', self::INVALID_LINK_MESSAGE);

        $this->assertTrue(Hash::check(self::OLD_PASSWORD, $user->fresh()->password));
    }

    public function test_token_signed_with_another_secret_is_rejected(): void
    {
        $user = $this->userWithOldPassword();
        app(PasswordResetJwtService::class)->create($user);

        $forged = JWT::encode([
            'sub' => $user->email,
            'purpose' => 'password_reset',
            'jti' => 'jti-forjado',
            'iat' => time(),
            'exp' => time() + 3600,
        ], 'um-segredo-qualquer-diferente-do-da-aplicacao-123456', 'HS256');

        $this->postJson('/api/auth/reset-password', $this->payload($user->email, $forged))
            ->assertUnprocessable()
            ->assertJsonPath('message', self::INVALID_LINK_MESSAGE);

        $this->assertTrue(Hash::check(self::OLD_PASSWORD, $user->fresh()->password));
    }

    public function test_expired_token_is_rejected(): void
    {
        $user = $this->userWithOldPassword();
        $token = app(PasswordResetJwtService::class)->create($user);

        JWT::$timestamp = time() + ((int) config('jwt.ttl') + 1) * 60;

        try {
            $this->postJson('/api/auth/reset-password', $this->payload($user->email, $token))
                ->assertUnprocessable()
                ->assertJsonPath('message', self::INVALID_LINK_MESSAGE);
        } finally {
            JWT::$timestamp = null;
        }

        $this->assertTrue(Hash::check(self::OLD_PASSWORD, $user->fresh()->password));
    }

    public function test_token_cannot_be_used_with_another_account_email(): void
    {
        $ana = $this->userWithOldPassword();
        $bia = User::factory()->create(['email' => 'bia@example.com', 'password' => self::OLD_PASSWORD]);
        $token = app(PasswordResetJwtService::class)->create($ana);

        $this->postJson('/api/auth/reset-password', $this->payload($bia->email, $token))
            ->assertUnprocessable();

        $this->assertTrue(Hash::check(self::OLD_PASSWORD, $ana->fresh()->password));
        $this->assertTrue(Hash::check(self::OLD_PASSWORD, $bia->fresh()->password));
    }

    public function test_a_new_request_invalidates_the_previous_link(): void
    {
        $user = $this->userWithOldPassword();
        $service = app(PasswordResetJwtService::class);
        $first = $service->create($user);
        $second = $service->create($user);

        $this->postJson('/api/auth/reset-password', $this->payload($user->email, $first))
            ->assertUnprocessable();
        $this->postJson('/api/auth/reset-password', $this->payload($user->email, $second))
            ->assertOk();
    }

    public function test_invalid_new_password_is_rejected_without_consuming_the_link(): void
    {
        $user = $this->userWithOldPassword();
        $token = app(PasswordResetJwtService::class)->create($user);

        $this->postJson('/api/auth/reset-password', [
            'email' => $user->email,
            'token' => $token,
            'password' => 'curta',
            'password_confirmation' => 'curta',
        ])->assertUnprocessable()->assertJsonValidationErrors('password');

        $this->postJson('/api/auth/reset-password', [
            'email' => $user->email,
            'token' => $token,
            'password' => self::NEW_PASSWORD,
            'password_confirmation' => 'outra-senha-diferente-123',
        ])->assertUnprocessable()->assertJsonValidationErrors('password');

        $this->postJson('/api/auth/reset-password', $this->payload($user->email, $token))
            ->assertOk();
    }

    public function test_reset_revokes_sessions_and_only_the_new_password_logs_in(): void
    {
        $user = $this->userWithOldPassword();
        $user->createToken('celular');
        $token = app(PasswordResetJwtService::class)->create($user);

        $this->postJson('/api/auth/reset-password', $this->payload($user->email, $token))
            ->assertOk();

        $this->assertSame(0, $user->tokens()->count());

        $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => self::OLD_PASSWORD])
            ->assertUnprocessable();

        $login = $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => self::NEW_PASSWORD]);
        $login->assertOk();
        $this->assertArrayHasKey('access_token', $login->json());

        $this->postJson('/api/auth/reset-password', $this->payload($user->email, $token))
            ->assertUnprocessable();
    }

    public function test_email_delivery_failure_does_not_reveal_that_the_account_exists(): void
    {
        User::factory()->create(['email' => 'ana@example.com']);

        $this->mock(\Illuminate\Contracts\Notifications\Dispatcher::class, function ($mock) {
            $mock->shouldReceive('send')->andThrow(new \RuntimeException('SMTP fora do ar'));
        });

        $this->postJson('/api/auth/forgot-password', ['email' => 'ana@example.com'])
            ->assertOk()
            ->assertJsonPath('message', self::NEUTRAL_MESSAGE);
    }

    private function userWithOldPassword(): User
    {
        return User::factory()->create(['email' => 'ana@example.com', 'password' => self::OLD_PASSWORD]);
    }

    /** @return array<string, string> */
    private function payload(string $email, string $token): array
    {
        return [
            'email' => $email,
            'token' => $token,
            'password' => self::NEW_PASSWORD,
            'password_confirmation' => self::NEW_PASSWORD,
        ];
    }
}
