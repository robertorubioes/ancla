<?php

namespace Tests\Unit\Evidence;

use App\Models\DeviceFingerprint;
use App\Models\EvidencePackage;
use App\Models\Tenant;
use App\Services\Evidence\AuditTrailService;
use App\Services\Evidence\DeviceFingerprintService;
use App\Services\Evidence\HashingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DeviceFingerprintServiceTest extends TestCase
{
    use RefreshDatabase;

    private DeviceFingerprintService $service;

    private HashingService $hashingService;

    private AuditTrailService $auditTrailService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hashingService = new HashingService;
        $this->auditTrailService = Mockery::mock(AuditTrailService::class);
        $this->auditTrailService->shouldReceive('logEvent')->andReturn(null);

        $this->service = new DeviceFingerprintService(
            $this->hashingService,
            $this->auditTrailService
        );

        // Create and bind tenant
        $tenant = Tenant::factory()->create();
        app()->instance('tenant', $tenant);
    }

    #[Test]
    public function it_captures_fingerprint_from_request(): void
    {
        $request = Request::create('/sign', 'POST', [], [], [], [
            'HTTP_USER_AGENT' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        ]);

        $signable = EvidencePackage::factory()->create();

        $clientData = [
            'screen_width' => 1920,
            'screen_height' => 1080,
            'color_depth' => 24,
            'pixel_ratio' => 1.5,
            'timezone' => 'Europe/Madrid',
            'language' => 'es-ES',
            'platform' => 'Win32',
        ];

        $fingerprint = $this->service->capture($request, $signable, $clientData, 'test@example.com');

        $this->assertInstanceOf(DeviceFingerprint::class, $fingerprint);
        $this->assertEquals('test@example.com', $fingerprint->signer_email);
        $this->assertEquals(1920, $fingerprint->screen_width);
        $this->assertEquals(1080, $fingerprint->screen_height);
        $this->assertEquals('Europe/Madrid', $fingerprint->timezone);
        $this->assertNotEmpty($fingerprint->fingerprint_hash);
    }

    #[Test]
    public function it_calculates_consistent_fingerprint_hash(): void
    {
        $data = [
            'user_agent_raw' => 'Mozilla/5.0',
            'screen_width' => 1920,
            'screen_height' => 1080,
            'color_depth' => 24,
            'timezone' => 'Europe/Madrid',
            'language' => 'es-ES',
        ];

        $hash1 = $this->service->calculateFingerprintHash($data);
        $hash2 = $this->service->calculateFingerprintHash($data);

        $this->assertEquals($hash1, $hash2);
        $this->assertEquals(64, strlen($hash1));
    }

    #[Test]
    public function it_generates_different_hashes_for_different_data(): void
    {
        $data1 = [
            'user_agent_raw' => 'Mozilla/5.0',
            'screen_width' => 1920,
            'screen_height' => 1080,
        ];

        $data2 = [
            'user_agent_raw' => 'Mozilla/5.0',
            'screen_width' => 1366,
            'screen_height' => 768,
        ];

        $hash1 = $this->service->calculateFingerprintHash($data1);
        $hash2 = $this->service->calculateFingerprintHash($data2);

        $this->assertNotEquals($hash1, $hash2);
    }

    #[Test]
    public function it_detects_device_types_correctly(): void
    {
        // Desktop User-Agent
        $desktopRequest = Request::create('/sign', 'POST', [], [], [], [
            'HTTP_USER_AGENT' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        ]);

        // Mobile User-Agent
        $mobileRequest = Request::create('/sign', 'POST', [], [], [], [
            'HTTP_USER_AGENT' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 14_0 like Mac OS X) AppleWebKit/605.1.15',
        ]);

        $signable = EvidencePackage::factory()->create();

        $desktopFingerprint = $this->service->capture($desktopRequest, $signable, [], 'desktop@test.com');
        $mobileFingerprint = $this->service->capture($mobileRequest, $signable, [], 'mobile@test.com');

        $this->assertEquals('desktop', $desktopFingerprint->device_type);
        $this->assertEquals('mobile', $mobileFingerprint->device_type);
    }

    #[Test]
    public function it_stores_raw_client_data(): void
    {
        $request = Request::create('/sign', 'POST');

        $signable = EvidencePackage::factory()->create();
        $clientData = [
            'custom_field' => 'custom_value',
            'another_field' => 123,
        ];

        $fingerprint = $this->service->capture($request, $signable, $clientData);

        $this->assertEquals($clientData, $fingerprint->raw_data);
    }

    #[Test]
    public function it_generates_uuid_automatically(): void
    {
        $request = Request::create('/sign', 'POST');
        $signable = EvidencePackage::factory()->create();

        $fingerprint = $this->service->capture($request, $signable, []);

        $this->assertNotEmpty($fingerprint->uuid);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            $fingerprint->uuid
        );
    }

    #[Test]
    public function it_retrieves_fingerprints_for_signable(): void
    {
        $request = Request::create('/sign', 'POST');
        $signable = EvidencePackage::factory()->create();

        $this->service->capture($request, $signable, [], 'user1@test.com');
        $this->service->capture($request, $signable, [], 'user2@test.com');

        $fingerprints = $this->service->getForSignable($signable);

        $this->assertCount(2, $fingerprints);
    }

    #[Test]
    public function it_retrieves_signer_history(): void
    {
        $request = Request::create('/sign', 'POST');
        $signable = EvidencePackage::factory()->create();

        $this->service->capture($request, $signable, [], 'user1@test.com');
        $this->service->capture($request, $signable, [], 'user1@test.com');
        $this->service->capture($request, $signable, [], 'user2@test.com');

        $history = $this->service->getSignerHistory('user1@test.com');

        $this->assertCount(2, $history);
        $this->assertEquals('user1@test.com', $history->first()->signer_email);
    }

    #[Test]
    public function it_finds_fingerprint_by_hash(): void
    {
        $request = Request::create('/sign', 'POST');
        $signable = EvidencePackage::factory()->create();

        $fingerprint = $this->service->capture($request, $signable, [
            'screen_width' => 1920,
            'screen_height' => 1080,
        ]);

        $found = $this->service->findByHash($fingerprint->fingerprint_hash);

        $this->assertNotNull($found);
        $this->assertEquals($fingerprint->id, $found->id);
    }

    #[Test]
    public function it_checks_previous_session_match(): void
    {
        $request = Request::create('/sign', 'POST');
        $signable = EvidencePackage::factory()->create();

        $fingerprint = $this->service->capture($request, $signable, [
            'screen_width' => 1920,
            'screen_height' => 1080,
        ], 'test@example.com');

        $matches = $this->service->matchesPreviousSession(
            $fingerprint->fingerprint_hash,
            'test@example.com'
        );

        $this->assertTrue($matches);

        $noMatch = $this->service->matchesPreviousSession(
            $fingerprint->fingerprint_hash,
            'other@example.com'
        );

        $this->assertFalse($noMatch);
    }

    #[Test]
    public function it_handles_missing_client_data_gracefully(): void
    {
        $request = Request::create('/sign', 'POST');
        $signable = EvidencePackage::factory()->create();

        $fingerprint = $this->service->capture($request, $signable, []);

        $this->assertNull($fingerprint->screen_width);
        $this->assertNull($fingerprint->timezone);
        $this->assertNotEmpty($fingerprint->fingerprint_hash);
    }

    #[Test]
    public function it_provides_model_helper_methods(): void
    {
        $request = Request::create('/sign', 'POST', [], [], [], [
            'HTTP_USER_AGENT' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 14_0 like Mac OS X)',
        ]);
        $signable = EvidencePackage::factory()->create();

        $fingerprint = $this->service->capture($request, $signable, [
            'screen_width' => 375,
            'screen_height' => 812,
            'touch_support' => true,
        ]);

        $this->assertTrue($fingerprint->isMobile());
        $this->assertTrue($fingerprint->hasTouch());
        $this->assertEquals('375x812', $fingerprint->screen_resolution);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
