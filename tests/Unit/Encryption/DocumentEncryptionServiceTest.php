<?php

namespace Tests\Unit\Encryption;

use App\Exceptions\EncryptionException;
use App\Models\Tenant;
use App\Services\Document\DocumentEncryptionService;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

/**
 * Unit tests for DocumentEncryptionService.
 *
 * @see \App\Services\Document\DocumentEncryptionService
 */
class DocumentEncryptionServiceTest extends TestCase
{
    use RefreshDatabase;

    private DocumentEncryptionService $service;

    private TenantContext $tenantContext;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a test tenant
        $this->tenant = Tenant::factory()->create();

        // Set up tenant context
        $this->tenantContext = app(TenantContext::class);
        $this->tenantContext->set($this->tenant);

        // Create service instance
        $this->service = app(DocumentEncryptionService::class);

        // Set up encryption key for testing
        Config::set('app.encryption_key', 'base64:'.base64_encode(random_bytes(32)));
    }

    /** @test */
    public function it_encrypts_plaintext_successfully(): void
    {
        $plaintext = 'This is secret document content';

        $encrypted = $this->service->encrypt($plaintext);

        $this->assertNotEquals($plaintext, $encrypted);
        $this->assertIsString($encrypted);
        $this->assertGreaterThanOrEqual(28, strlen($encrypted)); // Min size: 12 + 16 bytes
    }

    /** @test */
    public function it_decrypts_encrypted_data_successfully(): void
    {
        $plaintext = 'This is secret document content';

        $encrypted = $this->service->encrypt($plaintext);
        $decrypted = $this->service->decrypt($encrypted);

        $this->assertEquals($plaintext, $decrypted);
    }

    /** @test */
    public function it_produces_different_ciphertext_for_same_plaintext(): void
    {
        $plaintext = 'Same content';

        $encrypted1 = $this->service->encrypt($plaintext);
        $encrypted2 = $this->service->encrypt($plaintext);

        // Due to random nonce, ciphertexts should differ
        $this->assertNotEquals($encrypted1, $encrypted2);

        // But both should decrypt to the same plaintext
        $this->assertEquals($plaintext, $this->service->decrypt($encrypted1));
        $this->assertEquals($plaintext, $this->service->decrypt($encrypted2));
    }

    /** @test */
    public function it_uses_different_keys_for_different_tenants(): void
    {
        $plaintext = 'Same content across tenants';

        // Encrypt for tenant 1
        $this->tenantContext->set($this->tenant);
        $encrypted1 = $this->service->encrypt($plaintext);

        // Create and encrypt for tenant 2
        $tenant2 = Tenant::factory()->create();
        $this->tenantContext->set($tenant2);
        $encrypted2 = $this->service->encrypt($plaintext);

        // Encrypted data should differ
        $this->assertNotEquals($encrypted1, $encrypted2);

        // Decrypt with correct tenant should work
        $this->tenantContext->set($this->tenant);
        $this->assertEquals($plaintext, $this->service->decrypt($encrypted1));

        $this->tenantContext->set($tenant2);
        $this->assertEquals($plaintext, $this->service->decrypt($encrypted2));
    }

    /** @test */
    public function it_cannot_decrypt_with_wrong_tenant_context(): void
    {
        $plaintext = 'Tenant-specific content';

        // Encrypt for tenant 1
        $this->tenantContext->set($this->tenant);
        $encrypted = $this->service->encrypt($plaintext);

        // Try to decrypt with tenant 2 context
        $tenant2 = Tenant::factory()->create();
        $this->tenantContext->set($tenant2);

        $this->expectException(EncryptionException::class);
        $this->service->decrypt($encrypted);
    }

    /** @test */
    public function it_detects_data_tampering(): void
    {
        $plaintext = 'Original content';
        $encrypted = $this->service->encrypt($plaintext);

        // Tamper with the authentication tag (last 16 bytes)
        $tampered = substr($encrypted, 0, -1).'X';

        $this->expectException(EncryptionException::class);
        $this->expectExceptionMessage('Invalid auth tag');
        $this->service->decrypt($tampered);
    }

    /** @test */
    public function it_rejects_invalid_encrypted_data_format(): void
    {
        $invalidData = 'too-short'; // Less than 28 bytes

        $this->expectException(EncryptionException::class);
        $this->expectExceptionMessage('Invalid encrypted data format');
        $this->service->decrypt($invalidData);
    }

    /** @test */
    public function it_identifies_encrypted_data_correctly(): void
    {
        $plaintext = 'Test content';
        $encrypted = $this->service->encrypt($plaintext);

        $this->assertTrue($this->service->isEncrypted($encrypted));
        $this->assertFalse($this->service->isEncrypted($plaintext));
        $this->assertFalse($this->service->isEncrypted('short'));
    }

    /** @test */
    public function it_throws_exception_when_tenant_context_missing_for_encryption(): void
    {
        $this->tenantContext->clear();

        $this->expectException(EncryptionException::class);
        $this->expectExceptionMessage('Tenant context required');
        $this->service->encrypt('test');
    }

    /** @test */
    public function it_throws_exception_when_tenant_context_missing_for_decryption(): void
    {
        // Encrypt with tenant context
        $encrypted = $this->service->encrypt('test');

        // Clear tenant context
        $this->tenantContext->clear();

        $this->expectException(EncryptionException::class);
        $this->expectExceptionMessage('Tenant context required');
        $this->service->decrypt($encrypted);
    }

    /** @test */
    public function it_throws_exception_when_master_key_missing(): void
    {
        Config::set('app.encryption_key', null);

        $this->expectException(EncryptionException::class);
        $this->expectExceptionMessage('Master encryption key not configured');
        $this->service->encrypt('test');
    }

    /** @test */
    public function it_caches_derived_tenant_keys(): void
    {
        Cache::shouldReceive('get')
            ->once()
            ->with("encryption:dek:tenant:{$this->tenant->id}")
            ->andReturn(null);

        Cache::shouldReceive('put')
            ->once()
            ->withArgs(function ($key, $value, $ttl) {
                return $key === "encryption:dek:tenant:{$this->tenant->id}"
                    && strlen($value) === 32
                    && $ttl === 3600;
            });

        $this->service->encrypt('test');
    }

    /** @test */
    public function it_provides_encryption_metadata(): void
    {
        $plaintext = 'Test content';
        $encrypted = $this->service->encrypt($plaintext);

        $metadata = $this->service->getMetadata($encrypted);

        $this->assertTrue($metadata['encrypted']);
        $this->assertTrue($metadata['valid']);
        $this->assertEquals('aes-256-gcm', $metadata['algorithm']);
        $this->assertEquals(12, $metadata['nonce_size']);
        $this->assertEquals(16, $metadata['tag_size']);
        $this->assertArrayHasKey('nonce_hex', $metadata);
        $this->assertArrayHasKey('total_size', $metadata);
    }

    /** @test */
    public function it_handles_large_content(): void
    {
        // Generate 1MB of data
        $largeContent = str_repeat('A', 1024 * 1024);

        $encrypted = $this->service->encrypt($largeContent);
        $decrypted = $this->service->decrypt($encrypted);

        $this->assertEquals($largeContent, $decrypted);
        $this->assertGreaterThan(strlen($largeContent), strlen($encrypted)); // Overhead from nonce + tag
    }

    /** @test */
    public function it_handles_binary_content(): void
    {
        // Test with binary data (PDF-like content)
        $binaryContent = random_bytes(1024);

        $encrypted = $this->service->encrypt($binaryContent);
        $decrypted = $this->service->decrypt($encrypted);

        $this->assertEquals($binaryContent, $decrypted);
    }

    /** @test */
    public function it_clears_key_cache_for_tenant(): void
    {
        // First encrypt to populate cache
        $this->service->encrypt('test');

        Cache::shouldReceive('forget')
            ->once()
            ->with("encryption:dek:tenant:{$this->tenant->id}");

        $this->service->clearKeyCache($this->tenant->id);
    }
}
