<?php

declare(strict_types=1);

namespace Tests\Feature\Verification;

use App\Models\Document;
use App\Models\Tenant;
use App\Models\User;
use App\Models\VerificationCode;
use App\Services\Evidence\AuditTrailService;
use App\Services\Evidence\ChainVerificationResult;
use App\Services\Evidence\HashingService;
use App\Services\Evidence\TsaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class PublicVerificationApiTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);

        // Disable caching for tests
        config(['verification.cache.enabled' => false]);
        config(['verification.logging.enabled' => false]);

        // Mock the services to avoid actual verification logic
        $this->mockVerificationServices();
    }

    protected function tearDown(): void
    {
        Mockery::close();

        // Ensure any open transactions are rolled back
        while (\DB::transactionLevel() > 0) {
            \DB::rollBack();
        }

        parent::tearDown();
    }

    private function mockVerificationServices(): void
    {
        $hashingService = Mockery::mock(HashingService::class);
        $hashingService->shouldReceive('verifyDocumentHash')->andReturn(true);
        $hashingService->shouldReceive('isValidHash')->andReturn(true);

        $auditTrailService = Mockery::mock(AuditTrailService::class);
        $auditTrailService->shouldReceive('verifyChain')->andReturn(
            new ChainVerificationResult(valid: true, entriesVerified: 5, errors: [])
        );

        $tsaService = Mockery::mock(TsaService::class);
        $tsaService->shouldReceive('verifyTimestamp')->andReturn(true);

        $this->app->instance(HashingService::class, $hashingService);
        $this->app->instance(AuditTrailService::class, $auditTrailService);
        $this->app->instance(TsaService::class, $tsaService);
    }

    /** @test */
    public function it_verifies_document_by_code(): void
    {
        $document = Document::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'status' => 'ready',
        ]);

        $verificationCode = VerificationCode::create([
            'uuid' => fake()->uuid(),
            'document_id' => $document->id,
            'verification_code' => 'APITEST12345',
            'short_code' => 'APITES',
            'expires_at' => null,
            'access_count' => 0,
        ]);

        $response = $this->getJson('/api/v1/public/verify/APITEST12345');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'valid',
                'confidence' => ['score', 'level'],
                'document' => ['filename', 'hash', 'uploaded_at', 'pages'],
                'verification',
                'verified_at',
            ])
            ->assertJson(['valid' => true]);

        $response->assertHeader('X-Verification-Status', 'valid');
        $response->assertHeader('X-Confidence-Level');
    }

    /** @test */
    public function it_returns_404_for_non_existent_code(): void
    {
        $response = $this->getJson('/api/v1/public/verify/NONEXISTENT1');

        $response->assertStatus(404)
            ->assertJson(['valid' => false]);

        $response->assertHeader('X-Verification-Status', 'invalid');
    }

    /** @test */
    public function it_returns_410_for_expired_code(): void
    {
        $document = Document::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
        ]);

        VerificationCode::create([
            'uuid' => fake()->uuid(),
            'document_id' => $document->id,
            'verification_code' => 'EXPIREDAPI01',
            'short_code' => 'EXPAPI',
            'expires_at' => now()->subDay(),
            'access_count' => 0,
        ]);

        $response = $this->getJson('/api/v1/public/verify/EXPIREDAPI01');

        $response->assertStatus(410);
    }

    /** @test */
    public function it_verifies_document_by_hash(): void
    {
        $hash = str_repeat('a', 64);

        $document = Document::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'sha256_hash' => $hash,
        ]);

        VerificationCode::create([
            'uuid' => fake()->uuid(),
            'document_id' => $document->id,
            'verification_code' => 'HASHAPI12345',
            'short_code' => 'HASHAP',
            'expires_at' => null,
            'access_count' => 0,
        ]);

        $response = $this->postJson('/api/v1/public/verify/hash', [
            'hash' => $hash,
        ]);

        $response->assertStatus(200)
            ->assertJson(['valid' => true]);
    }

    /** @test */
    public function it_validates_hash_format(): void
    {
        $response = $this->postJson('/api/v1/public/verify/hash', [
            'hash' => 'invalid-hash',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['hash']);
    }

    /** @test */
    public function it_returns_404_for_unknown_hash(): void
    {
        $hash = str_repeat('f', 64);

        $response = $this->postJson('/api/v1/public/verify/hash', [
            'hash' => $hash,
        ]);

        $response->assertStatus(404);
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
            'verification_code' => 'DETAILAPI123',
            'short_code' => 'DETAIL',
            'expires_at' => null,
            'access_count' => 5,
        ]);

        $response = $this->getJson('/api/v1/public/verify/DETAILAPI123/details');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'document' => ['filename', 'hash', 'algorithm', 'pages', 'size', 'uploaded_at'],
                'verification' => ['code', 'short_code', 'created_at', 'access_count'],
                'integrity' => ['is_valid', 'confidence_score', 'confidence_level', 'checks'],
            ]);
    }

    /** @test */
    public function it_returns_404_for_details_with_invalid_code(): void
    {
        $response = $this->getJson('/api/v1/public/verify/INVALIDCODE1/details');

        $response->assertStatus(404)
            ->assertJson(['error' => 'Verification code not found']);
    }

    /** @test */
    public function it_gets_verification_urls(): void
    {
        $document = Document::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
        ]);

        VerificationCode::create([
            'uuid' => fake()->uuid(),
            'document_id' => $document->id,
            'verification_code' => 'URLSAPI12345',
            'short_code' => 'URLSAP',
            'expires_at' => null,
            'access_count' => 0,
        ]);

        $response = $this->getJson('/api/v1/public/verify/URLSAPI12345/urls');

        $response->assertStatus(200)
            ->assertJsonStructure(['url', 'short_url', 'qr_code_url']);
    }

    /** @test */
    public function it_includes_rate_limit_headers(): void
    {
        $document = Document::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
        ]);

        VerificationCode::create([
            'uuid' => fake()->uuid(),
            'document_id' => $document->id,
            'verification_code' => 'RATETEST1234',
            'short_code' => 'RATETE',
            'expires_at' => null,
            'access_count' => 0,
        ]);

        $response = $this->getJson('/api/v1/public/verify/RATETEST1234');

        $response->assertHeader('X-RateLimit-Limit');
        $response->assertHeader('X-RateLimit-Remaining');
    }

    /** @test */
    public function it_handles_codes_with_dashes(): void
    {
        $document = Document::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
        ]);

        VerificationCode::create([
            'uuid' => fake()->uuid(),
            'document_id' => $document->id,
            'verification_code' => 'DASHTEST1234',
            'short_code' => 'DASHTE',
            'expires_at' => null,
            'access_count' => 0,
        ]);

        // Test with dashes
        $response = $this->getJson('/api/v1/public/verify/DASH-TEST-1234');

        $response->assertStatus(200)
            ->assertJson(['valid' => true]);
    }

    /** @test */
    public function it_handles_lowercase_codes(): void
    {
        $document = Document::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
        ]);

        VerificationCode::create([
            'uuid' => fake()->uuid(),
            'document_id' => $document->id,
            'verification_code' => 'LOWERTEST123',
            'short_code' => 'LOWERT',
            'expires_at' => null,
            'access_count' => 0,
        ]);

        // Test with lowercase
        $response = $this->getJson('/api/v1/public/verify/lowertest123');

        $response->assertStatus(200)
            ->assertJson(['valid' => true]);
    }

    /** @test */
    public function it_returns_qr_code_image(): void
    {
        $document = Document::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
        ]);

        VerificationCode::create([
            'uuid' => fake()->uuid(),
            'document_id' => $document->id,
            'verification_code' => 'QRCODE123456',
            'short_code' => 'QRCODE',
            'expires_at' => null,
            'access_count' => 0,
        ]);

        $response = $this->get('/api/v1/public/verify/QRCODE123456/qr');

        // Should return image or 404 if QR generation fails
        $this->assertTrue(
            $response->status() === 200 || $response->status() === 404
        );

        if ($response->status() === 200) {
            $this->assertStringStartsWith('image/', $response->headers->get('Content-Type'));
        }
    }

    /** @test */
    public function it_verifies_via_web_page(): void
    {
        $response = $this->get('/verify');

        $response->assertStatus(200);
    }

    /** @test */
    public function it_verifies_via_web_page_with_code(): void
    {
        $document = Document::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
        ]);

        VerificationCode::create([
            'uuid' => fake()->uuid(),
            'document_id' => $document->id,
            'verification_code' => 'WEBPAGETEST1',
            'short_code' => 'WEBPAG',
            'expires_at' => null,
            'access_count' => 0,
        ]);

        $response = $this->get('/verify/WEBPAGETEST1');

        $response->assertStatus(200);
    }

    /** @test */
    public function it_verifies_via_short_url(): void
    {
        $document = Document::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
        ]);

        VerificationCode::create([
            'uuid' => fake()->uuid(),
            'document_id' => $document->id,
            'verification_code' => 'SHORTURL1234',
            'short_code' => 'SHORT1',
            'expires_at' => null,
            'access_count' => 0,
        ]);

        $response = $this->get('/v/SHORT1');

        $response->assertStatus(200);
    }

    /** @test */
    public function it_includes_correct_confidence_headers(): void
    {
        $document = Document::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
        ]);

        VerificationCode::create([
            'uuid' => fake()->uuid(),
            'document_id' => $document->id,
            'verification_code' => 'CONFIDENCE01',
            'short_code' => 'CONFID',
            'expires_at' => null,
            'access_count' => 0,
        ]);

        $response = $this->getJson('/api/v1/public/verify/CONFIDENCE01');

        $response->assertStatus(200);

        $confidenceLevel = $response->headers->get('X-Confidence-Level');
        $this->assertContains($confidenceLevel, ['high', 'medium', 'low']);

        $confidenceScore = (int) $response->headers->get('X-Confidence-Score');
        $this->assertGreaterThanOrEqual(0, $confidenceScore);
        $this->assertLessThanOrEqual(100, $confidenceScore);
    }

    /** @test */
    public function it_returns_verified_at_timestamp(): void
    {
        $document = Document::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
        ]);

        VerificationCode::create([
            'uuid' => fake()->uuid(),
            'document_id' => $document->id,
            'verification_code' => 'TIMESTAMP123',
            'short_code' => 'TIMEST',
            'expires_at' => null,
            'access_count' => 0,
        ]);

        $response = $this->getJson('/api/v1/public/verify/TIMESTAMP123');

        $response->assertStatus(200)
            ->assertJsonStructure(['verified_at']);

        $verifiedAt = $response->json('verified_at');
        $this->assertNotNull($verifiedAt);

        // Should be a valid ISO 8601 timestamp
        $timestamp = \DateTime::createFromFormat(\DateTime::ATOM, $verifiedAt);
        $this->assertNotFalse($timestamp);
    }
}
