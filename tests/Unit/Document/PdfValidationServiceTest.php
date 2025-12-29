<?php

declare(strict_types=1);

namespace Tests\Unit\Document;

use App\Services\Document\PdfValidationService;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * Unit tests for PdfValidationService.
 */
class PdfValidationServiceTest extends TestCase
{
    private PdfValidationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PdfValidationService;
    }

    /**
     * Test validation of valid PDF file.
     */
    public function test_validates_valid_pdf_file(): void
    {
        // Create a fake PDF file with proper magic bytes
        $pdfContent = $this->createValidPdfContent();
        $file = $this->createUploadedFileFromContent($pdfContent, 'test.pdf');

        $result = $this->service->validate($file);

        $this->assertTrue($result->isValid());
        $this->assertEmpty($result->errors);
    }

    /**
     * Test validation rejects non-PDF extension.
     */
    public function test_rejects_non_pdf_extension(): void
    {
        $pdfContent = $this->createValidPdfContent();
        $file = $this->createUploadedFileFromContent($pdfContent, 'test.txt', 'text/plain');

        $result = $this->service->validate($file);

        $this->assertFalse($result->isValid());
        $this->assertContains('File must have .pdf extension', $result->errors);
    }

    /**
     * Test validation rejects file exceeding max size.
     */
    public function test_rejects_file_exceeding_max_size(): void
    {
        // Set max size to 1KB for testing
        config(['documents.max_size' => 1024]);

        // Create a 2KB PDF
        $pdfContent = $this->createValidPdfContent().str_repeat('X', 2048);
        $file = $this->createUploadedFileFromContent($pdfContent, 'test.pdf');

        $result = $this->service->validate($file);

        $this->assertFalse($result->isValid());
        $this->assertTrue(
            collect($result->errors)->contains(fn ($e) => str_contains($e, 'exceeds maximum size'))
        );
    }

    /**
     * Test validation rejects invalid MIME type.
     */
    public function test_rejects_invalid_mime_type(): void
    {
        $file = UploadedFile::fake()->create('test.pdf', 100, 'text/plain');

        $result = $this->service->validate($file);

        $this->assertFalse($result->isValid());
        $this->assertTrue(
            collect($result->errors)->contains(fn ($e) => str_contains($e, 'Invalid MIME type'))
        );
    }

    /**
     * Test magic bytes validation with valid PDF header.
     */
    public function test_validates_magic_bytes_with_valid_pdf(): void
    {
        $pdfContent = $this->createValidPdfContent();
        $file = $this->createUploadedFileFromContent($pdfContent, 'test.pdf');

        $result = $this->service->validateMagicBytes($file);

        $this->assertTrue($result['valid']);
        $this->assertArrayHasKey('version', $result);
    }

    /**
     * Test magic bytes validation rejects non-PDF content.
     */
    public function test_rejects_invalid_magic_bytes(): void
    {
        $content = 'This is not a PDF file';
        $file = $this->createUploadedFileFromContent($content, 'test.pdf');

        $result = $this->service->validateMagicBytes($file);

        $this->assertFalse($result['valid']);
        $this->assertArrayHasKey('error', $result);
    }

    /**
     * Test detection of JavaScript in PDF.
     */
    public function test_detects_javascript_in_pdf(): void
    {
        $content = '%PDF-1.4 /JS (some javascript) /JavaScript %%EOF';

        $hasJs = $this->service->detectJavaScript($content);

        $this->assertTrue($hasJs);
    }

    /**
     * Test JavaScript not detected in clean PDF.
     */
    public function test_does_not_detect_javascript_in_clean_pdf(): void
    {
        $content = '%PDF-1.4 /Type /Page /Contents stream endstream %%EOF';

        $hasJs = $this->service->detectJavaScript($content);

        $this->assertFalse($hasJs);
    }

    /**
     * Test detection of encryption in PDF.
     */
    public function test_detects_encryption_in_pdf(): void
    {
        $content = '%PDF-1.4 /Encrypt << /Filter /Standard >> %%EOF';

        $hasEncryption = $this->service->detectEncryption($content);

        $this->assertTrue($hasEncryption);
    }

    /**
     * Test encryption not detected in unencrypted PDF.
     */
    public function test_does_not_detect_encryption_in_unencrypted_pdf(): void
    {
        $content = '%PDF-1.4 /Type /Page %%EOF';

        $hasEncryption = $this->service->detectEncryption($content);

        $this->assertFalse($hasEncryption);
    }

    /**
     * Test detection of digital signatures in PDF.
     */
    public function test_detects_signatures_in_pdf(): void
    {
        $content = '%PDF-1.4 /Type /Sig /SigFlags 3 %%EOF';

        $hasSignatures = $this->service->detectSignatures($content);

        $this->assertTrue($hasSignatures);
    }

    /**
     * Test PDF/A detection.
     */
    public function test_detects_pdfa_compliance(): void
    {
        $content = '%PDF-1.4 pdfaid:part PDF/A-3b conformance %%EOF';

        $isPdfA = $this->service->detectPdfA($content);

        $this->assertTrue($isPdfA);
    }

    /**
     * Test page count estimation.
     */
    public function test_estimates_page_count(): void
    {
        $content = '%PDF-1.4 /Type /Page /Type /Page /Type /Page /Type /Pages %%EOF';

        $pageCount = $this->service->estimatePageCount($content);

        // Should count 3 /Type /Page (not /Type /Pages)
        $this->assertEquals(3, $pageCount);
    }

    /**
     * Test basic metadata extraction.
     */
    public function test_extracts_basic_metadata(): void
    {
        $content = '%PDF-1.4 /Title (Test Document) /Author (John Doe) /Creator (Test App) %%EOF';

        $metadata = $this->service->extractBasicMetadata($content);

        $this->assertEquals('Test Document', $metadata['title']);
        $this->assertEquals('John Doe', $metadata['author']);
        $this->assertEquals('Test App', $metadata['creator']);
    }

    /**
     * Test filename sanitization removes path components.
     */
    public function test_sanitize_filename_removes_path_components(): void
    {
        $dangerous = '../../../etc/passwd.pdf';

        $sanitized = $this->service->sanitizeFilename($dangerous);

        // basename() returns just 'passwd.pdf', then '..' is removed
        $this->assertEquals('passwd.pdf', $sanitized);
        $this->assertStringNotContainsString('..', $sanitized);
        $this->assertStringNotContainsString('/', $sanitized);
    }

    /**
     * Test filename sanitization removes null bytes.
     */
    public function test_sanitize_filename_removes_null_bytes(): void
    {
        $dangerous = "test\0.pdf";

        $sanitized = $this->service->sanitizeFilename($dangerous);

        $this->assertEquals('test.pdf', $sanitized);
    }

    /**
     * Test filename sanitization limits length.
     */
    public function test_sanitize_filename_limits_length(): void
    {
        $longName = str_repeat('a', 300).'.pdf';

        $sanitized = $this->service->sanitizeFilename($longName);

        $this->assertLessThanOrEqual(255, strlen($sanitized));
        $this->assertStringEndsWith('.pdf', $sanitized);
    }

    /**
     * Test filename sanitization handles empty filename.
     */
    public function test_sanitize_filename_handles_empty_filename(): void
    {
        $sanitized = $this->service->sanitizeFilename('');

        $this->assertEquals('document.pdf', $sanitized);
    }

    /**
     * Test dangerous patterns detection.
     */
    public function test_detects_dangerous_patterns(): void
    {
        config(['documents.validation.dangerous_patterns' => [
            '/\/Launch\s/',
            '/\/OpenAction\s/',
        ]]);

        $content = '%PDF-1.4 /Launch /OpenAction /Type /Page %%EOF';

        $dangerous = $this->service->detectDangerousPatterns($content);

        $this->assertNotEmpty($dangerous);
    }

    /**
     * Test validation result has correct structure.
     */
    public function test_validation_result_structure(): void
    {
        $pdfContent = $this->createValidPdfContent();
        $file = $this->createUploadedFileFromContent($pdfContent, 'test.pdf');

        $result = $this->service->validate($file);

        $this->assertIsBool($result->valid);
        $this->assertIsArray($result->errors);
        $this->assertIsArray($result->warnings);
        $this->assertIsArray($result->metadata);
    }

    /**
     * Helper to create valid PDF content.
     */
    private function createValidPdfContent(): string
    {
        return '%PDF-1.7
1 0 obj
<< /Type /Catalog /Pages 2 0 R >>
endobj
2 0 obj
<< /Type /Pages /Kids [3 0 R] /Count 1 >>
endobj
3 0 obj
<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] >>
endobj
xref
0 4
trailer
<< /Size 4 /Root 1 0 R >>
startxref
%%EOF';
    }

    /**
     * Helper to create an uploaded file from content.
     */
    private function createUploadedFileFromContent(
        string $content,
        string $name,
        string $mimeType = 'application/pdf'
    ): UploadedFile {
        $tempPath = tempnam(sys_get_temp_dir(), 'test_pdf_');
        file_put_contents($tempPath, $content);

        return new UploadedFile(
            $tempPath,
            $name,
            $mimeType,
            null,
            true
        );
    }
}
