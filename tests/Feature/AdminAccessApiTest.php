<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminAccessApiTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(UserRole $role): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => $role])->save();

        return $user;
    }

    public function test_guest_cannot_access_admin_routes(): void
    {
        $this->getJson('/api/admin/overview')->assertUnauthorized();
    }

    public function test_client_cannot_access_admin_routes(): void
    {
        Sanctum::actingAs($this->userWithRole(UserRole::CLIENT));

        $this->getJson('/api/admin/overview')->assertForbidden();
        $this->getJson('/api/admin/landlord-requests')->assertForbidden();
    }

    public function test_landlord_cannot_access_admin_routes(): void
    {
        Sanctum::actingAs($this->userWithRole(UserRole::LANDLORD));

        $this->getJson('/api/admin/overview')->assertForbidden();
        $this->getJson('/api/admin/landlord-requests')->assertForbidden();
    }

    public function test_admin_can_view_overview_and_requests(): void
    {
        Sanctum::actingAs($this->userWithRole(UserRole::ADMIN));

        $this->getJson('/api/admin/overview')
            ->assertOk()
            ->assertJsonPath('data.landlord_requests.pending', 0)
            ->assertJsonPath('data.review_deadline_hours', 48);

        $this->getJson('/api/admin/landlord-requests')->assertOk();
    }

    public function test_admin_cannot_manage_other_admins(): void
    {
        Sanctum::actingAs($this->userWithRole(UserRole::ADMIN));

        $this->getJson('/api/admin/admins')->assertForbidden();
        $this->postJson('/api/admin/admins', [])->assertForbidden();
    }

    public function test_super_admin_can_create_an_admin(): void
    {
        Sanctum::actingAs($this->userWithRole(UserRole::SUPER_ADMIN));

        $this->postJson('/api/admin/admins', [
            'name' => 'Nova Pessoa',
            'email' => 'nova@exemplo.com',
            'password' => 'SenhaForte123',
            'password_confirmation' => 'SenhaForte123',
        ])
            ->assertCreated()
            ->assertJsonPath('data.role', 'admin');

        $this->assertSame(
            UserRole::ADMIN,
            User::where('email', 'nova@exemplo.com')->firstOrFail()->role,
        );
    }

    public function test_super_admin_cannot_create_admin_with_weak_password(): void
    {
        Sanctum::actingAs($this->userWithRole(UserRole::SUPER_ADMIN));

        $this->postJson('/api/admin/admins', [
            'name' => 'Nova Pessoa',
            'email' => 'nova@exemplo.com',
            'password' => '123',
            'password_confirmation' => '123',
        ])->assertUnprocessable();

        $this->assertDatabaseMissing('users', ['email' => 'nova@exemplo.com']);
    }

    public function test_removing_an_admin_demotes_the_account_and_revokes_tokens(): void
    {
        $admin = $this->userWithRole(UserRole::ADMIN);
        $admin->createToken('teste');

        Sanctum::actingAs($this->userWithRole(UserRole::SUPER_ADMIN));

        $this->deleteJson("/api/admin/admins/{$admin->id}")->assertOk();

        $this->assertSame(UserRole::CLIENT, $admin->fresh()->role);
        $this->assertSame(0, $admin->fresh()->tokens()->count());
    }

    public function test_super_admin_cannot_be_removed_through_the_api(): void
    {
        $other = $this->userWithRole(UserRole::SUPER_ADMIN);

        Sanctum::actingAs($this->userWithRole(UserRole::SUPER_ADMIN));

        $this->deleteJson("/api/admin/admins/{$other->id}")->assertUnprocessable();

        $this->assertSame(UserRole::SUPER_ADMIN, $other->fresh()->role);
    }

    public function test_a_regular_user_cannot_be_removed_as_admin(): void
    {
        $client = $this->userWithRole(UserRole::CLIENT);

        Sanctum::actingAs($this->userWithRole(UserRole::SUPER_ADMIN));

        $this->deleteJson("/api/admin/admins/{$client->id}")->assertNotFound();
    }
}