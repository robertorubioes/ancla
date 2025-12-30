<?php

declare(strict_types=1);

namespace Tests\Unit\Document;

use App\Models\Document;
use App\Models\Signer;
use App\Models\SigningProcess;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Document\CertificationPageBuilder;
use App\Services\Document\FinalDocumentException;
use App\Services\Document\FinalDocumentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FinalDocumentServiceTest extends TestCase
{
    use RefreshDatabase;

    private FinalDocumentService $service;

    private Tenant $tenant;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new FinalDocumentService(
            new CertificationPageBuilder
        );

        // Create test tenant and user
        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);

        // Use fake storage
        Storage::fake('local');
    }

    /** @test */
    public function it_throws_exception_if_process_not_completed(): void
    {
        $process = SigningProcess::factory()
            ->for($this->tenant)
            ->create(['status' => SigningProcess::STATUS_IN_PROGRESS]);

        $this->expectException(FinalDocumentException::class);
        $this->expectExceptionMessage('not completed');

        $this->service->generateFinalDocument($process);
    }

    /** @test */
    public function it_throws_exception_if_final_document_already_exists(): void
    {
        $process = SigningProcess::factory()
            ->for($this->tenant)
            ->create([
                'status' => SigningProcess::STATUS_COMPLETED,
                'final_document_path' => 'final/test.pdf',
            ]);

        $this->expectException(FinalDocumentException::class);
        $this->expectExceptionMessage('already generated');

        $this->service->generateFinalDocument($process);
    }

    /** @test */
    public function it_throws_exception_if_not_all_signers_signed(): void
    {
        $document = Document::factory()->for($this->tenant)->create();

        $process = SigningProcess::factory()
            ->for($this->tenant)
            ->for($document)
            ->create([
                'status' => SigningProcess::STATUS_COMPLETED,
                'completed_at' => now(),
            ]);

        // Create signers but not all signed
        Signer::factory()->for($process)->for($this->tenant)->create([
            'status' => 'signed',
            'order' => 1,
        ]);

        Signer::factory()->for($process)->for($this->tenant)->create([
            'status' => 'pending',
            'order' => 2,
        ]);

        $this->expectException(FinalDocumentException::class);
        $this->expectExceptionMessage('Not all signers');

        $this->service->generateFinalDocument($process);
    }

    /** @test */
    public function it_throws_exception_if_no_signers_exist(): void
    {
        $document = Document::factory()->for($this->tenant)->create();

        $process = SigningProcess::factory()
            ->for($this->tenant)
            ->for($document)
            ->create([
                'status' => SigningProcess::STATUS_COMPLETED,
                'completed_at' => now(),
            ]);

        $this->expectException(FinalDocumentException::class);
        $this->expectExceptionMessage('No signers found');

        $this->service->generateFinalDocument($process);
    }

    /** @test */
    public function it_throws_exception_if_no_signed_documents_found(): void
    {
        $document = Document::factory()->for($this->tenant)->create();

        $process = SigningProcess::factory()
            ->for($this->tenant)
            ->for($document)
            ->create([
                'status' => SigningProcess::STATUS_COMPLETED,
                'completed_at' => now(),
            ]);

        // Create signers but no signed documents
        Signer::factory()->for($process)->for($this->tenant)->create([
            'status' => 'signed',
            'order' => 1,
            'signed_at' => now(),
        ]);

        $this->expectException(FinalDocumentException::class);
        $this->expectExceptionMessage('No signed documents found');

        $this->service->generateFinalDocument($process);
    }

    /** @test */
    public function it_verifies_final_document_exists(): void
    {
        $process = SigningProcess::factory()
            ->for($this->tenant)
            ->create(['final_document_path' => null]);

        $result = $this->service->verifyFinalDocument($process);

        $this->assertFalse($result);
    }

    /** @test */
    public function it_returns_false_when_final_document_file_missing(): void
    {
        $process = SigningProcess::factory()
            ->for($this->tenant)
            ->create([
                'final_document_path' => 'final/missing.pdf',
                'final_document_hash' => 'some-hash',
            ]);

        $result = $this->service->verifyFinalDocument($process);

        $this->assertFalse($result);
    }

    /** @test */
    public function it_checks_hash_integrity_of_final_document(): void
    {
        $content = 'test pdf content';
        $path = 'final/test.pdf';

        Storage::put($path, $content);

        $process = SigningProcess::factory()
            ->for($this->tenant)
            ->create([
                'final_document_path' => $path,
                'final_document_hash' => hash('sha256', $content),
            ]);

        $result = $this->service->verifyFinalDocument($process);

        $this->assertTrue($result);
    }

    /** @test */
    public function it_returns_false_when_hash_mismatch(): void
    {
        $content = 'test pdf content';
        $path = 'final/test.pdf';

        Storage::put($path, $content);

        $process = SigningProcess::factory()
            ->for($this->tenant)
            ->create([
                'final_document_path' => $path,
                'final_document_hash' => 'wrong-hash',
            ]);

        $result = $this->service->verifyFinalDocument($process);

        $this->assertFalse($result);
    }

    /** @test */
    public function it_gets_final_document_content(): void
    {
        $content = 'test pdf content';
        $path = 'final/test.pdf';

        Storage::put($path, $content);

        $process = SigningProcess::factory()
            ->for($this->tenant)
            ->create([
                'final_document_path' => $path,
                'final_document_hash' => hash('sha256', $content),
            ]);

        $retrieved = $this->service->getFinalDocumentContent($process);

        $this->assertEquals($content, $retrieved);
    }

    /** @test */
    public function it_returns_null_when_no_final_document_path(): void
    {
        $process = SigningProcess::factory()
            ->for($this->tenant)
            ->create(['final_document_path' => null]);

        $content = $this->service->getFinalDocumentContent($process);

        $this->assertNull($content);
    }

    /** @test */
    public function it_throws_exception_on_integrity_failure_when_getting_content(): void
    {
        $path = 'final/test.pdf';

        Storage::put($path, 'content');

        $process = SigningProcess::factory()
            ->for($this->tenant)
            ->create([
                'final_document_path' => $path,
                'final_document_hash' => 'wrong-hash',
            ]);

        $this->expectException(FinalDocumentException::class);
        $this->expectExceptionMessage('Integrity check failed');

        $this->service->getFinalDocumentContent($process);
    }

    /** @test */
    public function it_validates_process_completion_status(): void
    {
        $process = SigningProcess::factory()
            ->for($this->tenant)
            ->create(['status' => SigningProcess::STATUS_DRAFT]);

        try {
            $this->service->generateFinalDocument($process);
            $this->fail('Expected FinalDocumentException');
        } catch (FinalDocumentException $e) {
            $this->assertStringContainsString('not completed', $e->getMessage());
        }
    }

    /** @test */
    public function it_validates_all_signers_completed(): void
    {
        $document = Document::factory()->for($this->tenant)->create();

        $process = SigningProcess::factory()
            ->for($this->tenant)
            ->for($document)
            ->create([
                'status' => SigningProcess::STATUS_COMPLETED,
                'completed_at' => now(),
            ]);

        Signer::factory()->for($process)->for($this->tenant)->create([
            'status' => 'viewed',
            'order' => 1,
        ]);

        try {
            $this->service->generateFinalDocument($process);
            $this->fail('Expected FinalDocumentException');
        } catch (FinalDocumentException $e) {
            $this->assertStringContainsString('Not all signers', $e->getMessage());
        }
    }

    /** @test */
    public function it_checks_for_existing_final_document_before_regeneration(): void
    {
        $path = 'final/existing.pdf';
        Storage::put($path, 'existing content');

        $document = Document::factory()->for($this->tenant)->create();

        $process = SigningProcess::factory()
            ->for($this->tenant)
            ->for($document)
            ->create([
                'status' => SigningProcess::STATUS_COMPLETED,
                'completed_at' => now(),
                'final_document_path' => $path,
            ]);

        Signer::factory()->for($process)->for($this->tenant)->create([
            'status' => 'signed',
            'order' => 1,
            'signed_at' => now(),
        ]);

        // Should not throw as regenerate deletes first
        $this->expectException(FinalDocumentException::class);
        $this->expectExceptionMessage('No signed documents found');

        // This will fail because we don't have signed documents, but proves validation works
        $this->service->regenerateFinalDocument($process);
    }

    /** @test */
    public function regenerate_deletes_old_final_document(): void
    {
        $oldPath = 'final/old.pdf';
        Storage::put($oldPath, 'old content');

        $document = Document::factory()->for($this->tenant)->create();

        $process = SigningProcess::factory()
            ->for($this->tenant)
            ->for($document)
            ->create([
                'status' => SigningProcess::STATUS_COMPLETED,
                'final_document_path' => $oldPath,
            ]);

        Signer::factory()->for($process)->for($this->tenant)->create([
            'status' => 'signed',
            'signed_at' => now(),
        ]);

        try {
            $this->service->regenerateFinalDocument($process);
        } catch (FinalDocumentException $e) {
            // Expected due to no signed documents
        }

        // Old file should be deleted
        Storage::assertMissing($oldPath);

        // Process should have null path
        $this->assertNull($process->fresh()->final_document_path);
    }
}
