<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\PasswordChangeConfirmationNotification;
use App\Notifications\PasswordResetJwtNotification;
use App\Services\PasswordChangeConfirmationService;
use App\Services\PasswordResetJwtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_and_receives_a_token(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Ana Silva',
            'email' => 'ana@example.com',
            'phone' => '(88) 99999-1234',
            'password' => 'senha-segura-123',
            'password_confirmation' => 'senha-segura-123',
            'device_name' => 'android',
        ]);

        $response->assertCreated()
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonPath('user.email', 'ana@example.com')
            ->assertJsonPath('user.phone', '+5588999991234')
            ->assertJsonPath('user.role', 'client')
            ->assertJsonPath('user.is_landlord', false)
            ->assertJsonPath('user.available_tabs', ['cliente'])
            ->assertJsonStructure(['access_token', 'expires_at']);

        $this->assertDatabaseHas('users', ['email' => 'ana@example.com', 'phone' => '+5588999991234']);
    }

    public function test_phone_is_required_when_registering(): void
    {
        $this->postJson('/api/auth/register', [
            'name' => 'Ana Silva',
            'email' => 'ana@example.com',
            'password' => 'senha-segura-123',
            'password_confirmation' => 'senha-segura-123',
        ])->assertUnprocessable()->assertJsonValidationErrors('phone');
    }

    public function test_user_can_log_in_with_valid_credentials(): void
    {
        $user = User::factory()->create(['email' => 'ana@example.com', 'password' => Hash::make('senha-segura-123')]);

        $response = $this->postJson('/api/auth/login', [
            'login' => $user->email,
            'password' => 'senha-segura-123',
            'remember' => true,
        ]);

        $response->assertOk()->assertJsonPath('user.id', $user->id)->assertJsonStructure(['access_token']);
    }

    public function test_login_identifies_a_landlord_and_releases_the_landlord_tab(): void
    {
        $user = User::factory()->landlord()->create([
            'email' => 'locador@example.com',
            'password' => Hash::make('senha-segura-123'),
        ]);

        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'senha-segura-123',
        ])->assertOk()
            ->assertJsonPath('user.role', 'landlord')
            ->assertJsonPath('user.is_landlord', true)
            ->assertJsonPath('user.available_tabs', ['cliente', 'locador']);
    }

    public function test_invalid_credentials_are_rejected(): void
    {
        User::factory()->create(['email' => 'ana@example.com', 'password' => Hash::make('senha-segura-123')]);

        $this->postJson('/api/auth/login', ['email' => 'ana@example.com', 'password' => 'incorreta'])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'E-mail ou senha inválidos.');
    }

    public function test_password_recovery_sends_a_jwt_by_email(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'ana@example.com']);

        $this->postJson('/api/auth/forgot-password', ['email' => $user->email])->assertOk();

        Notification::assertSentTo($user, PasswordResetJwtNotification::class, function ($notification): bool {
            $this->assertCount(3, explode('.', $notification->token));

            return true;
        });
    }

    public function test_password_can_be_reset_once_with_a_valid_jwt(): void
    {
        $user = User::factory()->create(['email' => 'ana@example.com']);
        $token = app(PasswordResetJwtService::class)->create($user);
        $payload = [
            'email' => $user->email,
            'token' => $token,
            'password' => 'nova-senha-segura-123',
            'password_confirmation' => 'nova-senha-segura-123',
        ];

        $this->postJson('/api/auth/reset-password', $payload)->assertOk();
        $this->assertTrue(Hash::check('nova-senha-segura-123', $user->fresh()->password));
        $this->postJson('/api/auth/reset-password', $payload)->assertUnprocessable();
    }

    public function test_authenticated_user_can_request_password_change_confirmation_by_email(): void
    {
        Notification::fake();
        $user = User::factory()->create(['password' => Hash::make('senha-atual-123')]);
        Sanctum::actingAs($user);

        $this->postJson('/api/auth/change-password', [
            'current_password' => 'senha-atual-123',
            'password' => 'nova-senha-segura-123',
            'password_confirmation' => 'nova-senha-segura-123',
        ])->assertStatus(202)
            ->assertJsonPath('message', 'Enviamos um e-mail para confirmar a alteração da sua senha.');

        $this->assertDatabaseHas('password_change_confirmations', ['email' => $user->email]);
        Notification::assertSentTo($user, PasswordChangeConfirmationNotification::class, function ($notification): bool {
            $this->assertCount(3, explode('.', $notification->token));

            return true;
        });
    }

    public function test_current_password_is_required_to_request_a_change(): void
    {
        Notification::fake();
        $user = User::factory()->create(['password' => Hash::make('senha-atual-123')]);
        Sanctum::actingAs($user);

        $this->postJson('/api/auth/change-password', [
            'current_password' => 'senha-incorreta',
            'password' => 'nova-senha-segura-123',
            'password_confirmation' => 'nova-senha-segura-123',
        ])->assertUnprocessable()->assertJsonValidationErrors('current_password');

        $this->assertDatabaseMissing('password_change_confirmations', ['email' => $user->email]);
        Notification::assertNothingSent();
    }

    public function test_password_change_confirmation_updates_password_once_and_revokes_tokens(): void
    {
        $user = User::factory()->create(['password' => Hash::make('senha-atual-123')]);
        $user->createToken('old-device');
        $token = app(PasswordChangeConfirmationService::class)->create($user, 'nova-senha-segura-123');

        $this->postJson('/api/auth/confirm-password-change', ['token' => $token])->assertOk();

        $this->assertTrue(Hash::check('nova-senha-segura-123', $user->fresh()->password));
        $this->assertDatabaseMissing('password_change_confirmations', ['email' => $user->email]);
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->postJson('/api/auth/confirm-password-change', ['token' => $token])->assertUnprocessable();
    }
}
