<?php

namespace Tests\Unit\Evidence;

use App\Models\EvidencePackage;
use App\Models\GeolocationRecord;
use App\Models\Tenant;
use App\Services\Evidence\AuditTrailService;
use App\Services\Evidence\GeolocationService;
use App\Services\Evidence\HashingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GeolocationServiceTest extends TestCase
{
    use RefreshDatabase;

    private GeolocationService $service;

    private AuditTrailService $auditTrailService;

    protected function setUp(): void
    {
        parent::setUp();

        $hashingService = new HashingService;
        $this->auditTrailService = Mockery::mock(AuditTrailService::class);
        $this->auditTrailService->shouldReceive('logEvent')->andReturn(null);

        $this->service = new GeolocationService($hashingService, $this->auditTrailService);

        // Create and bind tenant
        $tenant = Tenant::factory()->create();
        app()->instance('tenant', $tenant);

        // Fake HTTP requests
        Http::fake([
            'ipapi.co/*' => Http::response([
                'latitude' => 40.4168,
                'longitude' => -3.7038,
                'city' => 'Madrid',
                'region' => 'Community of Madrid',
                'country_code' => 'ES',
                'country_name' => 'Spain',
                'timezone' => 'Europe/Madrid',
                'org' => 'Telefonica Spain',
            ], 200),
        ]);
    }

    #[Test]
    public function it_captures_geolocation_from_gps_data(): void
    {
        $request = Request::create('/sign', 'POST');
        $signable = EvidencePackage::factory()->create();

        $gpsData = [
            'latitude' => 40.4168,
            'longitude' => -3.7038,
            'accuracy' => 10.5,
            'altitude' => 650.0,
        ];

        $record = $this->service->capture(
            $request,
            $signable,
            $gpsData,
            'granted',
            'test@example.com'
        );

        $this->assertInstanceOf(GeolocationRecord::class, $record);
        $this->assertEquals('gps', $record->capture_method);
        $this->assertEquals('granted', $record->permission_status);
        $this->assertEquals(40.4168, $record->latitude);
        $this->assertEquals(-3.7038, $record->longitude);
        $this->assertEquals(10.5, $record->accuracy_meters);
        $this->assertEquals('test@example.com', $record->signer_email);
    }

    #[Test]
    public function it_captures_geolocation_from_ip_fallback(): void
    {
        $request = Request::create('/sign', 'POST', [], [], [], [
            'REMOTE_ADDR' => '8.8.8.8',
        ]);
        $signable = EvidencePackage::factory()->create();

        $record = $this->service->capture(
            $request,
            $signable,
            null,
            'denied',
            'test@example.com'
        );

        $this->assertInstanceOf(GeolocationRecord::class, $record);
        $this->assertEquals('refused', $record->capture_method);
        $this->assertEquals('denied', $record->permission_status);
    }

    #[Test]
    public function it_records_unavailable_geolocation(): void
    {
        $request = Request::create('/sign', 'POST');
        $signable = EvidencePackage::factory()->create();

        $record = $this->service->capture(
            $request,
            $signable,
            null,
            'unavailable'
        );

        $this->assertEquals('unavailable', $record->capture_method);
        $this->assertEquals('unavailable', $record->permission_status);
    }

    #[Test]
    public function it_captures_ip_geolocation_data(): void
    {
        $request = Request::create('/sign', 'POST', [], [], [], [
            'REMOTE_ADDR' => '8.8.8.8',
        ]);
        $signable = EvidencePackage::factory()->create();

        $record = $this->service->capture(
            $request,
            $signable,
            null,
            'prompt'
        );

        // IP geolocation should be captured
        $this->assertEquals('Madrid', $record->ip_city);
        $this->assertEquals('ES', $record->ip_country);
    }

    #[Test]
    public function it_formats_address_correctly(): void
    {
        $request = Request::create('/sign', 'POST', [], [], [], [
            'REMOTE_ADDR' => '8.8.8.8',
        ]);
        $signable = EvidencePackage::factory()->create();

        $record = $this->service->capture($request, $signable, null, 'prompt');

        $this->assertEquals('Madrid, Community of Madrid, Spain', $record->formatted_address);
    }

    #[Test]
    public function it_retrieves_records_for_signable(): void
    {
        $request = Request::create('/sign', 'POST');
        $signable = EvidencePackage::factory()->create();

        $this->service->capture($request, $signable, [
            'latitude' => 40.4168,
            'longitude' => -3.7038,
        ], 'granted', 'user1@test.com');

        $this->service->capture($request, $signable, null, 'denied', 'user2@test.com');

        $records = $this->service->getForSignable($signable);

        $this->assertCount(2, $records);
    }

    #[Test]
    public function it_returns_local_info_for_private_ip(): void
    {
        $result = $this->service->getIpGeolocation('192.168.1.1');

        $this->assertEquals('Local', $result['city']);
        $this->assertEquals('Local Network', $result['country_name']);
        $this->assertEquals('Private Network', $result['isp']);
    }

    #[Test]
    public function it_generates_uuid_for_records(): void
    {
        $request = Request::create('/sign', 'POST');
        $signable = EvidencePackage::factory()->create();

        $record = $this->service->capture($request, $signable, null, 'denied');

        $this->assertNotEmpty($record->uuid);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            $record->uuid
        );
    }

    #[Test]
    public function it_stores_raw_gps_data(): void
    {
        $request = Request::create('/sign', 'POST');
        $signable = EvidencePackage::factory()->create();

        $gpsData = [
            'latitude' => 40.4168,
            'longitude' => -3.7038,
            'accuracy' => 10.5,
            'heading' => 90.0,
            'speed' => 5.0,
        ];

        $record = $this->service->capture($request, $signable, $gpsData, 'granted');

        $this->assertEquals($gpsData, $record->raw_gps_data);
    }

    #[Test]
    public function it_identifies_gps_capture_method(): void
    {
        $request = Request::create('/sign', 'POST');
        $signable = EvidencePackage::factory()->create();

        $record = $this->service->capture($request, $signable, [
            'latitude' => 40.4168,
            'longitude' => -3.7038,
        ], 'granted');

        $this->assertTrue($record->isGps());
        $this->assertFalse($record->isIpFallback());
        $this->assertFalse($record->wasRefused());
    }

    #[Test]
    public function it_identifies_ip_capture_method(): void
    {
        $request = Request::create('/sign', 'POST', [], [], [], [
            'REMOTE_ADDR' => '8.8.8.8',
        ]);
        $signable = EvidencePackage::factory()->create();

        $record = $this->service->capture($request, $signable, null, 'prompt');

        $this->assertTrue($record->isIpFallback());
        $this->assertFalse($record->isGps());
    }

    #[Test]
    public function it_provides_effective_coordinates(): void
    {
        // GPS record - use request with public IP
        $gpsRequest = Request::create('/sign', 'POST', [], [], [], [
            'REMOTE_ADDR' => '8.8.8.8',
        ]);
        $signable = EvidencePackage::factory()->create();

        $gpsRecord = $this->service->capture($gpsRequest, $signable, [
            'latitude' => 41.3851,
            'longitude' => 2.1734,
        ], 'granted');

        $this->assertEquals(41.3851, $gpsRecord->effective_latitude);
        $this->assertEquals(2.1734, $gpsRecord->effective_longitude);

        // IP fallback record - needs public IP to get IP geolocation data
        $ipRequest = Request::create('/sign', 'POST', [], [], [], [
            'REMOTE_ADDR' => '8.8.8.8',
        ]);

        $ipRecord = $this->service->capture($ipRequest, $signable, null, 'prompt');

        $this->assertEquals(40.4168, $ipRecord->effective_latitude);
        $this->assertEquals(-3.7038, $ipRecord->effective_longitude);
    }

    #[Test]
    public function it_determines_precision_level(): void
    {
        $request = Request::create('/sign', 'POST');
        $signable = EvidencePackage::factory()->create();

        // High precision GPS
        $highPrecision = $this->service->capture($request, $signable, [
            'latitude' => 40.4168,
            'longitude' => -3.7038,
            'accuracy' => 5,
        ], 'granted');
        $this->assertEquals('high', $highPrecision->precision_level);

        // Medium precision GPS
        $mediumPrecision = $this->service->capture($request, $signable, [
            'latitude' => 40.4168,
            'longitude' => -3.7038,
            'accuracy' => 50,
        ], 'granted');
        $this->assertEquals('medium', $mediumPrecision->precision_level);

        // IP fallback
        $ipFallback = $this->service->capture($request, $signable, null, 'prompt');
        $this->assertEquals('approximate', $ipFallback->precision_level);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
