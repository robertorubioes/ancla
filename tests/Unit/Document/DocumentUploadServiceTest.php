<?php

declare(strict_types=1);

namespace Tests\Unit\Document;

use App\Models\Document;
use App\Models\Tenant;
use App\Models\TsaToken;
use App\Models\User;
use App\Services\Document\DocumentUploadException;
use App\Services\Document\DocumentUploadService;
use App\Services\Document\DuplicateDocumentException;
use App\Services\Document\PdfValidationService;
use App\Services\Document\ValidationResult;
use App\Services\Evidence\AuditTrailService;
use App\Services\Evidence\HashingService;
use App\Services\Evidence\TsaService;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * Unit tests for DocumentUploadService.
 */
class DocumentUploadServiceTest extends TestCase
{
    use RefreshDatabase;

    private DocumentUploadService $service;

    private MockInterface $validator;

    private MockInterface $hashingService;

    private MockInterface $tsaService;

    private MockInterface $auditService;

    private MockInterface $tenantContext;

    private Tenant $tenant;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->validator = Mockery::mock(PdfValidationService::class);
        $this->hashingService = Mockery::mock(HashingService::class);
        $this->tsaService = Mockery::mock(TsaService::class);
        $this->auditService = Mockery::mock(AuditTrailService::class);
        $this->tenantContext = Mockery::mock(TenantContext::class);

