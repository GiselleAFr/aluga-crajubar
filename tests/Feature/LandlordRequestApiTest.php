<?php

namespace Tests\Feature;

use App\Enums\LandlordRequestStatus;
use App\Enums\UserRole;
use App\Models\LandlordRequest;
use App\Models\User;
use App\Services\LandlordRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LandlordRequestApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_submit_a_request(): void
    {
        $this->postJson('/api/landlord-request', [])->assertUnauthorized();
    }

    public function test_client_can_submit_form_details_and_it_starts_as_pending(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $this->submitLandlordRequest([
            'message' => 'Tenho duas casas no Crajubar.',
        ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.full_name', 'Ana Maria Silva')
            ->assertJsonPath('data.cpf', '52998224725')
            ->assertJsonPath('data.birth_date', '1990-11-08')
            ->assertJsonPath('data.document_type', 'cnh')
            ->assertJsonPath('data.email', 'ana@example.com')
            ->assertJsonPath('data.primary_phone', '+5588999991234')
            ->assertJsonPath('data.secondary_phone', '+558833334444')
            ->assertJsonPath('data.commercial_phone', null)
            ->assertJsonPath('data.message', 'Tenho duas casas no Crajubar.');

        $this->assertDatabaseHas('landlord_requests', [
            'user_id' => $user->id,
            'status' => 'pending',
            'full_name' => 'Ana Maria Silva',
            'cpf' => '52998224725',
            'document_type' => 'cnh',
            'email' => 'ana@example.com',
            'primary_phone' => '+5588999991234',
            'secondary_phone' => '+558833334444',
            'commercial_phone' => null,
        ]);
        $landlordRequest = LandlordRequest::query()->where('user_id', $user->id)->firstOrFail();
        Storage::disk('local')->assertExists($landlordRequest->document_front_path);
        Storage::disk('local')->assertExists($landlordRequest->document_back_path);
        $this->assertSame(
            '1990-11-08',
            LandlordRequest::query()
                ->where('user_id', $user->id)
                ->firstOrFail()
                ->birth_date
                ->toDateString(),
        );
        $this->assertSame(UserRole::CLIENT, $user->fresh()->role);
    }

    public function test_submission_requires_valid_personal_and_contact_details(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/landlord-request', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'full_name',
                'cpf',
                'birth_date',
                'document_type',
                'primary_phone',
                'document_front',
                'document_back',
            ]);

        $this->submitLandlordRequest([
            'cpf' => '123',
            'birth_date' => '2999-01-01',
            'document_type' => 'invalid',
            'primary_phone' => '123',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'cpf',
                'birth_date',
                'document_type',
                'primary_phone',
            ]);

        $this->assertDatabaseCount('landlord_requests', 0);
        $this->assertSame(UserRole::CLIENT, $user->fresh()->role);
    }

    public function test_submission_rejects_non_images_and_images_over_10_mb(): void
    {
        Storage::fake('local');
        Sanctum::actingAs(User::factory()->create());

        $invalidImage = $this->submitLandlordRequest([
            'document_front' => UploadedFile::fake()->createWithContent('front.txt', 'not an image'),
        ]);
        $invalidImage->assertUnprocessable();
        $this->assertArrayHasKey('document_front', $invalidImage->json('errors'));

        $oversizedImage = $this->submitLandlordRequest([
            'document_front' => UploadedFile::fake()->create('front.png', 10241, 'image/png'),
        ]);
        $oversizedImage->assertUnprocessable();
        $this->assertStringContainsString(
            'no máximo 10 MB',
            implode(' ', $oversizedImage->json('errors.document_front')),
        );

        $this->assertDatabaseCount('landlord_requests', 0);
    }

    public function test_user_cannot_have_two_pending_requests(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->submitLandlordRequest()->assertCreated();
        $this->submitLandlordRequest()
            ->assertUnprocessable()
            ->assertJsonValidationErrors('landlord_request');

        $this->assertDatabaseCount('landlord_requests', 1);
    }

    public function test_landlord_cannot_submit_a_request(): void
    {
        Sanctum::actingAs(User::factory()->landlord()->create());

        $this->submitLandlordRequest()
            ->assertUnprocessable()
            ->assertJsonValidationErrors('landlord_request');
    }

    public function test_user_can_submit_again_after_a_rejection(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        LandlordRequest::factory()->for($user)->rejected()->create();
        Sanctum::actingAs($user);

        $this->submitLandlordRequest()
            ->assertCreated();

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
            $this->getJson("/api/admin/landlord-requests/{$landlordRequest->id}/documents/front")->assertForbidden();
            $this->postJson("/api/admin/landlord-requests/{$landlordRequest->id}/approve")->assertForbidden();
            $this->postJson("/api/admin/landlord-requests/{$landlordRequest->id}/reject")->assertForbidden();
        }

        $this->assertSame(LandlordRequestStatus::PENDING, $landlordRequest->fresh()->status);
        $this->assertSame(UserRole::CLIENT, $landlordRequest->user->fresh()->role);
    }

    public function test_admin_can_access_documents_only_while_request_is_pending(): void
    {
        Storage::fake('local');
        $landlordRequest = LandlordRequest::factory()->create();
        [$frontPath, $backPath] = $this->attachDocuments($landlordRequest);
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/landlord-requests')
            ->assertOk()
            ->assertJsonPath('data.0.documents.front', route(
                'admin.landlord-requests.documents.show',
                ['landlordRequest' => $landlordRequest->id, 'side' => 'front'],
            ))
            ->assertJsonPath('data.0.documents.back', route(
                'admin.landlord-requests.documents.show',
                ['landlordRequest' => $landlordRequest->id, 'side' => 'back'],
            ));

        $this->get("/api/admin/landlord-requests/{$landlordRequest->id}/documents/front")
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png')
            ->assertHeader('Cache-Control', 'no-store, private');

        app(LandlordRequestService::class)->reject(
            $landlordRequest,
            $admin,
            'Documentação incompleta.',
        );

        $this->get("/api/admin/landlord-requests/{$landlordRequest->id}/documents/front")
            ->assertNotFound();
        Storage::disk('local')->assertMissing($frontPath);
        Storage::disk('local')->assertMissing($backPath);
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
            ->assertJsonStructure(['data' => [[
                'id',
                'status',
                'full_name',
                'cpf',
                'birth_date',
                'document_type',
                'email',
                'primary_phone',
                'secondary_phone',
                'commercial_phone',
                'user' => ['id', 'name', 'email', 'phone'],
            ]]]);

        $this->getJson('/api/admin/landlord-requests?status=invalido')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    }

    public function test_admin_can_see_the_submitted_form_details(): void
    {
        LandlordRequest::factory()->create([
            'full_name' => 'Ana Maria Silva',
            'cpf' => '52998224725',
            'birth_date' => '1990-11-08',
            'document_type' => 'cnh',
            'email' => 'ana@example.com',
            'primary_phone' => '+5588999991234',
            'secondary_phone' => '+558833334444',
            'commercial_phone' => null,
        ]);
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->getJson('/api/admin/landlord-requests')
            ->assertOk()
            ->assertJsonPath('data.0.full_name', 'Ana Maria Silva')
            ->assertJsonPath('data.0.cpf', '52998224725')
            ->assertJsonPath('data.0.birth_date', '1990-11-08')
            ->assertJsonPath('data.0.document_type', 'cnh')
            ->assertJsonPath('data.0.email', 'ana@example.com')
            ->assertJsonPath('data.0.primary_phone', '+5588999991234')
            ->assertJsonPath('data.0.secondary_phone', '+558833334444')
            ->assertJsonPath('data.0.commercial_phone', null);
    }

    public function test_admin_approval_promotes_the_applicant_to_landlord(): void
    {
        Storage::fake('local');
        $admin = User::factory()->admin()->create();
        $applicant = User::factory()->create();
        $landlordRequest = LandlordRequest::factory()->for($applicant)->create();
        [$frontPath, $backPath] = $this->attachDocuments($landlordRequest);
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
        Storage::disk('local')->assertMissing($frontPath);
        Storage::disk('local')->assertMissing($backPath);
        $this->assertNull($landlordRequest->fresh()->document_front_path);
        $this->assertNull($landlordRequest->fresh()->document_back_path);
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
        Storage::fake('local');
        $admin = User::factory()->admin()->create();
        $applicant = User::factory()->create();
        $landlordRequest = LandlordRequest::factory()->for($applicant)->create();
        [$frontPath, $backPath] = $this->attachDocuments($landlordRequest);
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
        Storage::disk('local')->assertMissing($frontPath);
        Storage::disk('local')->assertMissing($backPath);
        $this->assertNull($landlordRequest->fresh()->document_front_path);
        $this->assertNull($landlordRequest->fresh()->document_back_path);
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

    /**
     * @return array<string, string|null>
     */
    private function validLandlordRequestData(array $overrides = []): array
    {
        return array_merge([
            'full_name' => 'Ana Maria Silva',
            'cpf' => '529.982.247-25',
            'birth_date' => '1990-11-08',
            'document_type' => 'cnh',
            'email' => ' ANA@EXAMPLE.COM ',
            'primary_phone' => '(88) 99999-1234',
            'secondary_phone' => '(88) 3333-4444',
            'commercial_phone' => '',
        ], $overrides);
    }

    private function submitLandlordRequest(array $overrides = [])
    {
        $data = array_merge($this->validLandlordRequestData(), [
            'document_front' => $this->png('front.png'),
            'document_back' => $this->png('back.png'),
        ], $overrides);

        return $this->withHeader('Accept', 'application/json')
            ->post('/api/landlord-request', $data);
    }

    private function attachDocuments(LandlordRequest $landlordRequest): array
    {
        $frontPath = 'landlord-requests/'.$landlordRequest->id.'/front.png';
        $backPath = 'landlord-requests/'.$landlordRequest->id.'/back.png';
        Storage::disk('local')->put($frontPath, $this->pngContents());
        Storage::disk('local')->put($backPath, $this->pngContents());
        $landlordRequest->forceFill([
            'document_front_path' => $frontPath,
            'document_back_path' => $backPath,
        ])->save();

        return [$frontPath, $backPath];
    }

    private function png(string $name): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, $this->pngContents());
    }

    private function pngContents(): string
    {
        return (string) base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/r9sAAAAASUVORK5CYII=');
    }
}
