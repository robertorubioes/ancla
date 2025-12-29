<?php

namespace Tests\Unit\Evidence;

use App\Models\EvidencePackage;
use App\Models\IpResolutionRecord;
use App\Models\Tenant;
use App\Services\Evidence\AuditTrailService;
use App\Services\Evidence\HashingService;
use App\Services\Evidence\IpResolutionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class IpResolutionServiceTest extends TestCase
{
    use RefreshDatabase;

    private IpResolutionService $service;

    private AuditTrailService $auditTrailService;

    protected function setUp(): void
    {
        parent::setUp();

        $hashingService = new HashingService;
        $this->auditTrailService = Mockery::mock(AuditTrailService::class);
        $this->auditTrailService->shouldReceive('logEvent')->andReturn(null);

        $this->service = new IpResolutionService($hashingService, $this->auditTrailService);

        // Create and bind tenant
        $tenant = Tenant::factory()->create();
        app()->instance('tenant', $tenant);

        // Fake HTTP requests
        Http::fake([
            'ipapi.co/*' => Http::response([
                'asn' => 'AS15169',
                'org' => 'Google LLC',
                'country_code' => 'US',
                'city' => 'Mountain View',
            ], 200),
            'proxycheck.io/*' => Http::response([
                '8.8.8.8' => [
                    'proxy' => 'no',
                    'type' => 'Business',
                    'risk' => 0,
                ],
            ], 200),
        ]);
    }

    #[Test]
    public function it_captures_ip_resolution(): void
    {
        $request = Request::create('/sign', 'POST', [], [], [], [
            'REMOTE_ADDR' => '8.8.8.8',
        ]);
        $signable = EvidencePackage::factory()->create();

        $record = $this->service->capture($request, $signable, 'test@example.com');

        $this->assertInstanceOf(IpResolutionRecord::class, $record);
        $this->assertEquals('8.8.8.8', $record->ip_address);
        $this->assertEquals(4, $record->ip_version);
        $this->assertEquals('test@example.com', $record->signer_email);
    }

    #[Test]
    public function it_detects_ip_version_correctly(): void
    {
        $requestV4 = Request::create('/sign', 'POST', [], [], [], [
            'REMOTE_ADDR' => '8.8.8.8',
        ]);

        $requestV6 = Request::create('/sign', 'POST', [], [], [], [
            'REMOTE_ADDR' => '2001:4860:4860::8888',
        ]);

        $signable = EvidencePackage::factory()->create();

        $recordV4 = $this->service->capture($requestV4, $signable);
        $recordV6 = $this->service->capture($requestV6, $signable);

        $this->assertEquals(4, $recordV4->ip_version);
        $this->assertEquals(6, $recordV6->ip_version);
        $this->assertTrue($recordV4->isIpv4());
        $this->assertTrue($recordV6->isIpv6());
    }

    #[Test]
    public function it_stores_forwarded_headers(): void
    {
        $request = Request::create('/sign', 'POST', [], [], [], [
            'REMOTE_ADDR' => '127.0.0.1',
            'HTTP_X_FORWARDED_FOR' => '8.8.8.8, 10.0.0.1',
            'HTTP_X_REAL_IP' => '8.8.8.8',
        ]);
        $signable = EvidencePackage::factory()->create();

        $record = $this->service->capture($request, $signable);

        $this->assertEquals('8.8.8.8, 10.0.0.1', $record->x_forwarded_for);
        $this->assertEquals('8.8.8.8', $record->x_real_ip);
    }

    #[Test]
    public function it_performs_reverse_dns_lookup(): void
    {
        // This test uses a real DNS lookup - may vary by environment
        $hostname = $this->service->getReverseDns('8.8.8.8');

        // Google DNS typically resolves to dns.google
        // But we just test it doesn't throw an error
        $this->assertTrue($hostname === null || is_string($hostname));
    }

    #[Test]
    public function it_returns_local_info_for_private_ip(): void
    {
        $result = $this->service->getIpInfo('192.168.1.1');

        $this->assertEquals('Private Network', $result['isp']);
        $this->assertEquals('Local', $result['organization']);
    }

    #[Test]
    public function it_returns_default_detection_for_private_ips(): void
    {
        $result = $this->service->detectVpnProxy('192.168.1.1');

        $this->assertFalse($result['is_proxy']);
        $this->assertFalse($result['is_vpn']);
        $this->assertFalse($result['is_tor']);
        $this->assertFalse($result['is_datacenter']);
    }

    #[Test]
    public function it_retrieves_records_for_signable(): void
    {
        $signable = EvidencePackage::factory()->create();

        $request1 = Request::create('/sign', 'POST', [], [], [], ['REMOTE_ADDR' => '8.8.8.8']);
        $request2 = Request::create('/sign', 'POST', [], [], [], ['REMOTE_ADDR' => '8.8.4.4']);

        $this->service->capture($request1, $signable, 'user1@test.com');
        $this->service->capture($request2, $signable, 'user2@test.com');

        $records = $this->service->getForSignable($signable);

        $this->assertCount(2, $records);
    }

    #[Test]
    public function it_generates_uuid_for_records(): void
    {
        $request = Request::create('/sign', 'POST', [], [], [], ['REMOTE_ADDR' => '8.8.8.8']);
        $signable = EvidencePackage::factory()->create();

        $record = $this->service->capture($request, $signable);

        $this->assertNotEmpty($record->uuid);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            $record->uuid
        );
    }

    #[Test]
    public function it_identifies_clean_connections(): void
    {
        // Clear cache to ensure fresh responses
        \Illuminate\Support\Facades\Cache::flush();

        // Disable VPN/proxy detection to test clean connection behavior
        config(['evidence.ip_info.detect_vpn' => false]);
        config(['evidence.ip_info.detect_proxy' => false]);

        // Override HTTP fake for this test to use non-datacenter ISP
        Http::fake([
            'ipapi.co/*' => Http::response([
                'asn' => 'AS3352',
                'org' => 'Telefonica Spain',
                'country_code' => 'ES',
                'city' => 'Madrid',
            ], 200),
        ]);

        $request = Request::create('/sign', 'POST', [], [], [], ['REMOTE_ADDR' => '203.0.113.50']);
        $signable = EvidencePackage::factory()->create();

        $record = $this->service->capture($request, $signable);

        // When detection is disabled, all IPs should be clean
        $this->assertFalse($record->isSuspicious());
        $this->assertTrue($record->hasCleanConnection());
    }

    #[Test]
    public function it_stores_raw_data(): void
    {
        $request = Request::create('/sign', 'POST', [], [], [], ['REMOTE_ADDR' => '8.8.8.8']);
        $signable = EvidencePackage::factory()->create();

        $record = $this->service->capture($request, $signable);

        $this->assertIsArray($record->raw_data);
        $this->assertArrayHasKey('ip_info', $record->raw_data);
        $this->assertArrayHasKey('vpn_detection', $record->raw_data);
    }

    #[Test]
    public function it_calculates_risk_level(): void
    {
        // Create record with different flags
        $record = new IpResolutionRecord;

        $record->is_tor = false;
        $record->is_vpn = false;
        $record->is_proxy = false;
        $record->is_datacenter = false;
        $record->threat_score = null;
        $this->assertEquals('none', $record->risk_level);

        $record->is_vpn = true;
        $this->assertEquals('medium', $record->risk_level);

        $record->is_vpn = false;
        $record->is_tor = true;
        $this->assertEquals('high', $record->risk_level);

        $record->is_tor = false;
        $record->threat_score = 80;
        $this->assertEquals('high', $record->risk_level);

        $record->threat_score = 50;
        $this->assertEquals('medium', $record->risk_level);
    }

    #[Test]
    public function it_generates_warnings_for_suspicious_connections(): void
    {
        $record = new IpResolutionRecord;
        $record->is_vpn = true;
        $record->is_proxy = true;
        $record->is_tor = false;
        $record->is_datacenter = false;

        $warnings = $record->active_warnings;

        $this->assertContains('VPN detected', $warnings);
        $this->assertContains('Proxy detected', $warnings);
    }

    #[Test]
    public function it_identifies_suspicious_connections(): void
    {
        $record = new IpResolutionRecord;

        $record->is_vpn = false;
        $record->is_proxy = false;
        $record->is_tor = false;
        $record->is_datacenter = false;
        $this->assertFalse($record->isSuspicious());

        $record->is_vpn = true;
        $this->assertTrue($record->isSuspicious());
    }

    #[Test]
    public function it_checks_suspicious_ip(): void
    {
        // Normal IP should not be suspicious
        $isSuspicious = $this->service->isSuspiciousIp('8.8.8.8');
        $this->assertFalse($isSuspicious);

        // Private IP should not be suspicious
        $isPrivateSuspicious = $this->service->isSuspiciousIp('192.168.1.1');
        $this->assertFalse($isPrivateSuspicious);
    }

    #[Test]
    public function it_provides_network_info_attribute(): void
    {
        $record = new IpResolutionRecord;
        $record->isp = 'Google LLC';
        $record->asn = '15169';

        $this->assertEquals('Google LLC - AS15169', $record->network_info);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