        $this->service = new DocumentUploadService(
            $this->validator,
            $this->hashingService,
            $this->tsaService,
            $this->auditService,
            $this->tenantContext
        );
    }

    /**
     * Test successful document upload.
     */
    public function test_uploads_document_successfully(): void
    {
        $file = $this->createTestPdfFile();
        $hash = hash('sha256', 'test-content');

        $tsaToken = TsaToken::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->validator->shouldReceive('validate')
            ->once()
            ->andReturn(new ValidationResult(
                valid: true,
                errors: [],
                warnings: [],
                metadata: ['page_count' => 5, 'pdf_version' => '1.7']
            ));

        $this->validator->shouldReceive('sanitizeFilename')
            ->once()
            ->andReturn('test.pdf');

        $this->hashingService->shouldReceive('hashUploadedFile')
            ->once()
            ->andReturn($hash);

        $this->tenantContext->shouldReceive('get')
            ->once()
            ->andReturn($this->tenant);

        $this->tsaService->shouldReceive('requestTimestamp')
            ->once()
            ->with($hash)
            ->andReturn($tsaToken);

        $this->auditService->shouldReceive('record')
            ->once()
            ->with(Mockery::type(Document::class), 'document.uploaded', Mockery::type('array'));

        $document = $this->service->upload($file, $this->user);

        $this->assertInstanceOf(Document::class, $document);
        $this->assertEquals($this->tenant->id, $document->tenant_id);
        $this->assertEquals($this->user->id, $document->user_id);
        $this->assertEquals('test.pdf', $document->original_filename);
        $this->assertEquals($hash, $document->sha256_hash);
        $this->assertEquals(Document::STATUS_READY, $document->status);
    }

    /**
     * Test upload fails with validation error.
     */
    public function test_upload_fails_with_validation_error(): void
    {
        $file = $this->createTestPdfFile();

        $this->validator->shouldReceive('validate')
            ->once()
            ->andReturn(new ValidationResult(
                valid: false,
                errors: ['Invalid PDF structure', 'File too large'],
                warnings: [],
                metadata: []
            ));

        $this->expectException(DocumentUploadException::class);
        $this->expectExceptionMessage('PDF validation failed');

        $this->service->upload($file, $this->user);
    }

    /**
     * Test upload fails for duplicate document.
     */
    public function test_upload_fails_for_duplicate_document(): void
    {
        $file = $this->createTestPdfFile();
        $hash = hash('sha256', 'test-content');

        // Create existing document with same hash
        $existingDocument = Document::factory()->create([
            'tenant_id' => $this->tenant->id,
            'sha256_hash' => $hash,
        ]);

        $this->validator->shouldReceive('validate')
            ->once()
            ->andReturn(new ValidationResult(valid: true));

        $this->hashingService->shouldReceive('hashUploadedFile')
            ->once()
            ->andReturn($hash);

        $this->tenantContext->shouldReceive('get')
            ->once()
            ->andReturn($this->tenant);

        $this->expectException(DuplicateDocumentException::class);

        $this->service->upload($file, $this->user);
    }

    /**
     * Test upload fails without tenant context.
     */
    public function test_upload_fails_without_tenant_context(): void
    {
        $file = $this->createTestPdfFile();

        $this->validator->shouldReceive('validate')
            ->once()
            ->andReturn(new ValidationResult(valid: true));

        $this->hashingService->shouldReceive('hashUploadedFile')
            ->once()
            ->andReturn(hash('sha256', 'test'));

        $this->tenantContext->shouldReceive('get')
            ->once()
            ->andReturn(null);

        $this->expectException(DocumentUploadException::class);
        $this->expectExceptionMessage('No tenant context available');

        $this->service->upload($file, $this->user);
    }

    /**
     * Test PDF validation returns validation result.
     */
    public function test_validate_pdf_returns_result(): void
    {
        $file = $this->createTestPdfFile();

        $expectedResult = new ValidationResult(
            valid: true,
            errors: [],
            warnings: ['Contains JavaScript'],
            metadata: ['page_count' => 10]
        );

        $this->validator->shouldReceive('validate')
            ->once()
            ->with($file)
            ->andReturn($expectedResult);

        $result = $this->service->validatePdf($file);

        $this->assertSame($expectedResult, $result);
    }

    /**
     * Test document integrity verification passes.
     */
    public function test_verify_integrity_passes_for_valid_document(): void
    {
        $content = 'test-document-content';
        $hash = hash('sha256', $content);

        $document = Document::factory()->create([
            'tenant_id' => $this->tenant->id,
            'sha256_hash' => $hash,
            'storage_disk' => 'local',
            'storage_path' => 'test/document.pdf',
            'is_encrypted' => false,
        ]);

        Storage::disk('local')->put('test/document.pdf', $content);

        $this->hashingService->shouldReceive('hashString')
            ->once()
            ->with($content)
            ->andReturn($hash);

        $this->auditService->shouldReceive('record')
            ->once()
            ->with(Mockery::type(Document::class), 'document.integrity_verified', Mockery::type('array'));

        $result = $this->service->verifyIntegrity($document);

        $this->assertTrue($result);
        $this->assertNotNull($document->fresh()->hash_verified_at);
    }

    /**
     * Test document integrity verification fails.
     */
    public function test_verify_integrity_fails_for_tampered_document(): void
    {
        $originalHash = hash('sha256', 'original-content');
        $tamperedHash = hash('sha256', 'tampered-content');

        $document = Document::factory()->create([
            'tenant_id' => $this->tenant->id,
            'sha256_hash' => $originalHash,
            'storage_disk' => 'local',
            'storage_path' => 'test/document.pdf',
            'is_encrypted' => false,
        ]);

        Storage::disk('local')->put('test/document.pdf', 'tampered-content');

        $this->hashingService->shouldReceive('hashString')
            ->once()
            ->andReturn($tamperedHash);

        $this->auditService->shouldReceive('record')
            ->once()
            ->with(Mockery::type(Document::class), 'document.integrity_failed', Mockery::type('array'));

        $result = $this->service->verifyIntegrity($document);

        $this->assertFalse($result);
    }

    /**
     * Test document soft delete.
     */
    public function test_delete_soft_deletes_document(): void
    {
        $document = Document::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $this->auditService->shouldReceive('record')
            ->once()
            ->with(Mockery::type(Document::class), 'document.deleted', Mockery::type('array'));

        $result = $this->service->delete($document);

        $this->assertTrue($result);
        $this->assertSoftDeleted($document);
    }

    /**
     * Test document force delete removes files.
     */
    public function test_force_delete_removes_files(): void
    {
        $document = Document::factory()->withThumbnail()->create([
            'tenant_id' => $this->tenant->id,
            'storage_disk' => 'local',
            'storage_path' => 'test/document.pdf.enc',
        ]);

        Storage::disk('local')->put('test/document.pdf.enc', 'encrypted-content');
        Storage::disk('local')->put($document->thumbnail_path, 'thumbnail-content');

        $this->auditService->shouldReceive('record')
            ->once()
            ->with(Mockery::type(Document::class), 'document.permanently_deleted', Mockery::type('array'));

        $result = $this->service->forceDelete($document);

        $this->assertTrue($result);
        $this->assertDatabaseMissing('documents', ['id' => $document->id]);
        Storage::disk('local')->assertMissing('test/document.pdf.enc');
    }

    /**
     * Test get decrypted content for encrypted document.
     */
    public function test_get_decrypted_content_for_encrypted_document(): void
    {
        $originalContent = 'test-pdf-content';
        $encryptedContent = encrypt($originalContent);

        $document = Document::factory()->create([
            'tenant_id' => $this->tenant->id,
            'storage_disk' => 'local',
            'storage_path' => 'test/document.pdf.enc',
            'is_encrypted' => true,
        ]);

        Storage::disk('local')->put('test/document.pdf.enc', $encryptedContent);

        $content = $this->service->getDecryptedContent($document);

        $this->assertEquals($originalContent, $content);
    }

    /**
     * Test get decrypted content for unencrypted document.
     */
    public function test_get_decrypted_content_for_unencrypted_document(): void
    {
        $originalContent = 'test-pdf-content';

        $document = Document::factory()->unencrypted()->create([
            'tenant_id' => $this->tenant->id,
            'storage_disk' => 'local',
            'storage_path' => 'test/document.pdf',
        ]);

        Storage::disk('local')->put('test/document.pdf', $originalContent);

        $content = $this->service->getDecryptedContent($document);

        $this->assertEquals($originalContent, $content);
    }

    /**
     * Test get decrypted content throws exception for missing file.
     */
    public function test_get_decrypted_content_throws_for_missing_file(): void
    {
        $document = Document::factory()->create([
            'tenant_id' => $this->tenant->id,
            'storage_disk' => 'local',
            'storage_path' => 'nonexistent/document.pdf',
        ]);

        $this->expectException(DocumentUploadException::class);
        $this->expectExceptionMessage('Document file not found');

        $this->service->getDecryptedContent($document);
    }

    /**
     * Test encrypt and store creates proper path.
     */
    public function test_encrypt_and_store_creates_proper_path(): void
    {
        $file = $this->createTestPdfFile();
        $document = Document::factory()->make([
            'uuid' => 'test-uuid-1234',
            'tenant_id' => $this->tenant->id,
        ]);
        $document->save();

        $result = $this->service->encryptAndStore($file, $document);

        $this->assertEquals('local', $result['disk']);
        $this->assertStringContainsString('documents', $result['path']);
        $this->assertStringContainsString((string) $this->tenant->id, $result['path']);
        $this->assertTrue($result['encrypted']);
        Storage::disk('local')->assertExists($result['path']);
    }

    /**
     * Helper to create a test PDF file.
     */
    private function createTestPdfFile(): UploadedFile
    {
        $pdfContent = '%PDF-1.7
1 0 obj
<< /Type /Catalog /Pages 2 0 R >>
endobj
2 0 obj
<< /Type /Pages /Kids [3 0 R] /Count 1 >>
endobj
3 0 obj
<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] >>
endobj
%%EOF';

        $tempPath = tempnam(sys_get_temp_dir(), 'test_pdf_');
        file_put_contents($tempPath, $pdfContent);

        return new UploadedFile(
            $tempPath,
            'test.pdf',
            'application/pdf',
            null,
            true
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
