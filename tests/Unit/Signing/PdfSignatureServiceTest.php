<?php

declare(strict_types=1);

namespace Tests\Unit\Signing;

use App\Models\Document;
use App\Models\Tenant;
use App\Services\Signing\PdfSignatureService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PdfSignatureServiceTest extends TestCase
{
    use RefreshDatabase;

    private PdfSignatureService $service;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(PdfSignatureService::class);
        $this->tenant = Tenant::factory()->create();
    }

    /**
     * TEST #1: Sign document with valid inputs
     */
    public function test_sign_document_with_valid_inputs(): void
    {
        // Este test requiere PdfSignatureService completamente implementado
        // y certificados válidos. Para MVP, marcar como incomplete.
        $this->markTestIncomplete(
            'Requires full PdfSignatureService implementation with certificates. '.
            'Will be completed in Sprint 5 with complete signature pipeline.'
        );
    }

    /**
     * TEST #2: Fail with expired certificate
     */
    public function test_sign_document_fails_with_expired_certificate(): void
    {
        // Este test requiere certificado expirado
        // Para MVP, marcar como incomplete
        $this->markTestIncomplete('Requires expired certificate setup');
    }

    /**
     * TEST #3: Tenant isolation enforced
     */
    public function test_tenant_isolation(): void
    {
        // Este test requiere PdfSignatureService completamente implementado
        // Para MVP, marcar como incomplete
        $this->markTestIncomplete(
            'Requires full PdfSignatureService implementation. '.
            'Will be completed in Sprint 5 with tenant isolation tests.'
        );
    }
}
