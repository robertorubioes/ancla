<?php

declare(strict_types=1);

namespace Tests\Unit\Signing;

use App\Models\Document;
use App\Models\Signer;
use App\Models\SigningProcess;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Signing\SigningAccessException;
use App\Services\Signing\SigningAccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SigningAccessServiceTest extends TestCase
{
    use RefreshDatabase;

    private SigningAccessService $service;

    private Tenant $tenant;

    private User $user;

    private Document $document;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(SigningAccessService::class);

        // Create test tenant and user
        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->document = Document::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
        ]);

        // Set tenant context
        app()->instance('tenant', $this->tenant);
        $this->actingAs($this->user);
    }

    public function test_validates_access_for_valid_token(): void
    {
        $process = SigningProcess::factory()->create([
            'tenant_id' => $this->tenant->id,
            'document_id' => $this->document->id,
            'created_by' => $this->user->id,
            'status' => SigningProcess::STATUS_SENT,
            'signature_order' => SigningProcess::ORDER_PARALLEL,
        ]);

        $signer = Signer::factory()->create([
            'signing_process_id' => $process->id,
            'status' => Signer::STATUS_SENT,
            'order' => 1,
        ]);

        $result = $this->service->validateAccess($signer->token);

        $this->assertTrue($result->isAllowed());
        $this->assertTrue($result->canSign);
        $this->assertFalse($result->alreadySigned);
        $this->assertSame($signer->id, $result->signer->id);
    }

    public function test_throws_exception_for_invalid_token(): void
    {
        $this->expectException(SigningAccessException::class);
        $this->expectExceptionCode(SigningAccessException::CODE_TOKEN_NOT_FOUND);

        $this->service->validateAccess('invalid-token-12345678901234567');
    }

    public function test_records_audit_trail_on_first_access(): void
    {
        $process = SigningProcess::factory()->create([
            'tenant_id' => $this->tenant->id,
            'document_id' => $this->document->id,
            'created_by' => $this->user->id,
            'status' => SigningProcess::STATUS_SENT,
        ]);

        $signer = Signer::factory()->create([
            'signing_process_id' => $process->id,
            'status' => Signer::STATUS_SENT,
            'viewed_at' => null,
        ]);

        $result = $this->service->validateAccess($signer->token);

        $this->assertTrue($result->isFirstVisit());

        // Verify signer status updated
        $signer->refresh();
        $this->assertSame(Signer::STATUS_VIEWED, $signer->status);
        $this->assertNotNull($signer->viewed_at);

        // Verify audit trail entry created
        $this->assertDatabaseHas('audit_trail_entries', [
            'auditable_type' => SigningProcess::class,
            'auditable_id' => $process->id,
            'event_type' => 'signer.accessed',
        ]);
    }

    public function test_does_not_duplicate_audit_on_second_access(): void
    {
        $process = SigningProcess::factory()->create([
            'tenant_id' => $this->tenant->id,
            'document_id' => $this->document->id,
            'created_by' => $this->user->id,
            'status' => SigningProcess::STATUS_IN_PROGRESS,
        ]);

        $signer = Signer::factory()->create([
            'signing_process_id' => $process->id,
            'status' => Signer::STATUS_VIEWED,
            'viewed_at' => now()->subHour(),
        ]);

        $initialCount = $process->auditTrailEntries()->count();

        $result = $this->service->validateAccess($signer->token);

        $this->assertFalse($result->isFirstVisit());
        $this->assertSame($initialCount, $process->auditTrailEntries()->count());
    }

    public function test_throws_exception_for_expired_process(): void
    {
        $process = SigningProcess::factory()->create([
            'tenant_id' => $this->tenant->id,
            'document_id' => $this->document->id,
            'created_by' => $this->user->id,
            'status' => SigningProcess::STATUS_SENT,
            'deadline_at' => now()->subDay(),
        ]);

        $signer = Signer::factory()->create([
            'signing_process_id' => $process->id,
            'status' => Signer::STATUS_SENT,
        ]);

        $this->expectException(SigningAccessException::class);
        $this->expectExceptionCode(SigningAccessException::CODE_PROCESS_EXPIRED);

        $this->service->validateAccess($signer->token);
    }

    public function test_returns_result_for_already_signed_signer(): void
    {
        $process = SigningProcess::factory()->create([
            'tenant_id' => $this->tenant->id,
            'document_id' => $this->document->id,
            'created_by' => $this->user->id,
            'status' => SigningProcess::STATUS_IN_PROGRESS,
        ]);

        $signer = Signer::factory()->create([
            'signing_process_id' => $process->id,
            'status' => Signer::STATUS_SIGNED,
            'signed_at' => now()->subHour(),
        ]);

        $result = $this->service->validateAccess($signer->token);

        $this->assertTrue($result->hasAlreadySigned());
        $this->assertFalse($result->canProceedToSign());
        $this->assertNotNull($result->errorMessage);
    }

    public function test_throws_exception_for_cancelled_process(): void
    {
        $process = SigningProcess::factory()->create([
            'tenant_id' => $this->tenant->id,
            'document_id' => $this->document->id,
            'created_by' => $this->user->id,
            'status' => SigningProcess::STATUS_CANCELLED,
        ]);

        $signer = Signer::factory()->create([
            'signing_process_id' => $process->id,
            'status' => Signer::STATUS_SENT,
        ]);

        $this->expectException(SigningAccessException::class);
        $this->expectExceptionCode(SigningAccessException::CODE_PROCESS_CANCELLED);

        $this->service->validateAccess($signer->token);
    }

    public function test_throws_exception_for_completed_process(): void
    {
        $process = SigningProcess::factory()->create([
            'tenant_id' => $this->tenant->id,
            'document_id' => $this->document->id,
            'created_by' => $this->user->id,
            'status' => SigningProcess::STATUS_COMPLETED,
            'completed_at' => now()->subHour(),
        ]);

        $signer = Signer::factory()->create([
            'signing_process_id' => $process->id,
            'status' => Signer::STATUS_SIGNED,
            'signed_at' => now()->subHour(),
        ]);

        $this->expectException(SigningAccessException::class);
        $this->expectExceptionCode(SigningAccessException::CODE_PROCESS_COMPLETED);

        $this->service->validateAccess($signer->token);
    }

    public function test_sequential_order_can_sign_if_previous_signed(): void
    {
        $process = SigningProcess::factory()->create([
            'tenant_id' => $this->tenant->id,
            'document_id' => $this->document->id,
            'created_by' => $this->user->id,
            'status' => SigningProcess::STATUS_IN_PROGRESS,
            'signature_order' => SigningProcess::ORDER_SEQUENTIAL,
        ]);

        $signer1 = Signer::factory()->create([
            'signing_process_id' => $process->id,
            'status' => Signer::STATUS_SIGNED,
            'signed_at' => now()->subHour(),
            'order' => 1,
        ]);

        $signer2 = Signer::factory()->create([
            'signing_process_id' => $process->id,
            'status' => Signer::STATUS_SENT,
            'order' => 2,
        ]);

        $result = $this->service->validateAccess($signer2->token);

        $this->assertTrue($result->canSign);
        $this->assertNull($result->waitingFor);
    }

    public function test_sequential_order_cannot_sign_if_previous_not_signed(): void
    {
        $process = SigningProcess::factory()->create([
            'tenant_id' => $this->tenant->id,
            'document_id' => $this->document->id,
            'created_by' => $this->user->id,
            'status' => SigningProcess::STATUS_IN_PROGRESS,
            'signature_order' => SigningProcess::ORDER_SEQUENTIAL,
        ]);

        $signer1 = Signer::factory()->create([
            'signing_process_id' => $process->id,
            'status' => Signer::STATUS_SENT,
            'order' => 1,
            'name' => 'John Doe',
        ]);

        $signer2 = Signer::factory()->create([
            'signing_process_id' => $process->id,
            'status' => Signer::STATUS_SENT,
            'order' => 2,
        ]);

        $result = $this->service->validateAccess($signer2->token);

        $this->assertFalse($result->canSign);
        $this->assertSame('John Doe', $result->waitingFor);
    }

    public function test_parallel_order_all_can_sign(): void
    {
        $process = SigningProcess::factory()->create([
            'tenant_id' => $this->tenant->id,
            'document_id' => $this->document->id,
            'created_by' => $this->user->id,
            'status' => SigningProcess::STATUS_SENT,
            'signature_order' => SigningProcess::ORDER_PARALLEL,
        ]);

        $signer1 = Signer::factory()->create([
            'signing_process_id' => $process->id,
            'status' => Signer::STATUS_SENT,
            'order' => 1,
        ]);

        $signer2 = Signer::factory()->create([
            'signing_process_id' => $process->id,
            'status' => Signer::STATUS_SENT,
            'order' => 2,
        ]);

        $result1 = $this->service->validateAccess($signer1->token);
        $result2 = $this->service->validateAccess($signer2->token);

        $this->assertTrue($result1->canSign);
        $this->assertTrue($result2->canSign);
        $this->assertNull($result1->waitingFor);
        $this->assertNull($result2->waitingFor);
    }

    public function test_updates_process_status_to_in_progress(): void
    {
        $process = SigningProcess::factory()->create([
            'tenant_id' => $this->tenant->id,
            'document_id' => $this->document->id,
            'created_by' => $this->user->id,
            'status' => SigningProcess::STATUS_SENT,
        ]);

        $signer = Signer::factory()->create([
            'signing_process_id' => $process->id,
            'status' => Signer::STATUS_SENT,
        ]);

        $this->service->validateAccess($signer->token);

        $process->refresh();
        $this->assertSame(SigningProcess::STATUS_IN_PROGRESS, $process->status);
    }

    public function test_can_sign_method_for_parallel_process(): void
    {
        $process = SigningProcess::factory()->create([
            'tenant_id' => $this->tenant->id,
            'document_id' => $this->document->id,
            'created_by' => $this->user->id,
            'status' => SigningProcess::STATUS_IN_PROGRESS,
            'signature_order' => SigningProcess::ORDER_PARALLEL,
        ]);

        $signer = Signer::factory()->create([
            'signing_process_id' => $process->id,
            'status' => Signer::STATUS_VIEWED,
        ]);

        $this->assertTrue($this->service->canSign($signer));
    }

    public function test_can_sign_returns_false_for_signed_signer(): void
    {
        $process = SigningProcess::factory()->create([
            'tenant_id' => $this->tenant->id,
            'document_id' => $this->document->id,
            'created_by' => $this->user->id,
            'status' => SigningProcess::STATUS_IN_PROGRESS,
        ]);

        $signer = Signer::factory()->create([
            'signing_process_id' => $process->id,
            'status' => Signer::STATUS_SIGNED,
            'signed_at' => now(),
        ]);

        $this->assertFalse($this->service->canSign($signer));
    }

    public function test_get_signer_by_token(): void
    {
        $process = SigningProcess::factory()->create([
            'tenant_id' => $this->tenant->id,
            'document_id' => $this->document->id,
            'created_by' => $this->user->id,
            'status' => SigningProcess::STATUS_SENT,
        ]);

        $signer = Signer::factory()->create([
            'signing_process_id' => $process->id,
            'status' => Signer::STATUS_SENT,
        ]);

        $result = $this->service->getSignerByToken($signer->token);

        $this->assertNotNull($result);
        $this->assertSame($signer->id, $result->id);
        $this->assertNotNull($result->signingProcess);
        $this->assertNotNull($result->signingProcess->document);
    }

    public function test_get_signer_by_token_returns_null_for_invalid(): void
    {
        $result = $this->service->getSignerByToken('invalid-token-123');

        $this->assertNull($result);
    }

    public function test_throws_exception_for_draft_process(): void
    {
        $process = SigningProcess::factory()->create([
            'tenant_id' => $this->tenant->id,
            'document_id' => $this->document->id,
            'created_by' => $this->user->id,
            'status' => SigningProcess::STATUS_DRAFT,
        ]);

        $signer = Signer::factory()->create([
            'signing_process_id' => $process->id,
            'status' => Signer::STATUS_PENDING,
        ]);

        $this->expectException(SigningAccessException::class);
        $this->expectExceptionCode(SigningAccessException::CODE_INVALID_STATUS);

        $this->service->validateAccess($signer->token);
    }

    public function test_includes_metadata_in_audit_trail(): void
    {
        $process = SigningProcess::factory()->create([
            'tenant_id' => $this->tenant->id,
            'document_id' => $this->document->id,
            'created_by' => $this->user->id,
            'status' => SigningProcess::STATUS_SENT,
        ]);

        $signer = Signer::factory()->create([
            'signing_process_id' => $process->id,
            'status' => Signer::STATUS_SENT,
            'viewed_at' => null,
        ]);

        $this->service->validateAccess($signer->token);

        $auditEntry = $process->auditTrailEntries()->where('event_type', 'signer.accessed')->first();

        $this->assertNotNull($auditEntry);
        $this->assertArrayHasKey('signer_id', $auditEntry->payload);
        $this->assertArrayHasKey('signer_email', $auditEntry->payload);
        $this->assertArrayHasKey('signer_name', $auditEntry->payload);
        $this->assertArrayHasKey('ip_address', $auditEntry->payload);
        $this->assertArrayHasKey('user_agent', $auditEntry->payload);
    }
}
