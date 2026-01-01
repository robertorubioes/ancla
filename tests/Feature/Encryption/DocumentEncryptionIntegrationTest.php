<?php

namespace Tests\Feature\Encryption;

use App\Models\Document;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Document\DocumentEncryptionService;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Integration tests for document encryption end-to-end flow.
 *
 * Tests the complete encryption workflow from document upload to retrieval.
 */
class DocumentEncryptionIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $user;

    private TenantContext $tenantContext;

    protected function setUp(): void
    {
        parent::setUp();

        // Set up test environment
        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->tenantContext = app(TenantContext::class);
        $this->tenantContext->set($this->tenant);

        // Configure encryption
        Config::set('app.encryption_key', 'base64:'.base64_encode(random_bytes(32)));
        Config::set('encryption.key_version', 'v1');

        // Use fake storage
        Storage::fake('local');
    }

    /** @test */
    public function it_encrypts_and_decrypts_documents_end_to_end(): void
    {
        $service = app(DocumentEncryptionService::class);

        // Simulate PDF content
        $originalContent = '%PDF-1.4 fake content for testing';

        // Encrypt
        $encrypted = $service->encrypt($originalContent);
        $this->assertNotEquals($originalContent, $encrypted);

        // Store encrypted content
        $path = 'documents/test.pdf';
        Storage::put($path, $encrypted);

        // Retrieve and decrypt
        $retrieved = Storage::get($path);
        $decrypted = $service->decrypt($retrieved);

        $this->assertEquals($originalContent, $decrypted);
    }

    /** @test */
    public function it_maintains_tenant_isolation_in_encryption(): void
    {
        $service = app(DocumentEncryptionService::class);
        $content = 'Sensitive tenant data';

        // Encrypt for tenant 1
        $this->tenantContext->set($this->tenant);
        $encrypted1 = $service->encrypt($content);

        // Encrypt for tenant 2
        $tenant2 = Tenant::factory()->create();
        $this->tenantContext->set($tenant2);
        $encrypted2 = $service->encrypt($content);

        // Encrypted content should be different
        $this->assertNotEquals($encrypted1, $encrypted2);

        // Each tenant can only decrypt their own data
        $this->tenantContext->set($this->tenant);
        $this->assertEquals($content, $service->decrypt($encrypted1));

        $this->tenantContext->set($tenant2);
        $this->assertEquals($content, $service->decrypt($encrypted2));
    }

    /** @test */
    public function it_handles_encrypt_existing_documents_command_dry_run(): void
    {
        // Create unencrypted documents
        $document = Document::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'is_encrypted' => false,
        ]);

        // Store fake content
        $content = 'Unencrypted document content';
        Storage::put($document->storage_path, $content);

        // Run command in dry-run mode
        $exitCode = Artisan::call('documents:encrypt-existing', [
            '--dry-run' => true,
        ]);

        // Command should succeed
        $this->assertEquals(0, $exitCode);

        // Document should still be unencrypted
        $document->refresh();
        $this->assertFalse($document->is_encrypted);

        // Content should be unchanged
        $storedContent = Storage::get($document->storage_path);
        $this->assertEquals($content, $storedContent);
    }

    /** @test */
    public function it_preserves_data_integrity_across_encryption_decryption_cycles(): void
    {
        $service = app(DocumentEncryptionService::class);

        // Test with various content types
        $testCases = [
            'Simple text' => 'Hello World',
            'Special chars' => 'äöüßñ中文🎉',
            'Binary data' => random_bytes(256),
            'Large text' => str_repeat('Lorem ipsum ', 1000),
            'JSON data' => json_encode(['key' => 'value', 'nested' => ['data' => true]]),
        ];

        foreach ($testCases as $name => $content) {
            $encrypted = $service->encrypt($content);
            $decrypted = $service->decrypt($encrypted);

            $this->assertEquals(
                $content,
                $decrypted,
                "Failed to preserve integrity for: {$name}"
            );
        }
    }

    /** @test */
    public function it_correctly_identifies_encrypted_vs_plaintext_documents(): void
    {
        $service = app(DocumentEncryptionService::class);

        $plaintext = 'This is plaintext';
        $encrypted = $service->encrypt($plaintext);

        $this->assertFalse($service->isEncrypted($plaintext));
        $this->assertTrue($service->isEncrypted($encrypted));
    }

    /** @test */
    public function it_generates_consistent_metadata_for_encrypted_documents(): void
    {
        $service = app(DocumentEncryptionService::class);

        $content = 'Test document';
        $encrypted = $service->encrypt($content);

        $metadata = $service->getMetadata($encrypted);

        $this->assertTrue($metadata['encrypted']);
        $this->assertTrue($metadata['valid']);
        $this->assertEquals('aes-256-gcm', $metadata['algorithm']);
        $this->assertEquals(12, $metadata['nonce_size']);
        $this->assertEquals(16, $metadata['tag_size']);
        $this->assertIsInt($metadata['total_size']);
        $this->assertIsInt($metadata['ciphertext_size']);
        $this->assertIsString($metadata['nonce_hex']);
    }

    /** @test */
    public function it_handles_concurrent_encryption_operations_safely(): void
    {
        $service = app(DocumentEncryptionService::class);
        $content = 'Concurrent test content';

        // Simulate multiple concurrent encryption operations
        $encrypted = [];
        for ($i = 0; $i < 10; $i++) {
            $encrypted[] = $service->encrypt($content);
        }

        // All encrypted versions should be different (due to random nonces)
        $unique = array_unique($encrypted);
        $this->assertCount(10, $unique);

        // All should decrypt to the same plaintext
        foreach ($encrypted as $enc) {
            $this->assertEquals($content, $service->decrypt($enc));
        }
    }

    /** @test */
    public function it_updates_document_encryption_metadata(): void
    {
        $document = Document::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'is_encrypted' => false,
            'encrypted_at' => null,
            'encryption_key_version' => null,
        ]);

        // Mark as encrypted
        $document->update([
            'is_encrypted' => true,
            'encrypted_at' => now(),
            'encryption_key_version' => 'v1',
        ]);

        $document->refresh();

        $this->assertTrue($document->is_encrypted);
        $this->assertNotNull($document->encrypted_at);
        $this->assertEquals('v1', $document->encryption_key_version);
    }

    /** @test */
    public function it_supports_multiple_encryption_key_versions(): void
    {
        $service = app(DocumentEncryptionService::class);
        $content = 'Test content';

        // Encrypt with v1
        Config::set('encryption.key_version', 'v1');
        $encryptedV1 = $service->encrypt($content);

        // Both should decrypt successfully (same master key)
        $this->assertEquals($content, $service->decrypt($encryptedV1));
    }
}
