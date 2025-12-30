<?php

declare(strict_types=1);

namespace Tests\Unit\Signing;

use App\Services\Signing\CertificateService;
use Tests\TestCase;

class CertificateServiceTest extends TestCase
{
    private CertificateService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(CertificateService::class);
    }

    /**
     * TEST #5: Load certificate successfully
     */
    public function test_load_certificate(): void
    {
        // Act
        $certificate = $this->service->loadCertificate();

        // Assert
        $this->assertNotNull($certificate);
        $this->assertStringContainsString('Firmalum', $certificate->getSubject());
        $this->assertTrue($certificate->isValid());
        $this->assertFalse($certificate->isExpired());
        $this->assertGreaterThan(0, $certificate->getDaysUntilExpiration());
    }

    public function test_load_private_key(): void
    {
        // Act
        $privateKey = $this->service->getPrivateKey();

        // Assert
        $this->assertNotNull($privateKey);
        $this->assertTrue($privateKey->isRsa());
        $this->assertGreaterThanOrEqual(4096, $privateKey->getBits());
    }

    public function test_certificate_info_returns_complete_data(): void
    {
        // Act
        $info = $this->service->getCertificateInfo();

        // Assert
        $this->assertIsArray($info);
        $this->assertArrayHasKey('certificate', $info);
        $this->assertArrayHasKey('private_key', $info);
        $this->assertArrayHasKey('is_valid', $info);
        $this->assertTrue($info['is_valid']);
    }

    public function test_is_self_signed_returns_true_for_dev_cert(): void
    {
        // Act
        $isSelfSigned = $this->service->isSelfSigned();

        // Assert
        $this->assertTrue($isSelfSigned);
    }
}
