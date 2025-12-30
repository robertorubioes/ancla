<?php

declare(strict_types=1);

namespace Tests\Unit\Notification;

use App\Jobs\SendSigningRequestJob;
use App\Models\AuditTrailEntry;
use App\Models\Document;
use App\Models\Signer;
use App\Models\SigningProcess;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Notification\SigningNotificationException;
use App\Services\Notification\SigningNotificationResult;
use App\Services\Notification\SigningNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SigningNotificationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected SigningNotificationService $service;

    protected Tenant $tenant;

    protected User $user;

    protected Document $document;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();

        $this->service = app(SigningNotificationService::class);

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->document = Document::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'status' => Document::STATUS_READY,
        ]);

        // Set tenant context
        app()->instance('tenant', $this->tenant);
    }

    /** @test */
    public function it_can_send_notifications_for_parallel_signing_process(): void
    {
        $process = SigningProcess::factory()->create([
            'tenant_id' => $this->tenant->id,
            'document_id' => $this->document->id,
            'created_by' => $this->user->id,
            'status' => SigningProcess::STATUS_DRAFT,
            'signature_order' => SigningProcess::ORDER_PARALLEL,
        ]);

        $signer1 = Signer::factory()->create([
            'signing_process_id' => $process->id,
            'order' => 0,
            'status' => Signer::STATUS_PENDING,
        ]);

        $signer2 = Signer::factory()->create([
            'signing_process_id' => $process->id,
            'order' => 1,
            'status' => Signer::STATUS_PENDING,
        ]);

        $result = $this->service->sendNotifications($process);

        $this->assertInstanceOf(SigningNotificationResult::class, $result);
        $this->assertTrue($result->success);
        $this->assertEquals(2, $result->totalSigners);
        $this->assertEquals(2, $result->notifiedCount);
        $this->assertTrue($result->allNotified());

        Queue::assertPushed(SendSigningRequestJob::class, 2);
        Queue::assertPushed(SendSigningRequestJob::class, function ($job) use ($process, $signer1) {
            return $job->signingProcessId === $process->id && $job->signerId === $signer1->id;
        });
        Queue::assertPushed(SendSigningRequestJob::class, function ($job) use ($process, $signer2) {
            return $job->signingProcessId === $process->id && $job->signerId === $signer2->id;
        });
    }

    /** @test */
    public function it_sends_notification_only_to_first_signer_in_sequential_process(): void
    {
        $process = SigningProcess::factory()->create([
            'tenant_id' => $this->tenant->id,
            'document_id' => $this->document->id,
            'created_by' => $this->user->id,
            'status' => SigningProcess::STATUS_DRAFT,
            'signature_order' => SigningProcess::ORDER_SEQUENTIAL,
        ]);

        $signer1 = Signer::factory()->create([
            'signing_process_id' => $process->id,
            'order' => 0,
            'status' => Signer::STATUS_PENDING,
        ]);

        $signer2 = Signer::factory()->create([
            'signing_process_id' => $process->id,
            'order' => 1,
            'status' => Signer::STATUS_PENDING,
        ]);

        $result = $this->service->sendNotifications($process);

        $this->assertTrue($result->success);
        $this->assertEquals(2, $result->totalSigners);
        $this->assertEquals(1, $result->notifiedCount);
        $this->assertFalse($result->allNotified());
        $this->assertEquals(1, $result->pendingCount());

        Queue::assertPushed(SendSigningRequestJob::class, 1);
        Queue::assertPushed(SendSigningRequestJob::class, function ($job) use ($process, $signer1) {
            return $job->signingProcessId === $process->id && $job->signerId === $signer1->id;
        });
    }

    /** @test */
    public function it_updates_process_status_to_sent(): void
    {
        $process = SigningProcess::factory()->create([
            'tenant_id' => $this->tenant->id,
            'document_id' => $this->document->id,
            'created_by' => $this->user->id,
            'status' => SigningProcess::STATUS_DRAFT,
            'signature_order' => SigningProcess::ORDER_PARALLEL,
        ]);

        Signer::factory()->create([
            'signing_process_id' => $process->id,
            'order' => 0,
        ]);

        $this->assertEquals(SigningProcess::STATUS_DRAFT, $process->status);

        $result = $this->service->sendNotifications($process);

        $this->assertEquals(SigningProcess::STATUS_SENT, $result->signingProcess->status);
    }

    /** @test */
    public function it_throws_exception_when_process_is_not_draft(): void
    {
        $process = SigningProcess::factory()->create([
            'tenant_id' => $this->tenant->id,
            'document_id' => $this->document->id,
            'created_by' => $this->user->id,
            'status' => SigningProcess::STATUS_SENT,
        ]);

        $this->expectException(SigningNotificationException::class);
        $this->expectExceptionMessage("Cannot send notifications for signing process in 'sent' state. Must be 'draft'.");

        $this->service->sendNotifications($process);
    }

    /** @test */
    public function it_throws_exception_when_no_signers_exist(): void
    {
        $process = SigningProcess::factory()->create([
            'tenant_id' => $this->tenant->id,
            'document_id' => $this->document->id,
            'created_by' => $this->user->id,
            'status' => SigningProcess::STATUS_DRAFT,
        ]);

        $this->expectException(SigningNotificationException::class);
        $this->expectExceptionMessage('Cannot send notifications. No signers found for signing process.');

        $this->service->sendNotifications($process);
    }

    /** @test */
    public function it_creates_audit_trail_entry_for_signing_process_sent(): void
    {
        $process = SigningProcess::factory()->create([
            'tenant_id' => $this->tenant->id,
            'document_id' => $this->document->id,
            'created_by' => $this->user->id,
            'status' => SigningProcess::STATUS_DRAFT,
            'signature_order' => SigningProcess::ORDER_PARALLEL,
        ]);

        Signer::factory()->count(2)->create([
            'signing_process_id' => $process->id,
        ]);

        $this->service->sendNotifications($process);

        $this->assertDatabaseHas('audit_trail_entries', [
            'auditable_type' => SigningProcess::class,
            'auditable_id' => $process->id,
            'event_type' => 'signing_process.sent',
            'tenant_id' => $this->tenant->id,
        ]);

        $entry = AuditTrailEntry::where('event_type', 'signing_process.sent')->first();
        $this->assertEquals(2, $entry->payload['total_signers']);
        $this->assertEquals(2, $entry->payload['notified_signers']);
    }

    /** @test */
    public function it_can_resend_notification_to_specific_signer(): void
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

        $result = $this->service->resendNotification($signer);

        $this->assertTrue($result);

        Queue::assertPushed(SendSigningRequestJob::class, function ($job) use ($process, $signer) {
            return $job->signingProcessId === $process->id && $job->signerId === $signer->id;
        });

        $this->assertDatabaseHas('audit_trail_entries', [
            'event_type' => 'signer.notification_resent',
            'auditable_type' => SigningProcess::class,
            'auditable_id' => $process->id,
        ]);
    }

    /** @test */
    public function it_cannot_resend_notification_to_signer_who_already_signed(): void
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

        $this->expectException(SigningNotificationException::class);
        $this->expectExceptionMessage('Cannot resend notification. Signer has already signed.');

        $this->service->resendNotification($signer);
    }

    /** @test */
    public function it_cannot_resend_notification_for_completed_process(): void
    {
        $process = SigningProcess::factory()->create([
            'tenant_id' => $this->tenant->id,
            'document_id' => $this->document->id,
            'created_by' => $this->user->id,
            'status' => SigningProcess::STATUS_COMPLETED,
        ]);

        $signer = Signer::factory()->create([
            'signing_process_id' => $process->id,
            'status' => Signer::STATUS_SENT,
        ]);

        $this->expectException(SigningNotificationException::class);
        $this->expectExceptionMessage('Cannot resend notification. Signing process is completed.');

        $this->service->resendNotification($signer);
    }

    /** @test */
    public function it_can_notify_next_signer_in_sequential_process(): void
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
            'order' => 0,
            'status' => Signer::STATUS_SIGNED,
        ]);

        $signer2 = Signer::factory()->create([
            'signing_process_id' => $process->id,
            'order' => 1,
            'status' => Signer::STATUS_PENDING,
        ]);

        $result = $this->service->notifyNextSigner($process);

        $this->assertTrue($result);

        Queue::assertPushed(SendSigningRequestJob::class, function ($job) use ($process, $signer2) {
            return $job->signingProcessId === $process->id && $job->signerId === $signer2->id;
        });
    }

    /** @test */
    public function it_returns_false_when_notifying_next_signer_in_parallel_process(): void
    {
        $process = SigningProcess::factory()->create([
            'tenant_id' => $this->tenant->id,
            'document_id' => $this->document->id,
            'created_by' => $this->user->id,
            'status' => SigningProcess::STATUS_IN_PROGRESS,
            'signature_order' => SigningProcess::ORDER_PARALLEL,
        ]);

        $result = $this->service->notifyNextSigner($process);

        $this->assertFalse($result);
    }

    /** @test */
    public function it_returns_false_when_no_next_signer_exists(): void
    {
        $process = SigningProcess::factory()->create([
            'tenant_id' => $this->tenant->id,
            'document_id' => $this->document->id,
            'created_by' => $this->user->id,
            'status' => SigningProcess::STATUS_IN_PROGRESS,
            'signature_order' => SigningProcess::ORDER_SEQUENTIAL,
        ]);

        Signer::factory()->create([
            'signing_process_id' => $process->id,
            'order' => 0,
            'status' => Signer::STATUS_SIGNED,
        ]);

        $result = $this->service->notifyNextSigner($process);

        $this->assertFalse($result);
    }

    /** @test */
    public function it_respects_tenant_isolation(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherUser = User::factory()->create(['tenant_id' => $otherTenant->id]);
        $otherDocument = Document::factory()->create([
            'tenant_id' => $otherTenant->id,
            'user_id' => $otherUser->id,
        ]);

        $process = SigningProcess::factory()->create([
            'tenant_id' => $this->tenant->id,
            'document_id' => $this->document->id,
            'created_by' => $this->user->id,
            'status' => SigningProcess::STATUS_DRAFT,
        ]);

        Signer::factory()->create([
            'signing_process_id' => $process->id,
        ]);

        $this->service->sendNotifications($process);

        // Audit entries should only exist for the correct tenant
        $this->assertDatabaseHas('audit_trail_entries', [
            'tenant_id' => $this->tenant->id,
            'event_type' => 'signing_process.sent',
        ]);

        $this->assertDatabaseMissing('audit_trail_entries', [
            'tenant_id' => $otherTenant->id,
            'event_type' => 'signing_process.sent',
        ]);
    }

    /** @test */
    public function it_includes_deadline_in_audit_trail_if_present(): void
    {
        $deadline = now()->addWeek();

        $process = SigningProcess::factory()->create([
            'tenant_id' => $this->tenant->id,
            'document_id' => $this->document->id,
            'created_by' => $this->user->id,
            'status' => SigningProcess::STATUS_DRAFT,
            'deadline_at' => $deadline,
        ]);

        Signer::factory()->create([
            'signing_process_id' => $process->id,
        ]);

        $this->service->sendNotifications($process);

        $entry = AuditTrailEntry::where('event_type', 'signing_process.sent')->first();
        $this->assertNotNull($entry->payload['deadline_at']);
        $this->assertEquals($deadline->toIso8601String(), $entry->payload['deadline_at']);
    }
}
