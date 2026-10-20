<?php

namespace Tests\Feature;

use App\Enums\LandlordRequestStatus;
use App\Enums\UserRole;
use App\Models\LandlordRequest;
use App\Models\User;
use App\Services\LandlordRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LandlordRequestApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_submit_a_request(): void
    {
        $this->postJson('/api/landlord-request', [])->assertUnauthorized();
    }

    public function test_client_can_submit_a_request_and_it_starts_as_pending(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/landlord-request', ['message' => 'Tenho duas casas no Crajubar.'])
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.message', 'Tenho duas casas no Crajubar.');

        $this->assertDatabaseHas('landlord_requests', [
            'user_id' => $user->id,
            'status' => 'pending',
        ]);
        $this->assertSame(UserRole::CLIENT, $user->fresh()->role);
    }

    public function test_user_cannot_have_two_pending_requests(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/landlord-request', [])->assertCreated();
        $this->postJson('/api/landlord-request', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('landlord_request');

        $this->assertDatabaseCount('landlord_requests', 1);
    }

    public function test_landlord_cannot_submit_a_request(): void
    {
        Sanctum::actingAs(User::factory()->landlord()->create());

        $this->postJson('/api/landlord-request', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('landlord_request');
    }

    public function test_user_can_submit_again_after_a_rejection(): void
    {
        $user = User::factory()->create();
        LandlordRequest::factory()->for($user)->rejected()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/landlord-request', [])->assertCreated();

        $this->assertDatabaseCount('landlord_requests', 2);
    }

       public function test_user_can_see_the_status_of_the_latest_request(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/landlord-request')->assertOk()->assertJsonPath('data', null);

        LandlordRequest::factory()->for($user)->rejected()->create();

        // Em produção cada requisição carrega um User novo do banco. No teste, o mesmo objeto
        // permanece no guard com a relação já em cache (null), então autenticamos de novo
        // com uma instância nova para simular uma nova requisição.
        Sanctum::actingAs($user->fresh());

        $this->getJson('/api/landlord-request')
            ->assertOk()
            ->assertJsonPath('data.status', 'rejected')
            ->assertJsonPath('data.rejection_reason', 'Dados insuficientes.');
    }

    public function test_non_admins_cannot_review_requests(): void
    {
        $landlordRequest = LandlordRequest::factory()->create();

        foreach ([User::factory()->create(), User::factory()->landlord()->create()] as $user) {
            Sanctum::actingAs($user);

            $this->getJson('/api/admin/landlord-requests')->assertForbidden();
            $this->postJson("/api/admin/landlord-requests/{$landlordRequest->id}/approve")->assertForbidden();
            $this->postJson("/api/admin/landlord-requests/{$landlordRequest->id}/reject")->assertForbidden();
        }

        $this->assertSame(LandlordRequestStatus::PENDING, $landlordRequest->fresh()->status);
        $this->assertSame(UserRole::CLIENT, $landlordRequest->user->fresh()->role);
    }

    public function test_admin_can_list_requests_filtered_by_status(): void
    {
        LandlordRequest::factory()->count(2)->create();
        LandlordRequest::factory()->rejected()->create();
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->getJson('/api/admin/landlord-requests')
            ->assertOk()
            ->assertJsonPath('meta.total', 3);

        $this->getJson('/api/admin/landlord-requests?status=pending')
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonStructure(['data' => [['id', 'status', 'user' => ['id', 'name', 'email', 'phone']]]]);

        $this->getJson('/api/admin/landlord-requests?status=invalido')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    }

    public function test_admin_approval_promotes_the_applicant_to_landlord(): void
    {
        $admin = User::factory()->admin()->create();
        $applicant = User::factory()->create();
        $landlordRequest = LandlordRequest::factory()->for($applicant)->create();
        Sanctum::actingAs($admin);

        $this->postJson("/api/admin/landlord-requests/{$landlordRequest->id}/approve")
            ->assertOk()
            ->assertJsonPath('data.status', 'approved');

        $this->assertSame(UserRole::LANDLORD, $applicant->fresh()->role);
        $this->assertDatabaseHas('landlord_requests', [
            'id' => $landlordRequest->id,
            'status' => 'approved',
            'reviewed_by' => $admin->id,
        ]);
    }

    public function test_login_and_profile_reflect_the_approval(): void
    {
        $admin = User::factory()->admin()->create();
        $applicant = User::factory()->create(['password' => Hash::make('senha-segura-123')]);
        $landlordRequest = LandlordRequest::factory()->for($applicant)->create();

        $this->postJson('/api/auth/login', ['email' => $applicant->email, 'password' => 'senha-segura-123'])
            ->assertOk()
            ->assertJsonPath('user.is_landlord', false)
            ->assertJsonPath('user.available_tabs', ['cliente'])
            ->assertJsonPath('user.landlord_request_status', 'pending');

        app(LandlordRequestService::class)->approve($landlordRequest, $admin);

        $this->postJson('/api/auth/login', ['email' => $applicant->email, 'password' => 'senha-segura-123'])
            ->assertOk()
            ->assertJsonPath('user.is_landlord', true)
            ->assertJsonPath('user.available_tabs', ['cliente', 'locador'])
            ->assertJsonPath('user.landlord_request_status', 'approved');

        Sanctum::actingAs($applicant->fresh());
        $this->getJson('/api/auth/profile')
            ->assertOk()
            ->assertJsonPath('user.is_landlord', true)
            ->assertJsonPath('user.landlord_request_status', 'approved');
    }

    public function test_admin_rejection_keeps_the_applicant_as_client(): void
    {
        $admin = User::factory()->admin()->create();
        $applicant = User::factory()->create();
        $landlordRequest = LandlordRequest::factory()->for($applicant)->create();
        Sanctum::actingAs($admin);

        $this->postJson("/api/admin/landlord-requests/{$landlordRequest->id}/reject", [
            'rejection_reason' => 'Documentação incompleta.',
        ])->assertOk()->assertJsonPath('data.status', 'rejected');

        $this->assertSame(UserRole::CLIENT, $applicant->fresh()->role);
        $this->assertDatabaseHas('landlord_requests', [
            'id' => $landlordRequest->id,
            'status' => 'rejected',
            'rejection_reason' => 'Documentação incompleta.',
            'reviewed_by' => $admin->id,
        ]);
    }

    public function test_a_reviewed_request_cannot_be_changed_again(): void
    {
        $admin = User::factory()->admin()->create();
        $approved = LandlordRequest::factory()->approved()->create();
        $rejected = LandlordRequest::factory()->rejected()->create();
        Sanctum::actingAs($admin);

        $this->postJson("/api/admin/landlord-requests/{$approved->id}/reject")
            ->assertUnprocessable()->assertJsonValidationErrors('landlord_request');
        $this->postJson("/api/admin/landlord-requests/{$rejected->id}/approve")
            ->assertUnprocessable()->assertJsonValidationErrors('landlord_request');

        $this->assertSame(LandlordRequestStatus::APPROVED, $approved->fresh()->status);
        $this->assertSame(LandlordRequestStatus::REJECTED, $rejected->fresh()->status);
        $this->assertSame(UserRole::CLIENT, $rejected->user->fresh()->role);
    }
}