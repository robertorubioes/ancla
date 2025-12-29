<?php

namespace Tests\Unit\Evidence;

use App\Models\EvidenceDossier;
use App\Models\EvidencePackage;
use App\Models\Tenant;
use App\Models\TsaToken;
use App\Services\Evidence\AuditTrailService;
use App\Services\Evidence\ChainVerificationResult;
use App\Services\Evidence\EvidenceDossierService;
use App\Services\Evidence\HashingService;
use App\Services\Evidence\TsaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EvidenceDossierServiceTest extends TestCase
{
    use RefreshDatabase;

    private EvidenceDossierService $service;

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
        $this->auditTrailService->shouldReceive('verifyChain')->andReturn(
            new ChainVerificationResult(true, 0, [])
        );

        $this->service = new EvidenceDossierService(
            $this->hashingService,
            $this->tsaService,
            $this->auditTrailService
        );

        // Create and bind tenant
        $tenant = Tenant::factory()->create();
        app()->instance('tenant', $tenant);
    }

    #[Test]
    public function it_finds_dossier_by_verification_code(): void
    {
        $dossier = EvidenceDossier::factory()->create([
            'verification_code' => 'TEST-CODE-1234',
        ]);

        $found = EvidenceDossier::byVerificationCode('TEST-CODE-1234')->first();

        $this->assertNotNull($found);
        $this->assertEquals($dossier->id, $found->id);
    }

    #[Test]
    public function it_returns_null_for_invalid_verification_code(): void
    {
        $found = EvidenceDossier::byVerificationCode('INVALID-CODE')->first();

        $this->assertNull($found);
    }

    #[Test]
    public function it_retrieves_dossiers_for_signable(): void
    {
        $signable = EvidencePackage::factory()->create();

        EvidenceDossier::factory()->count(2)->create([
            'signable_type' => get_class($signable),
            'signable_id' => $signable->id,
        ]);

        $dossiers = $this->service->getForSignable($signable);

        $this->assertCount(2, $dossiers);
    }

    #[Test]
    public function it_gets_latest_dossier_for_signable(): void
    {
        $signable = EvidencePackage::factory()->create();

        $older = EvidenceDossier::factory()->create([
            'signable_type' => get_class($signable),
            'signable_id' => $signable->id,
            'generated_at' => now()->subDay(),
        ]);

        $newer = EvidenceDossier::factory()->create([
            'signable_type' => get_class($signable),
            'signable_id' => $signable->id,
            'generated_at' => now(),
        ]);

        $latest = $this->service->getLatest($signable);

        $this->assertEquals($newer->id, $latest->id);
    }

    #[Test]
    public function it_verifies_valid_dossier(): void
    {
        $content = 'Test PDF content';
        $path = 'evidence-dossiers/test.pdf';

        Storage::disk('local')->put($path, $content);

        $dossier = EvidenceDossier::factory()->create([
            'file_path' => $path,
            'file_hash' => $this->hashingService->hashContent($content),
            'platform_signature' => null,
        ]);

        $result = $this->service->verify($dossier->verification_code);

        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['errors']);
    }

    #[Test]
    public function it_detects_tampered_dossier(): void
    {
        $originalContent = 'Original PDF content';
        $tamperedContent = 'Tampered PDF content';
        $path = 'evidence-dossiers/test.pdf';

        // Store tampered content
        Storage::disk('local')->put($path, $tamperedContent);

        // But record has hash of original
        $dossier = EvidenceDossier::factory()->create([
            'file_path' => $path,
            'file_hash' => $this->hashingService->hashContent($originalContent),
        ]);

        $result = $this->service->verify($dossier->verification_code);

        $this->assertFalse($result['valid']);
        $this->assertContains('File hash mismatch - document may have been altered', $result['errors']);
    }

    #[Test]
    public function it_detects_missing_dossier_file(): void
    {
        $dossier = EvidenceDossier::factory()->create([
            'file_path' => 'evidence-dossiers/nonexistent.pdf',
        ]);

        $result = $this->service->verify($dossier->verification_code);

        $this->assertFalse($result['valid']);
        $this->assertContains('Dossier file not found', $result['errors']);
    }

    #[Test]
    public function it_records_download(): void
    {
        $content = 'Test PDF content';
        $path = 'evidence-dossiers/test.pdf';

        Storage::disk('local')->put($path, $content);

        $dossier = EvidenceDossier::factory()->create([
            'file_path' => $path,
            'file_name' => 'test.pdf',
            'download_count' => 0,
        ]);

        $dossier->recordDownload();
        $dossier->refresh();

        $this->assertEquals(1, $dossier->download_count);
        $this->assertNotNull($dossier->last_downloaded_at);
    }

    #[Test]
    public function it_checks_if_dossier_is_signed(): void
    {
        $unsigned = new EvidenceDossier;
        $unsigned->platform_signature = null;

        $signed = new EvidenceDossier;
        $signed->platform_signature = 'abc123signature';

        $this->assertFalse($unsigned->isSigned());
        $this->assertTrue($signed->isSigned());
    }

    #[Test]
    public function it_checks_if_dossier_has_tsa(): void
    {
        $withoutTsa = new EvidenceDossier;
        $withoutTsa->tsa_token_id = null;

        $withTsa = new EvidenceDossier;
        $withTsa->tsa_token_id = 1;

        $this->assertFalse($withoutTsa->hasTsa());
        $this->assertTrue($withTsa->hasTsa());
    }

    #[Test]
    public function it_formats_file_size_for_humans(): void
    {
        $dossier = new EvidenceDossier;

        $dossier->file_size = 500;
        $this->assertEquals('500 bytes', $dossier->formatted_size);

        $dossier->file_size = 1024;
        $this->assertEquals('1.00 KB', $dossier->formatted_size);

        $dossier->file_size = 1048576;
        $this->assertEquals('1.00 MB', $dossier->formatted_size);

        $dossier->file_size = 1572864; // 1.5 MB
        $this->assertEquals('1.50 MB', $dossier->formatted_size);
    }

    #[Test]
    public function it_provides_included_content_list(): void
    {
        $dossier = new EvidenceDossier;
        $dossier->includes_document = true;
        $dossier->includes_audit_trail = true;
        $dossier->includes_device_info = false;
        $dossier->includes_geolocation = true;
        $dossier->includes_ip_info = false;
        $dossier->includes_consents = true;
        $dossier->includes_tsa_tokens = true;

        $included = $dossier->included_content;

        $this->assertContains('Documento original', $included);
        $this->assertContains('Trail de auditoría', $included);
        $this->assertNotContains('Información de dispositivos', $included);
        $this->assertContains('Geolocalización', $included);
        $this->assertNotContains('Información de red', $included);
        $this->assertContains('Consentimientos', $included);
        $this->assertContains('Tokens TSA', $included);
    }

    #[Test]
    public function it_provides_stats_summary(): void
    {
        $dossier = new EvidenceDossier;
        $dossier->page_count = 10;
        $dossier->audit_entries_count = 15;
        $dossier->devices_count = 3;
        $dossier->geolocations_count = 2;
        $dossier->consents_count = 5;
        $dossier->download_count = 7;

        $stats = $dossier->stats_summary;

        $this->assertEquals(10, $stats['pages']);
        $this->assertEquals(15, $stats['audit_entries']);
        $this->assertEquals(3, $stats['devices']);
        $this->assertEquals(2, $stats['geolocations']);
        $this->assertEquals(5, $stats['consents']);
        $this->assertEquals(7, $stats['downloads']);
    }

    #[Test]
    public function it_generates_uuid_automatically(): void
    {
        $dossier = EvidenceDossier::factory()->create();

        $this->assertNotEmpty($dossier->uuid);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            $dossier->uuid
        );
    }

    #[Test]
    public function it_provides_dossier_type_description(): void
    {
        $dossier = new EvidenceDossier;

        $dossier->dossier_type = 'full_evidence';
        $this->assertEquals('Evidencias completas', $dossier->dossier_type_description);

        $dossier->dossier_type = 'audit_trail';
        $this->assertEquals('Trail de auditoría', $dossier->dossier_type_description);

        $dossier->dossier_type = 'legal_proof';
        $this->assertEquals('Prueba legal', $dossier->dossier_type_description);

        $dossier->dossier_type = 'executive_summary';
        $this->assertEquals('Resumen ejecutivo', $dossier->dossier_type_description);
    }

    #[Test]
    public function it_verifies_platform_signature(): void
    {
        $content = 'Test PDF content';
        $path = 'evidence-dossiers/test.pdf';
        $fileHash = $this->hashingService->hashContent($content);

        Storage::disk('local')->put($path, $content);

        // Create dossier with valid signature
        config(['evidence.dossier.platform_signing_key' => 'test-key']);
        $validSignature = hash_hmac('sha256', $fileHash, 'test-key');

        $dossier = EvidenceDossier::factory()->create([
            'file_path' => $path,
            'file_hash' => $fileHash,
            'platform_signature' => $validSignature,
        ]);

        $isValid = $this->service->verifySignature($dossier);
        $this->assertTrue($isValid);
    }

    #[Test]
    public function it_detects_invalid_platform_signature(): void
    {
        $content = 'Test PDF content';
        $path = 'evidence-dossiers/test.pdf';
        $fileHash = $this->hashingService->hashContent($content);

        Storage::disk('local')->put($path, $content);

        config(['evidence.dossier.platform_signing_key' => 'test-key']);

        $dossier = EvidenceDossier::factory()->create([
            'file_path' => $path,
            'file_hash' => $fileHash,
            'platform_signature' => 'invalid-signature',
        ]);

        $isValid = $this->service->verifySignature($dossier);
        $this->assertFalse($isValid);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
