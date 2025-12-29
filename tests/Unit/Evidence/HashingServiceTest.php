<?php

namespace Tests\Unit\Evidence;

use App\Services\Evidence\HashingService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class HashingServiceTest extends TestCase
{
    private HashingService $hashingService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->hashingService = new HashingService;
        Storage::fake('local');
    }

    #[Test]
    public function it_hashes_string_content_with_sha256(): void
    {
        $content = 'Hello, World!';
        $hash = $this->hashingService->hashString($content);

        // Known SHA-256 hash for "Hello, World!"
        $expectedHash = 'dffd6021bb2bd5b0af676290809ec3a53191dd81c7f70a4b28688a362182986f';

        $this->assertEquals($expectedHash, $hash);
    }

    #[Test]
    public function it_returns_64_character_hash(): void
    {
        $hash = $this->hashingService->hashString('any content');

        $this->assertEquals(64, strlen($hash));
    }

    #[Test]
    public function it_produces_consistent_hashes(): void
    {
        $content = 'Test content for consistency check';

        $hash1 = $this->hashingService->hashString($content);
        $hash2 = $this->hashingService->hashString($content);

        $this->assertEquals($hash1, $hash2);
    }

    #[Test]
    public function it_produces_different_hashes_for_different_content(): void
    {
        $hash1 = $this->hashingService->hashString('Content A');
        $hash2 = $this->hashingService->hashString('Content B');

        $this->assertNotEquals($hash1, $hash2);
    }

    #[Test]
    public function it_hashes_uploaded_files(): void
    {
        $file = UploadedFile::fake()->createWithContent(
            'test.txt',
            'File content for hashing'
        );

        $hash = $this->hashingService->hashUploadedFile($file);

        $this->assertEquals(64, strlen($hash));
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $hash);
    }

    #[Test]
    public function it_hashes_stored_documents(): void
    {
        $content = 'Stored document content';
        $path = 'documents/test.txt';

        Storage::disk('local')->put($path, $content);

        $hash = $this->hashingService->hashDocument($path, 'local');
        $expectedHash = $this->hashingService->hashString($content);

        $this->assertEquals($expectedHash, $hash);
    }

    #[Test]
    public function it_verifies_document_hash_correctly(): void
    {
        $content = 'Document content to verify';
        $path = 'documents/verify.txt';

        Storage::disk('local')->put($path, $content);

        $expectedHash = $this->hashingService->hashString($content);
        $isValid = $this->hashingService->verifyDocumentHash($path, $expectedHash, 'local');

        $this->assertTrue($isValid);
    }

    #[Test]
    public function it_rejects_invalid_document_hash(): void
    {
        $content = 'Original content';
        $path = 'documents/verify.txt';

        Storage::disk('local')->put($path, $content);

        // Use wrong hash
        $wrongHash = str_repeat('0', 64);
        $isValid = $this->hashingService->verifyDocumentHash($path, $wrongHash, 'local');

        $this->assertFalse($isValid);
    }

    #[Test]
    public function it_returns_false_for_missing_file(): void
    {
        $isValid = $this->hashingService->verifyDocumentHash(
            'nonexistent/file.txt',
            str_repeat('0', 64),
            'local'
        );

        $this->assertFalse($isValid);
    }

    #[Test]
    public function it_hashes_data_deterministically(): void
    {
        $data = [
            'name' => 'John',
            'age' => 30,
            'email' => 'john@example.com',
        ];

        $hash1 = $this->hashingService->hashData($data);

        // Same data in different order
        $dataReordered = [
            'email' => 'john@example.com',
            'name' => 'John',
            'age' => 30,
        ];

        $hash2 = $this->hashingService->hashData($dataReordered);

        $this->assertEquals($hash1, $hash2);
    }

    #[Test]
    public function it_handles_nested_data_structures(): void
    {
        $data = [
            'user' => [
                'name' => 'John',
                'contacts' => [
                    'email' => 'john@example.com',
                    'phone' => '123-456-7890',
                ],
            ],
            'timestamp' => '2025-01-01T00:00:00Z',
        ];

        $hash = $this->hashingService->hashData($data);

        $this->assertEquals(64, strlen($hash));
    }

    #[Test]
    public function it_returns_sha256_as_algorithm(): void
    {
        $algorithm = $this->hashingService->getAlgorithm();

        $this->assertEquals('SHA256', $algorithm);
    }

    #[Test]
    public function it_returns_correct_hash_length(): void
    {
        $length = $this->hashingService->getHashLength();

        $this->assertEquals(64, $length);
    }

    #[Test]
    public function it_validates_hash_format(): void
    {
        $validHash = 'dffd6021bb2bd5b0af676290809ec3a53191dd81c7f70a4b28688a362182986f';
        $invalidHash = 'not-a-valid-hash';
        $shortHash = 'dffd6021bb2bd5b0af676290809ec3a5';
        $uppercaseHash = 'DFFD6021BB2BD5B0AF676290809EC3A53191DD81C7F70A4B28688A362182986F';

        $this->assertTrue($this->hashingService->isValidHash($validHash));
        $this->assertFalse($this->hashingService->isValidHash($invalidHash));
        $this->assertFalse($this->hashingService->isValidHash($shortHash));
        $this->assertTrue($this->hashingService->isValidHash($uppercaseHash));
    }

    #[Test]
    public function it_handles_empty_string(): void
    {
        $hash = $this->hashingService->hashString('');

        // SHA-256 of empty string
        $expectedHash = 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855';

        $this->assertEquals($expectedHash, $hash);
    }

    #[Test]
    public function it_handles_unicode_content(): void
    {
        $content = 'Héllo Wörld! 你好世界 🎉';
        $hash = $this->hashingService->hashString($content);

        $this->assertEquals(64, strlen($hash));
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $hash);
    }

    #[Test]
    public function it_handles_large_content(): void
    {
        // Generate 1MB of content
        $content = str_repeat('A', 1024 * 1024);
        $hash = $this->hashingService->hashString($content);

        $this->assertEquals(64, strlen($hash));
    }

    #[Test]
    public function it_handles_empty_array_data(): void
    {
        $hash = $this->hashingService->hashData([]);

        $this->assertEquals(64, strlen($hash));
    }

    #[Test]
    public function it_handles_null_values_in_data(): void
    {
        $data = [
            'name' => 'John',
            'middleName' => null,
            'age' => 30,
        ];

        $hash = $this->hashingService->hashData($data);

        $this->assertEquals(64, strlen($hash));
    }
}
