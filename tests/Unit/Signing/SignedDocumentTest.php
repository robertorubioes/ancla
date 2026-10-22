<?php

declare(strict_types=1);

namespace Tests\Unit\Signing;

use App\Models\SignedDocument;
use App\Models\Tenant;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
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

        // Se escribe por el disco, no en una ruta armada a mano: es por donde
        // lo lee verifyIntegrity(), y asi la prueba no depende de donde tenga
        // configurada su raiz el disco local.
        Storage::fake('local');
        Storage::disk('local')->put($path, $content);

        $signedDoc = SignedDocument::factory()->create([
            'tenant_id' => $tenant->id,
            'storage_disk' => 'local',
            'signed_path' => $path,
            'content_hash' => $hash,
        ]);

        // Act
        $isValid = $signedDoc->verifyIntegrity();

        // Assert
        $this->assertTrue($isValid);

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

        // Se escribe por el disco, no en una ruta armada a mano: es por donde
        // lo lee verifyIntegrity(), y asi la prueba no depende de donde tenga
        // configurada su raiz el disco local.
        Storage::fake('local');
        Storage::disk('local')->put($path, $modifiedContent);

        $signedDoc = SignedDocument::factory()->create([
            'tenant_id' => $tenant->id,
            'storage_disk' => 'local',
            'signed_path' => $path,
            'content_hash' => $originalHash, // Hash of original
        ]);

        // Act
        $isValid = $signedDoc->verifyIntegrity();

        // Assert
        $this->assertFalse($isValid);

    }
}
