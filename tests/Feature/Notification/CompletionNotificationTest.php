<?php

declare(strict_types=1);

namespace Tests\Feature\Notification;

use App\Jobs\SendSignedDocumentCopyJob;
use App\Models\Document;
use App\Models\Signer;
use App\Models\SigningProcess;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Notification\CompletionNotificationException;
use App\Services\Notification\CompletionNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CompletionNotificationTest extends TestCase
{
    use RefreshDatabase;

    private CompletionNotificationService $service;

    private Tenant $tenant;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(CompletionNotificationService::class);

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
    }

    /** @test */
    public function it_sends_copies_to_all_signers()
    {
        Queue::fake();

        $document = Document::factory()->create(['tenant_id' => $this->tenant->id]);

        $process = SigningProcess::factory()->create([
            'tenant_id' => $this->tenant->id,
            'document_id' => $document->id,
            'status' => SigningProcess::STATUS_COMPLETED,
            'final_document_path' => 'final/test.pdf',
        ]);

        $signer1 = Signer::factory()->create([
            'signing_process_id' => $process->id,
            'status' => Signer::STATUS_SIGNED,
            'email' => 'signer1@test.com',
        ]);

        $signer2 = Signer::factory()->create([
            'signing_process_id' => $process->id,
            'status' => Signer::STATUS_SIGNED,
            'email' => 'signer2@test.com',
        ]);

        $result = $this->service->sendCopies($process);

        $this->assertTrue($result->success);
        $this->assertEquals(2, $result->totalSigners);
        $this->assertEquals(2, $result->notifiedCount);

        Queue::assertPushed(SendSignedDocumentCopyJob::class, 2);
    }

    /** @test */
    public function it_throws_exception_when_no_final_document()
    {
        $document = Document::factory()->create(['tenant_id' => $this->tenant->id]);

        $process = SigningProcess::factory()->create([
            'tenant_id' => $this->tenant->id,
            'document_id' => $document->id,
            'status' => SigningProcess::STATUS_COMPLETED,
            'final_document_path' => null,
        ]);

        $this->expectException(CompletionNotificationException::class);
        $this->expectExceptionMessage('Final document not found');

        $this->service->sendCopies($process);
    }

    /** @test */
    public function it_throws_exception_when_process_not_completed()
    {
        $document = Document::factory()->create(['tenant_id' => $this->tenant->id]);

        $process = SigningProcess::factory()->create([
            'tenant_id' => $this->tenant->id,
            'document_id' => $document->id,
            'status' => SigningProcess::STATUS_IN_PROGRESS,
            'final_document_path' => 'final/test.pdf',
        ]);

        $this->expectException(CompletionNotificationException::class);
        $this->expectExceptionMessage('not completed');

        $this->service->sendCopies($process);
    }

    /** @test */
    public function it_throws_exception_when_no_signers()
    {
        $document = Document::factory()->create(['tenant_id' => $this->tenant->id]);

        $process = SigningProcess::factory()->create([
            'tenant_id' => $this->tenant->id,
            'document_id' => $document->id,
            'status' => SigningProcess::STATUS_COMPLETED,
            'final_document_path' => 'final/test.pdf',
        ]);

        $this->expectException(CompletionNotificationException::class);
        $this->expectExceptionMessage('No signers found');

        $this->service->sendCopies($process);
    }

    /** @test */
    public function it_updates_signer_copy_sent_at_timestamp()
    {
        Queue::fake();

        $document = Document::factory()->create(['tenant_id' => $this->tenant->id]);

        $process = SigningProcess::factory()->create([
            'tenant_id' => $this->tenant->id,
            'document_id' => $document->id,
            'status' => SigningProcess::STATUS_COMPLETED,
            'final_document_path' => 'final/test.pdf',
        ]);

        $signer = Signer::factory()->create([
            'signing_process_id' => $process->id,
            'status' => Signer::STATUS_SIGNED,
            'copy_sent_at' => null,
        ]);

        $this->service->sendCopies($process);

        $signer->refresh();
        $this->assertNotNull($signer->copy_sent_at);
    }

    /** @test */
    public function it_generates_download_token_for_signer()
    {
        Queue::fake();

        $document = Document::factory()->create(['tenant_id' => $this->tenant->id]);

        $process = SigningProcess::factory()->create([
            'tenant_id' => $this->tenant->id,
            'document_id' => $document->id,
            'status' => SigningProcess::STATUS_COMPLETED,
            'final_document_path' => 'final/test.pdf',
        ]);

        $signer = Signer::factory()->create([
            'signing_process_id' => $process->id,
            'status' => Signer::STATUS_SIGNED,
            'download_token' => null,
        ]);

        $this->service->sendCopies($process);

        $signer->refresh();
        $this->assertNotNull($signer->download_token);
        $this->assertEquals(64, strlen($signer->download_token));
    }

    /** @test */
    public function it_sets_download_expiration_to_30_days()
    {
        Queue::fake();

        $document = Document::factory()->create(['tenant_id' => $this->tenant->id]);

        $process = SigningProcess::factory()->create([
            'tenant_id' => $this->tenant->id,
            'document_id' => $document->id,
            'status' => SigningProcess::STATUS_COMPLETED,
            'final_document_path' => 'final/test.pdf',
        ]);

        $signer = Signer::factory()->create([
            'signing_process_id' => $process->id,
            'status' => Signer::STATUS_SIGNED,
        ]);

        $this->service->sendCopies($process);

        $signer->refresh();
        $this->assertNotNull($signer->download_expires_at);
        $this->assertTrue($signer->download_expires_at->isAfter(now()->addDays(29)));
        $this->assertTrue($signer->download_expires_at->isBefore(now()->addDays(31)));
    }

    /** @test */
    public function it_validates_email_format()
    {
        Queue::fake();

        $document = Document::factory()->create(['tenant_id' => $this->tenant->id]);

        $process = SigningProcess::factory()->create([
            'tenant_id' => $this->tenant->id,
            'document_id' => $document->id,
            'status' => SigningProcess::STATUS_COMPLETED,
            'final_document_path' => 'final/test.pdf',
        ]);

        $signer = Signer::factory()->create([
            'signing_process_id' => $process->id,
            'status' => Signer::STATUS_SIGNED,
            'email' => 'invalid-email',
        ]);

        $result = $this->service->sendCopies($process);

        $this->assertFalse($result->success);
        $this->assertEquals(0, $result->notifiedCount);
        $this->assertEquals(1, $result->errorCount());
    }

    /** @test */
    public function it_can_resend_copy_to_specific_signer()
    {
        Queue::fake();

        $document = Document::factory()->create(['tenant_id' => $this->tenant->id]);

        $process = SigningProcess::factory()->create([
            'tenant_id' => $this->tenant->id,
            'document_id' => $document->id,
            'status' => SigningProcess::STATUS_COMPLETED,
            'final_document_path' => 'final/test.pdf',
        ]);

        $signer = Signer::factory()->create([
            'signing_process_id' => $process->id,
            'status' => Signer::STATUS_SIGNED,
        ]);

        $this->service->resendCopy($process, $signer);

        Queue::assertPushed(SendSignedDocumentCopyJob::class, 1);
    }

    /** @test */
    public function it_handles_partial_failures_gracefully()
    {
        Queue::fake();

        $document = Document::factory()->create(['tenant_id' => $this->tenant->id]);

        $process = SigningProcess::factory()->create([
            'tenant_id' => $this->tenant->id,
            'document_id' => $document->id,
            'status' => SigningProcess::STATUS_COMPLETED,
            'final_document_path' => 'final/test.pdf',
        ]);

        $signer1 = Signer::factory()->create([
            'signing_process_id' => $process->id,
            'status' => Signer::STATUS_SIGNED,
            'email' => 'valid@test.com',
        ]);

        $signer2 = Signer::factory()->create([
            'signing_process_id' => $process->id,
            'status' => Signer::STATUS_SIGNED,
            'email' => 'invalid-email',
        ]);

        $result = $this->service->sendCopies($process);

        $this->assertTrue($result->success); // At least one succeeded
        $this->assertEquals(2, $result->totalSigners);
        $this->assertEquals(1, $result->notifiedCount);
        $this->assertTrue($result->hasErrors());
    }
}
