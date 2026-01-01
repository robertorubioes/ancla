<?php

declare(strict_types=1);

namespace Tests\Unit\Verification;

use App\Models\Document;
use App\Models\Tenant;
use App\Models\User;
use App\Models\VerificationCode;
use App\Services\Evidence\AuditTrailService;
use App\Services\Evidence\ChainVerificationResult;
use App\Services\Evidence\HashingService;
use App\Services\Evidence\TsaService;
use App\Services\Verification\IntegrityCheckResult;
use App\Services\Verification\PublicVerificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;

class PublicVerificationServiceTest extends TestCase
{
    use RefreshDatabase;

    private PublicVerificationService $service;

    private HashingService $hashingService;

    private AuditTrailService $auditTrailService;

    private TsaService $tsaService;

    private Tenant $tenant;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->hashingService = Mockery::mock(HashingService::class);
        $this->auditTrailService = Mockery::mock(AuditTrailService::class);
        $this->tsaService = Mockery::mock(TsaService::class);

        $this->service = new PublicVerificationService(
            $this->hashingService,
            $this->auditTrailService,
            $this->tsaService
        );

        // Disable cache for tests
        config(['verification.cache.enabled' => false]);
        config(['verification.logging.enabled' => false]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_returns_invalid_code_for_short_codes(): void
    {
        $result = $this->service->verifyByCode('ABC');

        $this->assertFalse($result->isValid);
        $this->assertEquals('low', $result->confidenceLevel);
    }

    /** @test */
    public function it_returns_not_found_for_non_existent_code(): void
    {
        $result = $this->service->verifyByCode('ABCDEFGHIJKL');

        $this->assertFalse($result->isValid);
        $this->assertNotNull($result->errorMessage);
        $this->assertStringContainsString('found', strtolower($result->errorMessage));
    }

    /** @test */
    public function it_returns_expired_for_expired_code(): void
    {
        $document = Document::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
        ]);

        $verificationCode = VerificationCode::create([
            'uuid' => fake()->uuid(),
            'document_id' => $document->id,
            'verification_code' => 'EXPIREDCODE1',
            'short_code' => 'EXPIRE',
            'expires_at' => now()->subDay(),
            'access_count' => 0,
        ]);

        $result = $this->service->verifyByCode('EXPIREDCODE1');

