<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\PasswordResetJwtNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PasswordResetBridgePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_bridge_page_is_publicly_available(): void
    {
        $this->get('/reset-password')
            ->assertOk()
            ->assertSee('Abrir no aplicativo');
    }

    public function test_email_uses_the_https_bridge_page_when_configured(): void
    {
        config(['jwt.reset_web_url' => 'https://exemplo.com/reset-password']);
        $user = User::factory()->create(['email' => 'ana@example.com']);

        $mail = (new PasswordResetJwtNotification('aaa.bbb.ccc'))->toMail($user);

        $this->assertSame(
            'https://exemplo.com/reset-password#token=aaa.bbb.ccc&email=ana%40example.com',
            $mail->actionUrl,
        );
    }

    public function test_email_falls_back_to_the_deep_link_without_a_web_url(): void
    {
        config([
            'jwt.reset_web_url' => null,
            'jwt.reset_deep_link' => 'alugacrajubar://reset-password',
        ]);
        $user = User::factory()->create(['email' => 'ana@example.com']);

        $mail = (new PasswordResetJwtNotification('aaa.bbb.ccc'))->toMail($user);

        $this->assertSame(
            'alugacrajubar://reset-password?token=aaa.bbb.ccc&email=ana%40example.com',
            $mail->actionUrl,
        );
    }
}