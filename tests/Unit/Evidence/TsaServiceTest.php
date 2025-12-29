<?php

namespace Tests\Unit\Evidence;

use App\Models\Tenant;
use App\Models\TsaToken;
use App\Services\Evidence\TsaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TsaServiceTest extends TestCase
{
    use RefreshDatabase;

    private TsaService $tsaService;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a tenant
        $this->tenant = Tenant::factory()->create();
        app()->instance('tenant', $this->tenant);

        // Enable mock mode for tests
        Config::set('evidence.tsa.mock', true);

        $this->tsaService = new TsaService;
    }

    #[Test]
    public function it_creates_mock_timestamp_tokens(): void
    {
        $hash = hash('sha256', 'test content');

        $token = $this->tsaService->requestTimestamp($hash);

        $this->assertInstanceOf(TsaToken::class, $token);
        $this->assertEquals($hash, $token->data_hash);
        $this->assertEquals('mock', $token->provider);
        $this->assertEquals(TsaToken::STATUS_VALID, $token->status);
    }

    #[Test]
    public function it_associates_tokens_with_current_tenant(): void
    {
        $hash = hash('sha256', 'test content');

        $token = $this->tsaService->requestTimestamp($hash);

        $this->assertEquals($this->tenant->id, $token->tenant_id);
    }

    #[Test]
    public function it_generates_unique_uuids_for_each_token(): void
    {
        $hash = hash('sha256', 'test content');

        $token1 = $this->tsaService->requestTimestamp($hash);
        $token2 = $this->tsaService->requestTimestamp($hash);

        $this->assertNotEquals($token1->uuid, $token2->uuid);
    }

    #[Test]
    public function it_stores_issued_at_timestamp(): void
    {
        $hash = hash('sha256', 'test content');

        $token = $this->tsaService->requestTimestamp($hash);

        $this->assertNotNull($token->issued_at);
        $this->assertTrue($token->issued_at->isToday());
    }

    #[Test]
    public function it_stores_base64_encoded_token(): void
    {
        $hash = hash('sha256', 'test content');

        $token = $this->tsaService->requestTimestamp($hash);

        // Token should be base64 encoded
        $decoded = base64_decode($token->token, true);
        $this->assertNotFalse($decoded);
    }

    #[Test]
    public function it_verifies_mock_tokens_successfully(): void
    {
        $hash = hash('sha256', 'test content');

        $token = $this->tsaService->requestTimestamp($hash);
        $isValid = $this->tsaService->verifyTimestamp($token);

        $this->assertTrue($isValid);
    }

    #[Test]
    public function it_marks_token_as_verified_after_verification(): void
    {
        $hash = hash('sha256', 'test content');

        $token = $this->tsaService->requestTimestamp($hash);
        $this->tsaService->verifyTimestamp($token);

        $token->refresh();

        $this->assertNotNull($token->verified_at);
        $this->assertEquals(TsaToken::STATUS_VALID, $token->status);
    }

    #[Test]
    public function it_reports_mock_as_provider_when_mock_enabled(): void
    {
        $provider = $this->tsaService->getProvider();

        $this->assertEquals(TsaToken::PROVIDER_MOCK, $provider);
    }

    #[Test]
    public function it_reports_mock_mode_is_enabled(): void
    {
        $this->assertTrue($this->tsaService->isMockEnabled());
    }

    #[Test]
    public function it_can_enable_mock_mode(): void
    {
        Config::set('evidence.tsa.mock', false);
        $service = new TsaService;

        $this->assertFalse($service->isMockEnabled());

        $service->enableMock();

        $this->assertTrue($service->isMockEnabled());
    }

    #[Test]
    public function it_can_disable_mock_mode(): void
    {
        $this->assertTrue($this->tsaService->isMockEnabled());

        $this->tsaService->disableMock();

        $this->assertFalse($this->tsaService->isMockEnabled());
    }

    #[Test]
    public function it_stores_sha256_as_hash_algorithm(): void
    {
        $hash = hash('sha256', 'test content');

        $token = $this->tsaService->requestTimestamp($hash);

        $this->assertEquals('SHA-256', $token->hash_algorithm);
    }

    #[Test]
    public function it_can_decode_stored_token(): void
    {
        $hash = hash('sha256', 'test content');

        $token = $this->tsaService->requestTimestamp($hash);
        $decoded = $token->getDecodedToken();

        $this->assertNotEmpty($decoded);
        $this->assertIsString($decoded);
    }

    #[Test]
    public function mock_token_contains_correct_hash(): void
    {
        $content = 'test content';
        $hash = hash('sha256', $content);

        $token = $this->tsaService->requestTimestamp($hash);
        $decoded = json_decode($token->getDecodedToken(), true);

        $this->assertEquals($hash, $decoded['hash']);
    }

    #[Test]
    public function mock_token_contains_timestamp(): void
    {
        $hash = hash('sha256', 'test content');

        $token = $this->tsaService->requestTimestamp($hash);
        $decoded = json_decode($token->getDecodedToken(), true);

        $this->assertArrayHasKey('timestamp', $decoded);
    }

    #[Test]
    public function mock_token_contains_serial_number(): void
    {
        $hash = hash('sha256', 'test content');

        $token = $this->tsaService->requestTimestamp($hash);
        $decoded = json_decode($token->getDecodedToken(), true);

        $this->assertArrayHasKey('serial', $decoded);
    }

    #[Test]
    public function it_handles_multiple_tokens_for_same_hash(): void
    {
        $hash = hash('sha256', 'test content');

        $token1 = $this->tsaService->requestTimestamp($hash);
        $token2 = $this->tsaService->requestTimestamp($hash);

        // Should create separate tokens even for same hash
        $this->assertNotEquals($token1->id, $token2->id);
        $this->assertEquals($token1->data_hash, $token2->data_hash);
    }

    #[Test]
    public function token_is_valid_method_works(): void
    {
        $hash = hash('sha256', 'test content');

        $token = $this->tsaService->requestTimestamp($hash);

        $this->assertTrue($token->isValid());
    }

    #[Test]
    public function has_been_verified_method_works(): void
    {
        $hash = hash('sha256', 'test content');

        $token = $this->tsaService->requestTimestamp($hash);

        // Mock tokens are immediately verified
        $this->assertTrue($token->hasBeenVerified());
    }
}