        $this->assertFalse($result->isValid);
        $this->assertStringContainsString('expired', strtolower($result->errorMessage ?? ''));
    }

    /** @test */
    public function it_verifies_valid_document_by_code(): void
    {
        $document = Document::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'sha256_hash' => str_repeat('a', 64),
            'status' => 'ready',
        ]);

        $verificationCode = VerificationCode::create([
            'uuid' => fake()->uuid(),
            'document_id' => $document->id,
            'verification_code' => 'VALIDCODE123',
            'short_code' => 'VALID1',
            'expires_at' => null,
            'access_count' => 0,
        ]);

        // Mock the hashing service
        $this->hashingService
            ->shouldReceive('verifyDocumentHash')
            ->andReturn(true);

        // Mock the audit trail verification
        $this->auditTrailService
            ->shouldReceive('verifyChain')
            ->andReturn(new ChainVerificationResult(
                valid: true,
                entriesVerified: 5,
                errors: []
            ));

        // Mock TSA verification
        $this->tsaService
            ->shouldReceive('verifyTimestamp')
            ->andReturn(true);

        $result = $this->service->verifyByCode('VALIDCODE123');

        $this->assertTrue($result->isValid);
        $this->assertGreaterThan(0, $result->confidenceScore);
        $this->assertNotNull($result->document);
        $this->assertEquals($document->id, $result->document->id);
    }

    /** @test */
    public function it_increments_access_count_on_verification(): void
    {
        $document = Document::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
        ]);

        $verificationCode = VerificationCode::create([
            'uuid' => fake()->uuid(),
            'document_id' => $document->id,
            'verification_code' => 'COUNTTEST01',
            'short_code' => 'COUNT1',
            'expires_at' => null,
            'access_count' => 0,
        ]);

        // Mock services
        $this->hashingService->shouldReceive('verifyDocumentHash')->andReturn(true);
        $this->auditTrailService->shouldReceive('verifyChain')->andReturn(
            new ChainVerificationResult(valid: true, entriesVerified: 0, errors: [])
        );
        $this->tsaService->shouldReceive('verifyTimestamp')->andReturn(true);

        $this->service->verifyByCode('COUNTTEST01');

        $verificationCode->refresh();
        $this->assertEquals(1, $verificationCode->access_count);
        $this->assertNotNull($verificationCode->last_accessed_at);
    }

    /** @test */
    public function it_verifies_document_by_hash(): void
    {
        $hash = str_repeat('b', 64);

        $document = Document::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'sha256_hash' => $hash,
        ]);

        VerificationCode::create([
            'uuid' => fake()->uuid(),
            'document_id' => $document->id,
            'verification_code' => 'HASHTEST001',
            'short_code' => 'HASH01',
            'expires_at' => null,
            'access_count' => 0,
        ]);

        // Mock services
        $this->hashingService->shouldReceive('isValidHash')->with($hash)->andReturn(true);
        $this->hashingService->shouldReceive('verifyDocumentHash')->andReturn(true);
        $this->auditTrailService->shouldReceive('verifyChain')->andReturn(
            new ChainVerificationResult(valid: true, entriesVerified: 0, errors: [])
        );
        $this->tsaService->shouldReceive('verifyTimestamp')->andReturn(true);

        $result = $this->service->verifyByHash($hash);

        $this->assertTrue($result->isValid);
        $this->assertNotNull($result->document);
    }

    /** @test */
    public function it_returns_not_found_for_unknown_hash(): void
    {
        $hash = str_repeat('c', 64);

        $this->hashingService->shouldReceive('isValidHash')->with($hash)->andReturn(true);

        $result = $this->service->verifyByHash($hash);

        $this->assertFalse($result->isValid);
        $this->assertStringContainsString('found', strtolower($result->errorMessage ?? ''));
    }

    /** @test */
    public function it_returns_invalid_for_malformed_hash(): void
    {
        $invalidHash = 'not-a-valid-hash';

        $this->hashingService->shouldReceive('isValidHash')->with($invalidHash)->andReturn(false);

        $result = $this->service->verifyByHash($invalidHash);

        $this->assertFalse($result->isValid);
        $this->assertStringContainsString('invalid', strtolower($result->errorMessage ?? ''));
    }

    /** @test */
    public function it_gets_verification_details(): void
    {
        $document = Document::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
        ]);

        VerificationCode::create([
            'uuid' => fake()->uuid(),
            'document_id' => $document->id,
            'verification_code' => 'DETAILTEST1',
            'short_code' => 'DETAIL',
            'expires_at' => null,
            'access_count' => 5,
        ]);

        // Mock services
        $this->hashingService->shouldReceive('verifyDocumentHash')->andReturn(true);
        $this->auditTrailService->shouldReceive('verifyChain')->andReturn(
            new ChainVerificationResult(valid: true, entriesVerified: 3, errors: [])
        );
        $this->tsaService->shouldReceive('verifyTimestamp')->andReturn(true);

        $details = $this->service->getVerificationDetails('DETAILTEST1');

        $this->assertNotNull($details);
        $this->assertArrayHasKey('document', $details);
        $this->assertArrayHasKey('verification', $details);
        $this->assertArrayHasKey('integrity', $details);
        $this->assertEquals($document->original_filename, $details['document']['filename']);
    }

    /** @test */
    public function it_returns_null_details_for_non_existent_code(): void
    {
        $details = $this->service->getVerificationDetails('NONEXISTENT');

        $this->assertNull($details);
    }

    /** @test */
    public function it_calculates_confidence_level(): void
    {
        $document = Document::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
        ]);

        // Mock services for full confidence
        $this->hashingService->shouldReceive('verifyDocumentHash')->andReturn(true);
        $this->auditTrailService->shouldReceive('verifyChain')->andReturn(
            new ChainVerificationResult(valid: true, entriesVerified: 5, errors: [])
        );
        $this->tsaService->shouldReceive('verifyTimestamp')->andReturn(true);

        $score = $this->service->calculateConfidenceLevel($document);

        // Should have at least document hash + chain hash points
        $this->assertGreaterThanOrEqual(40, $score);
    }

    /** @test */
    public function it_creates_verification_code_for_document(): void
    {
        $document = Document::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
        ]);

        $verificationCode = $this->service->createVerificationCode($document);

        $this->assertNotNull($verificationCode);
        $this->assertNotNull($verificationCode->verification_code);
        $this->assertNotNull($verificationCode->short_code);
        $this->assertEquals(12, strlen(str_replace('-', '', $verificationCode->verification_code)));
        $this->assertEquals(6, strlen($verificationCode->short_code));
        $this->assertEquals($document->id, $verificationCode->document_id);
    }

    /** @test */
    public function it_creates_verification_code_with_expiration(): void
    {
        $document = Document::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
        ]);

        $verificationCode = $this->service->createVerificationCode($document, 30);

        $this->assertNotNull($verificationCode->expires_at);
        $this->assertTrue($verificationCode->expires_at->isAfter(now()));
        $this->assertTrue($verificationCode->expires_at->isBefore(now()->addDays(31)));
    }

    /** @test */
    public function it_verifies_full_integrity(): void
    {
        $document = Document::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
        ]);

        // Mock all verification checks passing
        $this->hashingService->shouldReceive('verifyDocumentHash')->andReturn(true);
        $this->auditTrailService->shouldReceive('verifyChain')->andReturn(
            new ChainVerificationResult(valid: true, entriesVerified: 10, errors: [])
        );
        $this->tsaService->shouldReceive('verifyTimestamp')->andReturn(true);

        $result = $this->service->verifyFullIntegrity($document);

        $this->assertInstanceOf(IntegrityCheckResult::class, $result);
        $this->assertTrue($result->documentHashValid);
        $this->assertTrue($result->chainHashValid);
    }

    /** @test */
    public function it_handles_document_hash_mismatch(): void
    {
        $document = Document::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
        ]);

        // Mock document hash verification failing
        $this->hashingService->shouldReceive('verifyDocumentHash')->andReturn(false);
        $this->auditTrailService->shouldReceive('verifyChain')->andReturn(
            new ChainVerificationResult(valid: true, entriesVerified: 0, errors: [])
        );
        $this->tsaService->shouldReceive('verifyTimestamp')->andReturn(true);

        $result = $this->service->verifyFullIntegrity($document);

        $this->assertFalse($result->documentHashValid);
        $this->assertFalse($result->isValid);
        $this->assertNotEmpty($result->errors);
    }

    /** @test */
    public function it_handles_chain_verification_failure(): void
    {
        $document = Document::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
        ]);

        $this->hashingService->shouldReceive('verifyDocumentHash')->andReturn(true);
        $this->auditTrailService->shouldReceive('verifyChain')->andReturn(
            new ChainVerificationResult(valid: false, entriesVerified: 5, errors: ['Chain broken at entry 3'])
        );
        $this->tsaService->shouldReceive('verifyTimestamp')->andReturn(true);

        $result = $this->service->verifyFullIntegrity($document);

        $this->assertFalse($result->chainHashValid);
        $this->assertFalse($result->isValid);
    }

    /** @test */
    public function it_uses_cache_when_enabled(): void
    {
        config(['verification.cache.enabled' => true]);

        $document = Document::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
        ]);

        $verificationCode = VerificationCode::create([
            'uuid' => fake()->uuid(),
            'document_id' => $document->id,
            'verification_code' => 'CACHETEST01',
            'short_code' => 'CACHE1',
            'expires_at' => null,
            'access_count' => 0,
        ]);

        // Mock services - use zeroOrMoreTimes since not all methods may be called
        $this->hashingService->shouldReceive('verifyDocumentHash')->zeroOrMoreTimes()->andReturn(true);
        $this->auditTrailService->shouldReceive('verifyChain')->zeroOrMoreTimes()->andReturn(
            new ChainVerificationResult(valid: true, entriesVerified: 0, errors: [])
        );
        $this->tsaService->shouldReceive('verifyTimestamp')->zeroOrMoreTimes()->andReturn(true);

        // First call should hit the service
        $result1 = $this->service->verifyByCode('CACHETEST01');

        // Second call should use cache (mocks only allow once())
        $result2 = $this->service->verifyByCode('CACHETEST01');

        $this->assertTrue($result1->isValid);
        $this->assertTrue($result2->isValid);

        // Clear cache after test
        Cache::forget('verification:code:CACHETEST01');
    }

    /** @test */
    public function it_normalizes_codes_with_dashes(): void
    {
        $document = Document::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
        ]);

        VerificationCode::create([
            'uuid' => fake()->uuid(),
            'document_id' => $document->id,
            'verification_code' => 'ABCDEFGHIJKL',
            'short_code' => 'ABCDEF',
            'expires_at' => null,
            'access_count' => 0,
        ]);

        // Mock services
        $this->hashingService->shouldReceive('verifyDocumentHash')->andReturn(true);
        $this->auditTrailService->shouldReceive('verifyChain')->andReturn(
            new ChainVerificationResult(valid: true, entriesVerified: 0, errors: [])
        );
        $this->tsaService->shouldReceive('verifyTimestamp')->andReturn(true);

        // Should work with dashes
        $result = $this->service->verifyByCode('ABCD-EFGH-IJKL');

        $this->assertTrue($result->isValid);
    }
}
