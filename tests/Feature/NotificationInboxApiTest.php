<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\LandlordRequestApprovedNotification;
use App\Notifications\LandlordRequestRejectedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NotificationInboxApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_use_the_inbox(): void
    {
        $this->getJson('/api/notifications')->assertUnauthorized();
        $this->postJson('/api/notifications/read-all')->assertUnauthorized();
        $this->patchJson('/api/notifications/'.fake()->uuid().'/read')->assertUnauthorized();
    }

    public function test_inbox_is_paginated_newest_first_with_unread_counter(): void
    {
        $user = User::factory()->create();
        $this->notificationsFor($user, 3);
        Sanctum::actingAs($user);

        $first = $this->getJson('/api/notifications?per_page=2')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.unread_count', 3)
            ->assertJsonPath('data.0.type', 'landlord_request_approved')
            ->assertJsonPath('data.0.title', 'Solicitação aprovada')
            ->assertJsonPath('data.0.read', false);

        $second = $this->getJson('/api/notifications?per_page=2&page=2')->assertOk()->assertJsonCount(1, 'data');

        // Mais recente primeiro, sem repetir itens entre páginas.
        $ids = array_merge($first->json('data.*.id'), $second->json('data.*.id'));
        $this->assertCount(3, array_unique($ids));
        $createdAt = array_merge($first->json('data.*.created_at'), $second->json('data.*.created_at'));
        $sorted = $createdAt;
        rsort($sorted);
        $this->assertSame($sorted, $createdAt);
    }

    public function test_per_page_is_limited(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/notifications?per_page=500')->assertOk()->assertJsonPath('meta.per_page', 50);
        $this->getJson('/api/notifications?per_page=0')->assertOk()->assertJsonPath('meta.per_page', 1);
    }

    public function test_user_only_sees_their_own_notifications(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $this->notificationsFor($alice, 2);
        $this->notificationsFor($bob, 5);
        Sanctum::actingAs($alice);

        $this->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.unread_count', 2);
    }

    public function test_unread_filter_returns_only_unread_items(): void
    {
        $user = User::factory()->create();
        $this->notificationsFor($user, 3);
        $user->notifications()->first()->markAsRead();
        Sanctum::actingAs($user);

        $this->getJson('/api/notifications?unread=1')
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.unread_count', 2);

        $this->getJson('/api/notifications')->assertOk()->assertJsonPath('meta.total', 3);
    }

    public function test_user_can_mark_a_notification_as_read(): void
    {
        $user = User::factory()->create();
        $this->notificationsFor($user, 2);
        $notification = $user->notifications()->first();
        Sanctum::actingAs($user);

        $this->patchJson("/api/notifications/{$notification->id}/read")
            ->assertOk()
            ->assertJsonPath('data.id', $notification->id)
            ->assertJsonPath('data.read', true);

        $this->assertNotNull($notification->fresh()->read_at);
        $this->getJson('/api/notifications')->assertOk()->assertJsonPath('meta.unread_count', 1);

        // Repetir a chamada continua funcionando e não altera o que já estava lido.
        $this->patchJson("/api/notifications/{$notification->id}/read")->assertOk();
    }

    public function test_user_cannot_mark_another_users_notification_as_read(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $this->notificationsFor($bob, 1);
        $bobsNotification = $bob->notifications()->firstOrFail();
        Sanctum::actingAs($alice);

        $this->patchJson("/api/notifications/{$bobsNotification->id}/read")->assertNotFound();

        $this->assertNull($bobsNotification->fresh()->read_at);
    }

    public function test_unknown_or_malformed_ids_return_404(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->patchJson('/api/notifications/'.fake()->uuid().'/read')->assertNotFound();
        $this->patchJson('/api/notifications/nao-e-um-uuid/read')->assertNotFound();
    }

    public function test_read_all_marks_only_the_authenticated_users_notifications(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $this->notificationsFor($alice, 3);
        $this->notificationsFor($bob, 2);
        Sanctum::actingAs($alice);

        $this->postJson('/api/notifications/read-all')->assertOk()->assertJsonPath('updated', 3);

        $this->assertSame(0, $alice->unreadNotifications()->count());
        $this->assertSame(2, $bob->unreadNotifications()->count());
        $this->getJson('/api/notifications')->assertOk()->assertJsonPath('meta.unread_count', 0);
    }

    /**
     * Cria notificações em instantes diferentes: a última criada é a mais recente.
     */
    private function notificationsFor(User $user, int $count): void
    {
        $base = now()->startOfMinute();

        for ($i = 0; $i < $count; $i++) {
            $this->travelTo($base->copy()->addMinutes($i));

            $user->notify($i % 2 === 0
                ? new LandlordRequestApprovedNotification($i + 1)
                : new LandlordRequestRejectedNotification($i + 1, 'Motivo.'));
        }

        $this->travelBack();
    }
}
