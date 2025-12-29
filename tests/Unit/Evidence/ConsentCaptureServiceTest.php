<?php

namespace Tests\Unit\Evidence;

use App\Models\ConsentRecord;
use App\Models\EvidencePackage;
use App\Models\Tenant;
use App\Models\TsaToken;
use App\Services\Evidence\AuditTrailService;
use App\Services\Evidence\ConsentCaptureService;
use App\Services\Evidence\HashingService;
use App\Services\Evidence\TsaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ConsentCaptureServiceTest extends TestCase
{
    use RefreshDatabase;

    private ConsentCaptureService $service;

    private HashingService $hashingService;

    private TsaService $tsaService;

    private AuditTrailService $auditTrailService;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->hashingService = new HashingService;

        // Mock TSA service
        $this->tsaService = Mockery::mock(TsaService::class);
        $tsaToken = TsaToken::factory()->create();
        $this->tsaService->shouldReceive('getTimestamp')->andReturn($tsaToken);
        $this->tsaService->shouldReceive('verifyToken')->andReturn(true);

        // Mock audit trail service
        $this->auditTrailService = Mockery::mock(AuditTrailService::class);
        $this->auditTrailService->shouldReceive('logEvent')->andReturn(null);

        $this->service = new ConsentCaptureService(
            $this->hashingService,
            $this->tsaService,
            $this->auditTrailService
        );

        // Create and bind tenant
        $tenant = Tenant::factory()->create();
        app()->instance('tenant', $tenant);
    }

    #[Test]
    public function it_records_consent_acceptance(): void
    {
        $signable = EvidencePackage::factory()->create();

        $record = $this->service->recordConsent(
            $signable,
            'test@example.com',
            'signature',
            'accepted'
        );

        $this->assertInstanceOf(ConsentRecord::class, $record);
        $this->assertEquals('test@example.com', $record->signer_email);
        $this->assertEquals('signature', $record->consent_type);
        $this->assertEquals('accepted', $record->action);
        $this->assertTrue($record->isAccepted());
    }

    #[Test]
    public function it_records_consent_rejection(): void
    {
        $signable = EvidencePackage::factory()->create();

        $record = $this->service->recordConsent(
            $signable,
            'test@example.com',
            'terms',
            'rejected'
        );

        $this->assertEquals('rejected', $record->action);
        $this->assertTrue($record->isRejected());
        $this->assertFalse($record->isAccepted());
    }

    #[Test]
    public function it_records_signature_consent_convenience_method(): void
    {
        $signable = EvidencePackage::factory()->create();

        $record = $this->service->recordSignatureConsent($signable, 'test@example.com');

        $this->assertEquals('signature', $record->consent_type);
        $this->assertEquals('accepted', $record->action);
    }

    #[Test]
    public function it_revokes_consent(): void
    {
        $signable = EvidencePackage::factory()->create();

        $original = $this->service->recordConsent(
            $signable,
            'test@example.com',
            'privacy',
            'accepted'
        );

        $revoked = $this->service->revokeConsent($original, 'User requested');

        $this->assertEquals('revoked', $revoked->action);
        $this->assertTrue($revoked->isRevoked());
    }

    #[Test]
    public function it_hashes_legal_text(): void
    {
        $signable = EvidencePackage::factory()->create();

        $record = $this->service->recordConsent(
            $signable,
            'test@example.com',
            'signature',
            'accepted'
        );

        $expectedHash = $this->hashingService->hashContent($record->legal_text_content);

        $this->assertEquals($expectedHash, $record->legal_text_hash);
        $this->assertEquals(64, strlen($record->legal_text_hash));
    }

    #[Test]
    public function it_generates_verification_hash(): void
    {
        $signable = EvidencePackage::factory()->create();

        $record = $this->service->recordConsent(
            $signable,
            'test@example.com',
            'signature',
            'accepted'
        );

        $this->assertNotEmpty($record->verification_hash);
        $this->assertEquals(64, strlen($record->verification_hash));
    }

    #[Test]
    public function it_requests_tsa_timestamp_for_accepted_consent(): void
    {
        $signable = EvidencePackage::factory()->create();

        $record = $this->service->recordConsent(
            $signable,
            'test@example.com',
            'signature',
            'accepted'
        );

        $this->assertNotNull($record->tsa_token_id);
        $this->assertTrue($record->hasTsa());
    }

    #[Test]
    public function it_does_not_request_tsa_for_rejected_consent(): void
    {
        // Create a fresh mock that expects no TSA calls for rejected consent
        $tsaService = Mockery::mock(TsaService::class);
        $tsaService->shouldNotReceive('getTimestamp');

        $service = new ConsentCaptureService(
            $this->hashingService,
            $tsaService,
            $this->auditTrailService
        );

        $signable = EvidencePackage::factory()->create();

        $record = $service->recordConsent(
            $signable,
            'test@example.com',
            'signature',
            'rejected'
        );

        $this->assertNull($record->tsa_token_id);
    }

    #[Test]
    public function it_stores_screenshot(): void
    {
        $signable = EvidencePackage::factory()->create();

        // Create a simple base64 image
        $imageData = base64_encode('fake image data');
        $dataUrl = "data:image/png;base64,{$imageData}";

        $record = $this->service->recordConsent(
            $signable,
            'test@example.com',
            'signature',
            'accepted',
            $dataUrl
        );

        $this->assertNotNull($record->screenshot_path);
        $this->assertNotNull($record->screenshot_hash);
        $this->assertTrue($record->hasScreenshot());
        Storage::disk('local')->assertExists($record->screenshot_path);
    }

    #[Test]
    public function it_stores_ui_context(): void
    {
        $signable = EvidencePackage::factory()->create();

        $record = $this->service->recordConsent(
            $signable,
            'test@example.com',
            'signature',
            'accepted',
            null,
            [
                'element_id' => 'consent-checkbox-1',
                'visible_duration_ms' => 5000,
                'scroll_to_bottom' => true,
            ]
        );

        $this->assertEquals('consent-checkbox-1', $record->ui_element_id);
        $this->assertEquals(5000, $record->ui_visible_duration_ms);
        $this->assertTrue($record->scroll_to_bottom);
    }

    #[Test]
    public function it_verifies_consent_integrity(): void
    {
        $signable = EvidencePackage::factory()->create();

        $record = $this->service->recordConsent(
            $signable,
            'test@example.com',
            'signature',
            'accepted'
        );

        $result = $this->service->verifyConsent($record);

        $this->assertTrue($result['is_valid']);
        $this->assertEmpty($result['errors']);
    }

    #[Test]
    public function it_retrieves_records_for_signable(): void
    {
        $signable = EvidencePackage::factory()->create();

        $this->service->recordConsent($signable, 'user1@test.com', 'signature', 'accepted');
        $this->service->recordConsent($signable, 'user2@test.com', 'terms', 'accepted');

        $records = $this->service->getForSignable($signable);

        $this->assertCount(2, $records);
    }

    #[Test]
    public function it_retrieves_records_for_signer(): void
    {
        $signable = EvidencePackage::factory()->create();

        $this->service->recordConsent($signable, 'target@test.com', 'signature', 'accepted');
        $this->service->recordConsent($signable, 'other@test.com', 'signature', 'accepted');

        $records = $this->service->getForSigner('target@test.com');

        $this->assertCount(1, $records);
        $this->assertEquals('target@test.com', $records->first()->signer_email);
    }

    #[Test]
    public function it_checks_accepted_consent(): void
    {
        $signable = EvidencePackage::factory()->create();

        // Initially no consents
        $hasConsent = $this->service->hasAcceptedConsent(
            $signable,
            'test@example.com',
            'signature'
        );
        $this->assertFalse($hasConsent);

        // Add signature consent
        $this->service->recordSignatureConsent($signable, 'test@example.com');

        $hasConsent = $this->service->hasAcceptedConsent(
            $signable,
            'test@example.com',
            'signature'
        );
        $this->assertTrue($hasConsent);
    }

    #[Test]
    public function it_gets_legal_text_for_consent_type(): void
    {
        $text = $this->service->getLegalText('signature', 'es');

        $this->assertNotEmpty($text);
        $this->assertStringContainsString('eIDAS', $text);
    }

    #[Test]
    public function it_gets_consent_version(): void
    {
        $version = $this->service->getConsentVersion('signature');

        $this->assertEquals('1.0', $version);
    }

    #[Test]
    public function it_generates_uuid_for_records(): void
    {
        $signable = EvidencePackage::factory()->create();

        $record = $this->service->recordConsent(
            $signable,
            'test@example.com',
            'signature',
            'accepted'
        );

        $this->assertNotEmpty($record->uuid);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            $record->uuid
        );
    }

    #[Test]
    public function it_stores_consent_in_specified_language(): void
    {
        $signable = EvidencePackage::factory()->create();

        $recordEs = $this->service->recordConsent(
            $signable,
            'test@example.com',
            'signature',
            'accepted',
            null,
            null,
            null,
            'es'
        );

        $recordEn = $this->service->recordConsent(
            $signable,
            'test2@example.com',
            'signature',
            'accepted',
            null,
            null,
            null,
            'en'
        );

        $this->assertEquals('es', $recordEs->legal_text_language);
        $this->assertEquals('en', $recordEn->legal_text_language);
    }

    #[Test]
    public function it_calculates_visible_duration_in_seconds(): void
    {
        $record = new ConsentRecord;
        $record->ui_visible_duration_ms = 5500;

        $this->assertEquals(5.5, $record->visible_duration_seconds);
    }

    #[Test]
    public function it_provides_consent_type_description(): void
    {
        $record = new ConsentRecord;
        $record->consent_type = 'signature';

        $this->assertEquals('Consentimiento de firma electrónica', $record->consent_type_description);
    }

    #[Test]
    public function it_returns_available_consent_types(): void
    {
        $types = $this->service->getAvailableConsentTypes();

        $this->assertContains('signature', $types);
        $this->assertContains('terms', $types);
        $this->assertContains('privacy', $types);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
