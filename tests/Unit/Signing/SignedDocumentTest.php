<?php

declare(strict_types=1);

namespace Tests\Unit\Signing;

use App\Models\SignedDocument;
use App\Models\Tenant;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SignedDocumentTest extends TestCase
{
    use RefreshDatabase;

    /**
     * TEST #4: Verify integrity with valid hash
     */
    public function test_verify_integrity(): void
    {
        // Arrange
        $tenant = Tenant::factory()->create();

        // Set tenant context
        app(TenantContext::class)->set($tenant);

        $content = '%PDF-1.4 test signed content';
        $hash = hash('sha256', $content);
        $path = 'signed/test.pdf';

        // Create file in the actual storage path
        $fullPath = storage_path('app/'.$path);
        @mkdir(dirname($fullPath), 0755, true);
        file_put_contents($fullPath, $content);

        $signedDoc = SignedDocument::factory()->create([
            'tenant_id' => $tenant->id,
            'signed_path' => $path,
            'content_hash' => $hash,
        ]);

        // Act
        $isValid = $signedDoc->verifyIntegrity();

        // Assert
        $this->assertTrue($isValid);

        // Cleanup
        @unlink($fullPath);
    }

    public function test_verify_integrity_fails_when_file_modified(): void
    {
        // Arrange
        $tenant = Tenant::factory()->create();

        // Set tenant context
        app(TenantContext::class)->set($tenant);

        $originalContent = '%PDF-1.4 original';
        $modifiedContent = '%PDF-1.4 modified';
        $originalHash = hash('sha256', $originalContent);
        $path = 'signed/test-modified.pdf';

        // Create file with modified content
        $fullPath = storage_path('app/'.$path);
        @mkdir(dirname($fullPath), 0755, true);
        file_put_contents($fullPath, $modifiedContent);

        $signedDoc = SignedDocument::factory()->create([
            'tenant_id' => $tenant->id,
            'signed_path' => $path,
            'content_hash' => $originalHash, // Hash of original
        ]);

        // Act
        $isValid = $signedDoc->verifyIntegrity();

        // Assert
        $this->assertFalse($isValid);

        // Cleanup
        @unlink($fullPath);
    }
}
