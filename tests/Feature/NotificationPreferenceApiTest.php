<?php

namespace Tests\Feature;

use App\Enums\NotificationType;
use App\Models\LandlordRequest;
use App\Models\User;
use App\Notifications\PasswordResetJwtNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Events\NotificationSending;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use RuntimeException;
use Tests\TestCase;

class NotificationPreferenceApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_read_or_change_preferences(): void
    {
        $this->getJson('/api/notification-preferences')->assertUnauthorized();
        $this->patchJson('/api/notification-preferences', [])->assertUnauthorized();
    }

    public function test_new_user_has_every_type_enabled_by_default(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->getJson('/api/notification-preferences')->assertOk();

        $response->assertJsonCount(count(NotificationType::cases()), 'data');

        foreach (NotificationType::cases() as $index => $type) {
            $response
                ->assertJsonPath("data.{$index}.type", $type->value)
                ->assertJsonPath("data.{$index}.label", $type->label())
                ->assertJsonPath("data.{$index}.enabled", true);
        }
    }

    public function test_user_can_disable_a_type_and_it_persists(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->patchJson('/api/notification-preferences', [
            'preferences' => [['type' => 'landlord_request_approved', 'enabled' => false]],
        ])
            ->assertOk()
            ->assertJsonPath('data.0.type', 'landlord_request_approved')
            ->assertJsonPath('data.0.enabled', false)
            ->assertJsonPath('data.1.type', 'landlord_request_rejected')
            ->assertJsonPath('data.1.enabled', true);

        $this->getJson('/api/notification-preferences')
            ->assertOk()
            ->assertJsonPath('data.0.enabled', false)
            ->assertJsonPath('data.1.enabled', true);

        // Só o tipo alterado vira linha; o restante continua no padrão.
        $this->assertDatabaseCount('notification_preferences', 1);
        $this->assertDatabaseHas('notification_preferences', [
            'user_id' => $user->id,
            'type' => 'landlord_request_approved',
            'enabled' => false,
        ]);
    }

    public function test_updating_the_same_type_again_does_not_duplicate_rows(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->patchJson('/api/notification-preferences', [
            'preferences' => [['type' => 'landlord_request_rejected', 'enabled' => false]],
        ])->assertOk();

        $this->patchJson('/api/notification-preferences', [
            'preferences' => [['type' => 'landlord_request_rejected', 'enabled' => true]],
        ])->assertOk()->assertJsonPath('data.1.enabled', true);

        $this->assertDatabaseCount('notification_preferences', 1);
        $this->assertTrue($user->wantsNotification(NotificationType::LANDLORD_REQUEST_REJECTED));
    }

    public function test_several_types_can_be_updated_in_one_request(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->patchJson('/api/notification-preferences', [
            'preferences' => [
                ['type' => 'landlord_request_approved', 'enabled' => false],
                ['type' => 'landlord_request_rejected', 'enabled' => '0'],
            ],
        ])
            ->assertOk()
            ->assertJsonPath('data.0.enabled', false)
            ->assertJsonPath('data.1.enabled', false);

        $this->assertDatabaseCount('notification_preferences', 2);
    }

    /**
     * @dataProvider invalidPayloads
     *
     * @param  array<string, mixed>  $payload
     */
    public function test_invalid_payloads_are_rejected_with_422(array $payload, string $errorKey): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->patchJson('/api/notification-preferences', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors($errorKey);

        $this->assertDatabaseCount('notification_preferences', 0);
    }

    /**
     * @return array<string, array{0: array<string, mixed>, 1: string}>
     */
    public static function invalidPayloads(): array
    {
        return [
            'sem preferences' => [[], 'preferences'],
            'lista vazia' => [['preferences' => []], 'preferences'],
            'tipo inexistente' => [['preferences' => [['type' => 'promocoes', 'enabled' => true]]], 'preferences.0.type'],
            'tipo ausente' => [['preferences' => [['enabled' => true]]], 'preferences.0.type'],
            'enabled ausente' => [['preferences' => [['type' => 'landlord_request_approved']]], 'preferences.0.enabled'],
            'enabled inválido' => [['preferences' => [['type' => 'landlord_request_approved', 'enabled' => 'talvez']]], 'preferences.0.enabled'],
            'tipo repetido' => [['preferences' => [
                ['type' => 'landlord_request_approved', 'enabled' => true],
                ['type' => 'landlord_request_approved', 'enabled' => false],
            ]], 'preferences.0.type'],
        ];
    }

    public function test_a_user_only_changes_their_own_preferences(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();

        Sanctum::actingAs($alice);
        $this->patchJson('/api/notification-preferences', [
            'preferences' => [['type' => 'landlord_request_approved', 'enabled' => false]],
            // Tentativa de apontar para outro usuário: o campo é ignorado.
            'user_id' => $bob->id,
        ])->assertOk();

        $this->assertFalse($alice->wantsNotification(NotificationType::LANDLORD_REQUEST_APPROVED));
        $this->assertTrue($bob->wantsNotification(NotificationType::LANDLORD_REQUEST_APPROVED));
        $this->assertDatabaseMissing('notification_preferences', ['user_id' => $bob->id]);

        Sanctum::actingAs($bob->fresh());
        $this->getJson('/api/notification-preferences')->assertOk()->assertJsonPath('data.0.enabled', true);
    }

    public function test_approval_creates_an_inbox_notification_by_default(): void
    {
        $admin = User::factory()->admin()->create();
        $applicant = User::factory()->create();
        $landlordRequest = LandlordRequest::factory()->for($applicant)->create();
        Sanctum::actingAs($admin);

        $this->postJson("/api/admin/landlord-requests/{$landlordRequest->id}/approve")->assertOk();

        $this->assertDatabaseCount('notifications', 1);
        $notification = $applicant->notifications()->firstOrFail();
        $this->assertSame('landlord_request_approved', $notification->type);
        $this->assertSame($landlordRequest->id, $notification->data['landlord_request_id']);
        $this->assertNull($notification->read_at);
    }

    public function test_disabled_type_prevents_the_notification_but_not_the_approval(): void
    {
        $admin = User::factory()->admin()->create();
        $applicant = User::factory()->create();
        $applicant->notificationPreferences()->create(['type' => NotificationType::LANDLORD_REQUEST_APPROVED, 'enabled' => false]);
        $landlordRequest = LandlordRequest::factory()->for($applicant)->create();
        Sanctum::actingAs($admin);

        $this->postJson("/api/admin/landlord-requests/{$landlordRequest->id}/approve")
            ->assertOk()
            ->assertJsonPath('data.status', 'approved');

        $this->assertDatabaseCount('notifications', 0);
        $this->assertTrue($applicant->fresh()->isLandlord());
    }

    public function test_disabling_one_type_does_not_block_the_other_type(): void
    {
        $admin = User::factory()->admin()->create();
        $applicant = User::factory()->create();
        $applicant->notificationPreferences()->create(['type' => NotificationType::LANDLORD_REQUEST_APPROVED, 'enabled' => false]);
        $landlordRequest = LandlordRequest::factory()->for($applicant)->create();
        Sanctum::actingAs($admin);

        $this->postJson("/api/admin/landlord-requests/{$landlordRequest->id}/reject", [
            'rejection_reason' => 'Documentação incompleta.',
        ])->assertOk();

        $notification = $applicant->notifications()->firstOrFail();
        $this->assertSame('landlord_request_rejected', $notification->type);
        $this->assertSame('Documentação incompleta.', $notification->data['rejection_reason']);
    }

    public function test_disabled_rejection_type_prevents_the_notification(): void
    {
        $admin = User::factory()->admin()->create();
        $applicant = User::factory()->create();
        $applicant->notificationPreferences()->create(['type' => NotificationType::LANDLORD_REQUEST_REJECTED, 'enabled' => false]);
        $landlordRequest = LandlordRequest::factory()->for($applicant)->create();
        Sanctum::actingAs($admin);

        $this->postJson("/api/admin/landlord-requests/{$landlordRequest->id}/reject")->assertOk();

        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_one_users_preference_does_not_block_another_users_notification(): void
    {
        $admin = User::factory()->admin()->create();
        $optedOut = User::factory()->create();
        $optedOut->notificationPreferences()->create(['type' => NotificationType::LANDLORD_REQUEST_APPROVED, 'enabled' => false]);
        $other = User::factory()->create();
        $request = LandlordRequest::factory()->for($other)->create();
        Sanctum::actingAs($admin);

        $this->postJson("/api/admin/landlord-requests/{$request->id}/approve")->assertOk();

        $this->assertCount(1, $other->notifications);
        $this->assertCount(0, $optedOut->notifications);
    }

    public function test_security_emails_are_not_affected_by_preferences(): void
    {
        $user = User::factory()->create();
        foreach (NotificationType::cases() as $type) {
            $user->notificationPreferences()->create(['type' => $type, 'enabled' => false]);
        }
        Notification::fake();

        $this->postJson('/api/auth/forgot-password', ['email' => $user->email])->assertOk();

        Notification::assertSentTo($user, PasswordResetJwtNotification::class);
    }

    public function test_a_failure_while_notifying_does_not_undo_or_break_the_approval(): void
    {
        $admin = User::factory()->admin()->create();
        $applicant = User::factory()->create();
        $landlordRequest = LandlordRequest::factory()->for($applicant)->create();
        Event::listen(NotificationSending::class, function (): void {
            throw new RuntimeException('Falha simulada ao notificar.');
        });
        Sanctum::actingAs($admin);

        $this->postJson("/api/admin/landlord-requests/{$landlordRequest->id}/approve")
            ->assertOk()
            ->assertJsonPath('data.status', 'approved');

        $this->assertTrue($applicant->fresh()->isLandlord());
        $this->assertDatabaseCount('notifications', 0);
    }
}