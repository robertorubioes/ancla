<?php

declare(strict_types=1);

namespace Tests\Feature\Notification;

use App\Jobs\SendSigningRequestJob;
use App\Mail\SigningRequestMail;
use App\Models\AuditTrailEntry;
use App\Models\Document;
use App\Models\Signer;
use App\Models\SigningProcess;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SigningNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected User $user;

    protected Document $document;

    protected function setUp(): void
    {
        parent::setUp();

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
    public function it_sends_email_notifications_in_parallel_mode(): void
    {
        Queue::fake();

        $process = SigningProcess::factory()->create([
            'tenant_id' => $this->tenant->id,
            'document_id' => $this->document->id,
            'created_by' => $this->user->id,
            'status' => SigningProcess::STATUS_DRAFT,
            'signature_order' => SigningProcess::ORDER_PARALLEL,
            'custom_message' => 'Please review and sign this document.',
        ]);

        $signer1 = Signer::factory()->create([
            'signing_process_id' => $process->id,
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'order' => 0,
        ]);

        $signer2 = Signer::factory()->create([
            'signing_process_id' => $process->id,
            'name' => 'Jane Smith',
            'email' => 'jane@example.com',
            'order' => 1,
        ]);

        // Call sendNotifications which will queue jobs
        $process->sendNotifications();

        Queue::assertPushed(SendSigningRequestJob::class, 2);

        Queue::assertPushed(SendSigningRequestJob::class, function ($job) use ($signer1) {
            return $job->signerId === $signer1->id;
        });

        Queue::assertPushed(SendSigningRequestJob::class, function ($job) use ($signer2) {
            return $job->signerId === $signer2->id;
        });
    }

    /** @test */
    public function it_sends_email_with_correct_subject(): void
    {
        Mail::fake();

        $process = SigningProcess::factory()->create([
            'tenant_id' => $this->tenant->id,
            'document_id' => $this->document->id,
            'created_by' => $this->user->id,
            'status' => SigningProcess::STATUS_DRAFT,
        ]);

        $signer = Signer::factory()->create([
            'signing_process_id' => $process->id,
            'email' => 'john@example.com',
        ]);

        $mailable = new SigningRequestMail($process, $signer);

        // Subject should contain document reference
        $subject = $mailable->envelope()->subject;
        $this->assertStringContainsString('Firma requerida:', $subject);
    }

    /** @test */
    public function it_includes_signing_url_with_unique_token_in_email(): void
    {
        Mail::fake();

        $process = SigningProcess::factory()->create([
            'tenant_id' => $this->tenant->id,
            'document_id' => $this->document->id,
            'created_by' => $this->user->id,
            'status' => SigningProcess::STATUS_DRAFT,
        ]);

        $signer = Signer::factory()->create([
            'signing_process_id' => $process->id,
            'token' => 'unique-token-123',
        ]);

        $mailable = new SigningRequestMail($process, $signer);
        $rendered = $mailable->render();

        $this->assertStringContainsString('/sign/unique-token-123', $rendered);
    }

    /** @test */
    public function it_includes_custom_message_in_email(): void
    {
        Mail::fake();

        $process = SigningProcess::factory()->create([
            'tenant_id' => $this->tenant->id,
            'document_id' => $this->document->id,
            'created_by' => $this->user->id,
            'status' => SigningProcess::STATUS_DRAFT,
            'custom_message' => 'This is a custom message for the signer.',
        ]);

        $signer = Signer::factory()->create([
            'signing_process_id' => $process->id,
        ]);

        $mailable = new SigningRequestMail($process, $signer);
        $rendered = $mailable->render();

        $this->assertStringContainsString('This is a custom message for the signer.', $rendered);
    }

    /** @test */
    public function it_includes_deadline_in_email_when_present(): void
    {
        Mail::fake();

        $deadline = now()->addWeek();

        $process = SigningProcess::factory()->create([
            'tenant_id' => $this->tenant->id,
            'document_id' => $this->document->id,
            'created_by' => $this->user->id,
            'status' => SigningProcess::STATUS_DRAFT,
            'deadline_at' => $deadline,
        ]);

        $signer = Signer::factory()->create([
            'signing_process_id' => $process->id,
        ]);

        $mailable = new SigningRequestMail($process, $signer);
        $rendered = $mailable->render();

        $this->assertStringContainsString('Fecha límite', $rendered);
        $this->assertStringContainsString($deadline->format('d/m/Y'), $rendered);
    }

    /** @test */
    public function it_includes_promoter_name_in_email(): void
    {
        Mail::fake();

        $process = SigningProcess::factory()->create([
            'tenant_id' => $this->tenant->id,
            'document_id' => $this->document->id,
            'created_by' => $this->user->id,
            'status' => SigningProcess::STATUS_DRAFT,
        ]);

        $signer = Signer::factory()->create([
            'signing_process_id' => $process->id,
        ]);

        $mailable = new SigningRequestMail($process, $signer);
        $rendered = $mailable->render();

        $this->assertStringContainsString($this->user->name, $rendered);
    }

    /** @test */
    public function it_updates_signer_status_after_successful_send(): void
    {
        Queue::fake();
        Mail::fake();

        $process = SigningProcess::factory()->create([
            'tenant_id' => $this->tenant->id,
            'document_id' => $this->document->id,
            'created_by' => $this->user->id,
            'status' => SigningProcess::STATUS_DRAFT,
        ]);

        $signer = Signer::factory()->create([
            'signing_process_id' => $process->id,
            'status' => Signer::STATUS_PENDING,
            'sent_at' => null,
        ]);

        $this->assertEquals(Signer::STATUS_PENDING, $signer->status);
        $this->assertNull($signer->sent_at);

        // Manually execute the job
        $job = new SendSigningRequestJob($process->id, $signer->id);
        $job->handle(app(\App\Services\Evidence\AuditTrailService::class));

        $signer->refresh();

        $this->assertEquals(Signer::STATUS_SENT, $signer->status);
        $this->assertNotNull($signer->sent_at);
    }

    /** @test */
    public function it_creates_audit_trail_when_notification_sent(): void
    {
        Queue::fake();
        Mail::fake();

        $process = SigningProcess::factory()->create([
            'tenant_id' => $this->tenant->id,
            'document_id' => $this->document->id,
            'created_by' => $this->user->id,
            'status' => SigningProcess::STATUS_DRAFT,
        ]);

        $signer = Signer::factory()->create([
            'signing_process_id' => $process->id,
        ]);

        // Execute the job
        $job = new SendSigningRequestJob($process->id, $signer->id);
        $job->handle(app(\App\Services\Evidence\AuditTrailService::class));

        $this->assertDatabaseHas('audit_trail_entries', [
            'auditable_type' => SigningProcess::class,
            'auditable_id' => $process->id,
            'event_type' => 'signer.notified',
            'tenant_id' => $this->tenant->id,
        ]);

        $entry = AuditTrailEntry::where('event_type', 'signer.notified')->first();
        $this->assertEquals($signer->id, $entry->payload['signer_id']);
        $this->assertEquals($signer->email, $entry->payload['signer_email']);
        $this->assertEquals('email', $entry->payload['notification_method']);
    }

    /** @test */
    public function it_handles_invalid_email_address_gracefully(): void
    {
        Queue::fake();
        Mail::fake();

        $process = SigningProcess::factory()->create([
            'tenant_id' => $this->tenant->id,
            'document_id' => $this->document->id,
            'created_by' => $this->user->id,
            'status' => SigningProcess::STATUS_DRAFT,
        ]);

        $signer = Signer::factory()->create([
            'signing_process_id' => $process->id,
            'email' => 'invalid-email-address',
        ]);

        // Execute the job - should not throw exception
        $job = new SendSigningRequestJob($process->id, $signer->id);
        $job->handle(app(\App\Services\Evidence\AuditTrailService::class));

        Mail::assertNothingSent();

        // Should log failure
        $this->assertDatabaseHas('audit_trail_entries', [
            'event_type' => 'signer.notification_failed',
        ]);
    }

    /** @test */
    public function it_queues_notifications_with_proper_retry_settings(): void
    {
        Queue::fake();

        $process = SigningProcess::factory()->create([
            'tenant_id' => $this->tenant->id,
            'document_id' => $this->document->id,
            'created_by' => $this->user->id,
            'status' => SigningProcess::STATUS_DRAFT,
        ]);

        $signer = Signer::factory()->create([
            'signing_process_id' => $process->id,
        ]);

        $job = new SendSigningRequestJob($process->id, $signer->id);

        $this->assertEquals(3, $job->tries);
        $this->assertEquals(60, $job->backoff);
    }

    /** @test */
    public function email_template_is_responsive(): void
    {
        Mail::fake();

        $process = SigningProcess::factory()->create([
            'tenant_id' => $this->tenant->id,
            'document_id' => $this->document->id,
            'created_by' => $this->user->id,
            'status' => SigningProcess::STATUS_DRAFT,
        ]);

        $signer = Signer::factory()->create([
            'signing_process_id' => $process->id,
        ]);

        $mailable = new SigningRequestMail($process, $signer);
        $rendered = $mailable->render();

        // Check for viewport meta tag
        $this->assertStringContainsString('viewport', $rendered);
        $this->assertStringContainsString('max-width: 600px', $rendered);

        // Check for media queries
        $this->assertStringContainsString('@media', $rendered);
    }

    /** @test */
    public function email_includes_security_warnings(): void
    {
        Mail::fake();

        $process = SigningProcess::factory()->create([
            'tenant_id' => $this->tenant->id,
            'document_id' => $this->document->id,
            'created_by' => $this->user->id,
            'status' => SigningProcess::STATUS_DRAFT,
        ]);

        $signer = Signer::factory()->create([
            'signing_process_id' => $process->id,
        ]);

        $mailable = new SigningRequestMail($process, $signer);
        $rendered = $mailable->render();

        $this->assertStringContainsString('No respondas a este correo', $rendered);
        $this->assertStringContainsString('único y personal', $rendered);
    }

    /** @test */
    public function it_sends_only_to_first_signer_in_sequential_mode(): void
    {
        Queue::fake();

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
        ]);

        $signer2 = Signer::factory()->create([
            'signing_process_id' => $process->id,
            'order' => 1,
        ]);

        $result = $process->sendNotifications();

        $this->assertTrue($result->success);
        $this->assertEquals(1, $result->notifiedCount);

        Queue::assertPushed(SendSigningRequestJob::class, 1);
        Queue::assertPushed(SendSigningRequestJob::class, function ($job) use ($signer1) {
            return $job->signerId === $signer1->id;
        });
    }

    /** @test */
    public function it_includes_ancla_branding_in_email(): void
    {
        Mail::fake();

        $process = SigningProcess::factory()->create([
            'tenant_id' => $this->tenant->id,
            'document_id' => $this->document->id,
            'created_by' => $this->user->id,
            'status' => SigningProcess::STATUS_DRAFT,
        ]);

        $signer = Signer::factory()->create([
            'signing_process_id' => $process->id,
        ]);

        $mailable = new SigningRequestMail($process, $signer);
        $rendered = $mailable->render();

        $this->assertStringContainsString('Firmalum', $rendered);
        $this->assertStringContainsString('Firma Digital', $rendered);
    }

    /** @test */
    public function email_has_proper_html_structure(): void
    {
        Mail::fake();

        $process = SigningProcess::factory()->create([
            'tenant_id' => $this->tenant->id,
            'document_id' => $this->document->id,
            'created_by' => $this->user->id,
            'status' => SigningProcess::STATUS_DRAFT,
        ]);

        $signer = Signer::factory()->create([
            'signing_process_id' => $process->id,
        ]);

        $mailable = new SigningRequestMail($process, $signer);
        $rendered = $mailable->render();

        $this->assertStringContainsString('<!DOCTYPE html>', $rendered);
        $this->assertStringContainsString('<html', $rendered);
        $this->assertStringContainsString('</html>', $rendered);
        $this->assertStringContainsString('<head>', $rendered);
        $this->assertStringContainsString('<body>', $rendered);
    }
}
