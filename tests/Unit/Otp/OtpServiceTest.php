<?php

declare(strict_types=1);

namespace Tests\Unit\Otp;

use App\Jobs\SendOtpCodeJob;
use App\Models\OtpCode;
use App\Models\Signer;
use App\Models\SigningProcess;
use App\Models\Tenant;
use App\Services\Evidence\AuditTrailService;
use App\Services\Otp\OtpException;
use App\Services\Otp\OtpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class OtpServiceTest extends TestCase
{
    use RefreshDatabase;

    private OtpService $otpService;

    private Signer $signer;

    protected function setUp(): void
    {
        parent::setUp();

        // Create tenant and bind it to the container
        $tenant = Tenant::factory()->create();
        app()->instance('tenant', $tenant);

        $this->otpService = app(OtpService::class);

        // Create signing process and signer
        $process = SigningProcess::factory()->for($tenant)->create();
        $this->signer = Signer::factory()->for($process, 'signingProcess')->create();

        Queue::fake();
    }

    /** @test */
    public function it_generates_valid_otp_code(): void
    {
        $result = $this->otpService->generate($this->signer);

        $this->assertTrue($result->isSuccess());
        $this->assertNotNull($result->otpCode);
        $this->assertNotNull($result->code);
    }

    /** @test */
    public function generated_code_is_6_digits(): void
    {
        $result = $this->otpService->generate($this->signer);

        $this->assertEquals(6, strlen($result->code));
        $this->assertMatchesRegularExpression('/^\d{6}$/', $result->code);
    }

    /** @test */
    public function code_is_hashed_in_database(): void
    {
        $result = $this->otpService->generate($this->signer);

        $otpCode = OtpCode::find($result->otpCode->id);

        $this->assertNotEquals($result->code, $otpCode->code_hash);
        $this->assertTrue(Hash::check($result->code, $otpCode->code_hash));
    }

    /** @test */
    public function expiration_is_10_minutes_from_generation(): void
    {
        $beforeGeneration = now();
        $result = $this->otpService->generate($this->signer);
        $afterGeneration = now();

        $expectedExpiration = $beforeGeneration->copy()->addMinutes(10);
        $otpCode = $result->otpCode;

        $this->assertGreaterThanOrEqual(
            $expectedExpiration->timestamp,
            $otpCode->expires_at->timestamp
        );

        $this->assertLessThanOrEqual(
            $afterGeneration->copy()->addMinutes(10)->timestamp,
            $otpCode->expires_at->timestamp
        );
    }

    /** @test */
    public function verification_succeeds_with_correct_code(): void
    {
        $result = $this->otpService->generate($this->signer);
        $code = $result->code;

        $verified = $this->otpService->verify($this->signer, $code);

        $this->assertTrue($verified);
    }

    /** @test */
    public function verification_fails_with_incorrect_code(): void
    {
        $this->otpService->generate($this->signer);

        $this->expectException(OtpException::class);
        $this->expectExceptionMessage('Invalid verification code');

        $this->otpService->verify($this->signer, '999999');
    }

    /** @test */
    public function expired_code_is_rejected(): void
    {
        OtpCode::factory()
            ->for($this->signer)
            ->expired()
            ->withCode('123456')
            ->create();

        $this->expectException(OtpException::class);
        $this->expectExceptionMessage('expired');

        $this->otpService->verify($this->signer, '123456');
    }

    /** @test */
    public function max_attempts_limit_is_enforced(): void
    {
        OtpCode::factory()
            ->for($this->signer)
            ->maxAttemptsExceeded()
            ->withCode('123456')
            ->create();

        $this->expectException(OtpException::class);
        $this->expectExceptionMessage('Maximum verification attempts exceeded');

        $this->otpService->verify($this->signer, '123456');
    }

    /** @test */
    public function rate_limiting_allows_3_requests_per_hour(): void
    {
        // First 3 requests should succeed
        $this->otpService->generate($this->signer);
        $this->otpService->generate($this->signer);
        $this->otpService->generate($this->signer);

        // 4th request should fail
        $this->expectException(OtpException::class);
        $this->expectExceptionMessage('Rate limit exceeded');

        $this->otpService->generate($this->signer);
    }

    /** @test */
    public function can_request_otp_checks_rate_limit(): void
    {
        $this->assertTrue($this->otpService->canRequestOtp($this->signer));

        // Create 3 OTP codes in the last hour
        OtpCode::factory()->for($this->signer)->count(3)->create();

        $this->assertFalse($this->otpService->canRequestOtp($this->signer));
    }

    /** @test */
    public function previous_codes_are_invalidated_on_new_generation(): void
    {
        // Generate first code
        $firstResult = $this->otpService->generate($this->signer);
        $firstCodeId = $firstResult->otpCode->id;

        // Generate second code
        $this->otpService->generate($this->signer);

        // First code should be deleted
        $this->assertDatabaseMissing('otp_codes', ['id' => $firstCodeId]);
    }

    /** @test */
    public function signer_otp_verified_status_is_updated(): void
    {
        $result = $this->otpService->generate($this->signer);

        $this->assertFalse($this->otpService->hasVerifiedOtp($this->signer));

        $this->otpService->verify($this->signer, $result->code);

        $this->assertTrue($this->otpService->hasVerifiedOtp($this->signer));
    }

    /** @test */
    public function audit_trail_logs_otp_requested(): void
    {
        $auditTrailService = $this->mock(AuditTrailService::class);
        $auditTrailService->shouldReceive('record')
            ->once()
            ->withArgs(function ($auditable, $event, $payload) {
                return $auditable->id === $this->signer->id
                    && $event === 'otp.requested'
                    && isset($payload['otp_code_id']);
            });

        $otpService = new OtpService($auditTrailService);
        $otpService->generate($this->signer);
    }

    /** @test */
    public function audit_trail_logs_otp_verified(): void
    {
        $result = $this->otpService->generate($this->signer);

        $auditTrailService = $this->mock(AuditTrailService::class);
        $auditTrailService->shouldReceive('record')
            ->once()
            ->withArgs(function ($auditable, $event) {
                return $auditable->id === $this->signer->id
                    && $event === 'otp.verified';
            });

        $otpService = new OtpService($auditTrailService);
        $otpService->verify($this->signer, $result->code);
    }

    /** @test */
    public function audit_trail_logs_otp_failed(): void
    {
        $this->otpService->generate($this->signer);

        $auditTrailService = $this->mock(AuditTrailService::class);
        $auditTrailService->shouldReceive('record')
            ->once()
            ->withArgs(function ($auditable, $event, $payload) {
                return $auditable->id === $this->signer->id
                    && $event === 'otp.failed'
                    && $payload['reason'] === 'invalid_code';
            });

        $otpService = new OtpService($auditTrailService);

        try {
            $otpService->verify($this->signer, '999999');
        } catch (OtpException $e) {
            // Expected
        }
    }

    /** @test */
    public function email_job_is_dispatched(): void
    {
        Queue::fake();

        $this->otpService->generate($this->signer);

        Queue::assertPushed(SendOtpCodeJob::class);
    }

    /** @test */
    public function attempts_counter_increments_on_failure(): void
    {
        $result = $this->otpService->generate($this->signer);
        $otpCode = $result->otpCode;

        $this->assertEquals(0, $otpCode->attempts);

        try {
            $this->otpService->verify($this->signer, '999999');
        } catch (OtpException $e) {
            // Expected
        }

        $otpCode->refresh();
        $this->assertEquals(1, $otpCode->attempts);
    }

    /** @test */
    public function verified_code_cannot_be_reused(): void
    {
        $result = $this->otpService->generate($this->signer);

        // First verification
        $this->otpService->verify($this->signer, $result->code);

        // Second verification should fail
        $this->expectException(OtpException::class);
        $this->expectExceptionMessage('already been verified');

        $this->otpService->verify($this->signer, $result->code);
    }

    /** @test */
    public function code_not_found_throws_exception(): void
    {
        $this->expectException(OtpException::class);
        $this->expectExceptionMessage('No active verification code found');

        $this->otpService->verify($this->signer, '123456');
    }

    /** @test */
    public function rate_limit_is_per_signer(): void
    {
        // Create another signer
        $anotherSigner = Signer::factory()
            ->for($this->signer->signingProcess, 'signingProcess')
            ->create();

        // Generate 3 codes for first signer
        $this->otpService->generate($this->signer);
        $this->otpService->generate($this->signer);
        $this->otpService->generate($this->signer);

        // First signer should be rate limited
        $this->assertFalse($this->otpService->canRequestOtp($this->signer));

        // But second signer should still be able to request
        $this->assertTrue($this->otpService->canRequestOtp($anotherSigner));
    }
}
