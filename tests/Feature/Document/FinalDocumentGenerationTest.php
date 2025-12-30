<?php

declare(strict_types=1);

namespace Tests\Feature\Document;

use App\Models\Document;
use App\Models\EvidencePackage;
use App\Models\SignedDocument;
use App\Models\Signer;
use App\Models\SigningProcess;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Document\FinalDocumentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FinalDocumentGenerationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $user;

    private FinalDocumentService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->service = app(FinalDocumentService::class);

        Storage::fake('local');
    }

    /** @test */
    public function it_automatically_generates_final_document_when_process_completed(): void
    {
        $document = Document::factory()->for($this->tenant)->create([
            'stored_path' => 'documents/test.pdf',
        ]);

        // Create mock PDF
        Storage::put('documents/test.pdf', $this->getMockPdfContent());

        $process = SigningProcess::factory()
            ->for($this->tenant)
            ->for($document)
            ->for($this->user, 'createdBy')
            ->create([
                'status' => SigningProcess::STATUS_IN_PROGRESS,
            ]);

        $signer = Signer::factory()->for($process)->for($this->tenant)->create([
            'status' => 'signed',
            'order' => 1,
            'signed_at' => now(),
            'otp_verified' => true,
            'signature_data' => 'base64signature',
        ]);

        $evidencePackage = EvidencePackage::factory()
            ->for($this->tenant)
            ->for($process)
            ->create(['sealed_at' => now()]);

        $signer->update(['evidence_package_id' => $evidencePackage->id]);

        // Create signed document
        $signedPath = 'signed/'.$this->tenant->id.'/2025/01/'.$process->id.'_'.$signer->id.'.pdf';
        Storage::put($signedPath, $this->getMockPdfContent());

        $signedDoc = SignedDocument::create([
            'uuid' => \Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'signing_process_id' => $process->id,
            'signer_id' => $signer->id,
            'original_document_id' => $document->id,
            'storage_disk' => 'local',
            'signed_path' => $signedPath,
            'signed_name' => 'test_signed.pdf',
            'file_size' => 1024,
            'content_hash' => hash('sha256', $this->getMockPdfContent()),
            'original_hash' => hash('sha256', $this->getMockPdfContent()),
            'hash_algorithm' => 'SHA-256',
            'pkcs7_signature' => bin2hex('signature'),
            'certificate_subject' => 'CN=Test',
            'certificate_issuer' => 'CN=TestCA',
            'certificate_serial' => '123',
            'certificate_fingerprint' => 'abc123',
            'pades_level' => 'B-LT',
            'has_tsa_token' => true,
            'signature_visible' => true,
            'status' => 'signed',
            'signed_at' => now(),
        ]);

        // Mark process as completed (should trigger observer)
        $process->markAsCompleted();

        // Refresh to get updated data
        $process->refresh();

        // Final document should be generated
        $this->assertNotNull($process->final_document_path);
        $this->assertNotNull($process->final_document_name);
        $this->assertNotNull($process->final_document_hash);
        $this->assertNotNull($process->final_document_generated_at);
        $this->assertTrue($process->hasFinalDocument());

        // File should exist
        Storage::assertExists($process->final_document_path);
    }

    /** @test */
    public function it_merges_multiple_signed_documents(): void
    {
        $document = Document::factory()->for($this->tenant)->create([
            'stored_path' => 'documents/test.pdf',
        ]);

        Storage::put('documents/test.pdf', $this->getMockPdfContent());

        $process = SigningProcess::factory()
            ->for($this->tenant)
            ->for($document)
            ->for($this->user, 'createdBy')
            ->create([
                'status' => SigningProcess::STATUS_COMPLETED,
                'completed_at' => now(),
            ]);

        // Create 3 signers with signed documents
        for ($i = 1; $i <= 3; $i++) {
            $signer = Signer::factory()->for($process)->for($this->tenant)->create([
                'status' => 'signed',
                'order' => $i,
                'signed_at' => now(),
                'otp_verified' => true,
                'signature_data' => 'signature'.$i,
            ]);

            $evidencePackage = EvidencePackage::factory()
                ->for($this->tenant)
                ->for($process)
                ->create(['sealed_at' => now()]);

            $signer->update(['evidence_package_id' => $evidencePackage->id]);

            $signedPath = 'signed/'.$this->tenant->id.'/2025/01/'.$process->id.'_'.$signer->id.'.pdf';
            Storage::put($signedPath, $this->getMockPdfContent());

            SignedDocument::create([
                'uuid' => \Str::uuid(),
                'tenant_id' => $this->tenant->id,
                'signing_process_id' => $process->id,
                'signer_id' => $signer->id,
                'original_document_id' => $document->id,
                'storage_disk' => 'local',
                'signed_path' => $signedPath,
                'signed_name' => 'test_signed_'.$i.'.pdf',
                'file_size' => 1024,
                'content_hash' => hash('sha256', $this->getMockPdfContent()),
                'original_hash' => hash('sha256', $this->getMockPdfContent()),
                'hash_algorithm' => 'SHA-256',
                'pkcs7_signature' => bin2hex('signature'),
                'certificate_subject' => 'CN=Test',
                'certificate_issuer' => 'CN=TestCA',
                'certificate_serial' => (string) $i,
                'certificate_fingerprint' => 'abc'.$i,
                'pades_level' => 'B-LT',
                'has_tsa_token' => true,
                'signature_visible' => true,
                'status' => 'signed',
                'signed_at' => now(),
            ]);
        }

        $result = $this->service->generateFinalDocument($process);

        $this->assertTrue($result->isSuccess());
        $this->assertGreaterThan(0, $result->fileSize);
        $this->assertGreaterThan(0, $result->pageCount);

        // Check database was updated
        $process->refresh();
        $this->assertEquals($result->storagePath, $process->final_document_path);
        $this->assertEquals($result->contentHash, $process->final_document_hash);
    }

    /** @test */
    public function it_includes_certification_page(): void
    {
        $document = Document::factory()->for($this->tenant)->create([
            'stored_path' => 'documents/test.pdf',
        ]);

        Storage::put('documents/test.pdf', $this->getMockPdfContent());

        $process = SigningProcess::factory()
            ->for($this->tenant)
            ->for($document)
            ->for($this->user, 'createdBy')
            ->create([
                'status' => SigningProcess::STATUS_COMPLETED,
                'completed_at' => now(),
                'custom_message' => 'Please sign this important document',
            ]);

        $signer = Signer::factory()->for($process)->for($this->tenant)->create([
            'status' => 'signed',
            'order' => 1,
            'signed_at' => now(),
            'otp_verified' => true,
            'signature_data' => 'signature',
        ]);

        $evidencePackage = EvidencePackage::factory()
            ->for($this->tenant)
            ->for($process)
            ->create(['sealed_at' => now()]);

        $signer->update(['evidence_package_id' => $evidencePackage->id]);

        $signedPath = 'signed/'.$this->tenant->id.'/2025/01/'.$process->id.'_'.$signer->id.'.pdf';
        Storage::put($signedPath, $this->getMockPdfContent());

        SignedDocument::create([
            'uuid' => \Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'signing_process_id' => $process->id,
            'signer_id' => $signer->id,
            'original_document_id' => $document->id,
            'storage_disk' => 'local',
            'signed_path' => $signedPath,
            'signed_name' => 'test_signed.pdf',
            'file_size' => 1024,
            'content_hash' => hash('sha256', $this->getMockPdfContent()),
            'original_hash' => hash('sha256', $this->getMockPdfContent()),
            'hash_algorithm' => 'SHA-256',
            'pkcs7_signature' => bin2hex('signature'),
            'certificate_subject' => 'CN=Test',
            'certificate_issuer' => 'CN=TestCA',
            'certificate_serial' => '123',
            'certificate_fingerprint' => 'abc123',
            'pades_level' => 'B-LT',
            'has_tsa_token' => true,
            'signature_visible' => true,
            'status' => 'signed',
            'signed_at' => now(),
        ]);

        $result = $this->service->generateFinalDocument($process);

        // Page count should be original pages + certification page
        $this->assertGreaterThan(1, $result->pageCount);

        // Final document should exist
        $this->assertTrue(Storage::exists($result->storagePath));
    }

    /** @test */
    public function it_respects_tenant_isolation(): void
    {
        $tenant2 = Tenant::factory()->create();

        $document = Document::factory()->for($this->tenant)->create([
            'stored_path' => 'documents/test.pdf',
        ]);

        Storage::put('documents/test.pdf', $this->getMockPdfContent());

        $process = SigningProcess::factory()
            ->for($this->tenant)
            ->for($document)
            ->for($this->user, 'createdBy')
            ->create([
                'status' => SigningProcess::STATUS_COMPLETED,
                'completed_at' => now(),
            ]);

        $signer = Signer::factory()->for($process)->for($this->tenant)->create([
            'status' => 'signed',
            'signed_at' => now(),
        ]);

        $evidencePackage = EvidencePackage::factory()
            ->for($this->tenant)
            ->for($process)
            ->create(['sealed_at' => now()]);

        $signer->update(['evidence_package_id' => $evidencePackage->id]);

        // Create signed document for tenant 1
        $signedPath = 'signed/'.$this->tenant->id.'/2025/01/'.$process->id.'_'.$signer->id.'.pdf';
        Storage::put($signedPath, $this->getMockPdfContent());

        SignedDocument::create([
            'uuid' => \Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'signing_process_id' => $process->id,
            'signer_id' => $signer->id,
            'original_document_id' => $document->id,
            'storage_disk' => 'local',
            'signed_path' => $signedPath,
            'signed_name' => 'test.pdf',
            'file_size' => 1024,
            'content_hash' => hash('sha256', $this->getMockPdfContent()),
            'original_hash' => hash('sha256', $this->getMockPdfContent()),
            'hash_algorithm' => 'SHA-256',
            'pkcs7_signature' => bin2hex('signature'),
            'certificate_subject' => 'CN=Test',
            'certificate_issuer' => 'CN=TestCA',
            'certificate_serial' => '123',
            'certificate_fingerprint' => 'abc123',
            'pades_level' => 'B-LT',
            'has_tsa_token' => true,
            'signature_visible' => true,
            'status' => 'signed',
            'signed_at' => now(),
        ]);

        $result = $this->service->generateFinalDocument($process);

        // Final document path should include tenant ID
        $this->assertStringContainsString((string) $this->tenant->id, $result->storagePath);
        $this->assertStringNotContainsString((string) $tenant2->id, $result->storagePath);
    }

    /** @test */
    public function it_calculates_correct_hash(): void
    {
        $document = Document::factory()->for($this->tenant)->create([
            'stored_path' => 'documents/test.pdf',
        ]);

        Storage::put('documents/test.pdf', $this->getMockPdfContent());

        $process = SigningProcess::factory()
            ->for($this->tenant)
            ->for($document)
            ->for($this->user, 'createdBy')
            ->create([
                'status' => SigningProcess::STATUS_COMPLETED,
                'completed_at' => now(),
            ]);

        $signer = Signer::factory()->for($process)->for($this->tenant)->create([
            'status' => 'signed',
            'signed_at' => now(),
        ]);

        $evidencePackage = EvidencePackage::factory()
            ->for($this->tenant)
            ->for($process)
            ->create(['sealed_at' => now()]);

        $signer->update(['evidence_package_id' => $evidencePackage->id]);

        $signedPath = 'signed/'.$this->tenant->id.'/2025/01/'.$process->id.'_'.$signer->id.'.pdf';
        Storage::put($signedPath, $this->getMockPdfContent());

        SignedDocument::create([
            'uuid' => \Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'signing_process_id' => $process->id,
            'signer_id' => $signer->id,
            'original_document_id' => $document->id,
            'storage_disk' => 'local',
            'signed_path' => $signedPath,
            'signed_name' => 'test.pdf',
            'file_size' => 1024,
            'content_hash' => hash('sha256', $this->getMockPdfContent()),
            'original_hash' => hash('sha256', $this->getMockPdfContent()),
            'hash_algorithm' => 'SHA-256',
            'pkcs7_signature' => bin2hex('signature'),
            'certificate_subject' => 'CN=Test',
            'certificate_issuer' => 'CN=TestCA',
            'certificate_serial' => '123',
            'certificate_fingerprint' => 'abc123',
            'pades_level' => 'B-LT',
            'has_tsa_token' => true,
            'signature_visible' => true,
            'status' => 'signed',
            'signed_at' => now(),
        ]);

        $result = $this->service->generateFinalDocument($process);

        // Hash should be 64 characters (SHA-256)
        $this->assertEquals(64, strlen($result->contentHash));

        // Verify hash matches actual file
        $actualContent = Storage::get($result->storagePath);
        $actualHash = hash('sha256', $actualContent);

        $this->assertEquals($actualHash, $result->contentHash);
    }

    /**
     * Get mock PDF content (minimal valid PDF structure).
     */
    private function getMockPdfContent(): string
    {
        return "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj 2 0 obj<</Type/Pages/Count 1/Kids[3 0 R]>>endobj 3 0 obj<</Type/Page/MediaBox[0 0 612 792]/Parent 2 0 R/Resources<<>>>>endobj\nxref\n0 4\n0000000000 65535 f\n0000000009 00000 n\n0000000056 00000 n\n0000000115 00000 n\ntrailer<</Size 4/Root 1 0 R>>\nstartxref\n200\n%%EOF";
    }
}
