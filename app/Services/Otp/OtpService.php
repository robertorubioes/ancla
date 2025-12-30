<?php

declare(strict_types=1);

namespace App\Services\Otp;

use App\Jobs\SendOtpCodeJob;
use App\Models\OtpCode;
use App\Models\Signer;
use App\Services\Evidence\AuditTrailService;
use Illuminate\Support\Facades\Hash;

/**
 * Service for managing OTP (One-Time Password) verification.
 */
class OtpService
{
    public function __construct(
        private readonly AuditTrailService $auditTrailService,
    ) {}

    /**
     * Generate and send an OTP code for a signer.
     *
     * @throws OtpException
     */
    public function generate(Signer $signer): OtpResult
    {
        // 1. Check rate limiting
        if (! $this->canRequestOtp($signer)) {
            $this->auditTrailService->record(
                auditable: $signer,
                event: 'otp.rate_limited',
                payload: [
                    'signer_email' => $signer->email,
                    'signing_process_id' => $signer->signing_process_id,
                ]
            );

            throw OtpException::rateLimitExceeded();
        }

        // 2. Invalidate previous unused codes
        $this->invalidatePreviousCodes($signer);

        // 3. Generate cryptographically secure random code
        $code = $this->generateRandomCode();

        // 4. Hash the code with bcrypt
        $codeHash = Hash::make($code);

        // 5. Calculate expiration time
        $expiresAt = now()->addMinutes(config('otp.expires_minutes', 10));

        // 6. Create OTP record
        $otpCode = OtpCode::create([
            'signer_id' => $signer->id,
            'code_hash' => $codeHash,
            'expires_at' => $expiresAt,
            'attempts' => 0,
            'verified' => false,
        ]);

        // 7. Log OTP requested
        $this->auditTrailService->record(
            auditable: $signer,
            event: 'otp.requested',
            payload: [
                'otp_code_id' => $otpCode->id,
                'expires_at' => $expiresAt->toIso8601String(),
                'signer_email' => $signer->email,
                'signing_process_id' => $signer->signing_process_id,
            ]
        );

        // 8. Dispatch job to send email (with retry logic)
        SendOtpCodeJob::dispatch($otpCode, $signer, $code);

        // 9. Return result with plain code (for testing/email)
        return OtpResult::success(
            otpCode: $otpCode,
            message: 'Verification code sent to your email',
            code: $code
        );
    }

    /**
     * Verify an OTP code for a signer.
     *
     * @throws OtpException
     */
    public function verify(Signer $signer, string $code): bool
    {
        // 1. Find active OTP code
        $otpCode = OtpCode::where('signer_id', $signer->id)
            ->where('verified', false)
            ->orderBy('created_at', 'desc')
            ->first();

        if (! $otpCode) {
            $this->auditTrailService->record(
                auditable: $signer,
                event: 'otp.failed',
                payload: [
                    'reason' => 'not_found',
                    'signer_email' => $signer->email,
                ]
            );

            throw OtpException::notFound();
        }

        // 2. Check if expired
        if ($otpCode->isExpired()) {
            $this->auditTrailService->record(
                auditable: $signer,
                event: 'otp.expired',
                payload: [
                    'otp_code_id' => $otpCode->id,
                    'expired_at' => $otpCode->expires_at->toIso8601String(),
                    'signer_email' => $signer->email,
                ]
            );

            throw OtpException::expired();
        }

        // 3. Check if already verified
        if ($otpCode->isVerified()) {
            $this->auditTrailService->record(
                auditable: $signer,
                event: 'otp.already_verified',
                payload: [
                    'otp_code_id' => $otpCode->id,
                    'verified_at' => $otpCode->verified_at?->toIso8601String(),
                ]
            );

            throw OtpException::alreadyVerified();
        }

        // 4. Check if max attempts exceeded
        if ($otpCode->hasExceededMaxAttempts()) {
            $this->auditTrailService->record(
                auditable: $signer,
                event: 'otp.max_attempts_exceeded',
                payload: [
                    'otp_code_id' => $otpCode->id,
                    'attempts' => $otpCode->attempts,
                    'max_attempts' => config('otp.max_attempts'),
                ]
            );

            throw OtpException::maxAttemptsExceeded();
        }

        // 5. Verify code with Hash::check
        if (! Hash::check($code, $otpCode->code_hash)) {
            // Increment attempts on failure
            $otpCode->incrementAttempts();

            $this->auditTrailService->record(
                auditable: $signer,
                event: 'otp.failed',
                payload: [
                    'otp_code_id' => $otpCode->id,
                    'reason' => 'invalid_code',
                    'attempt' => $otpCode->attempts,
                    'signer_email' => $signer->email,
                ]
            );

            throw OtpException::invalidCode();
        }

        // 6. Mark OTP as verified
        $otpCode->markAsVerified();

        // 7. Update signer status to verified (add constant if doesn't exist)
        if (! defined(Signer::class.'::STATUS_VERIFIED')) {
            // Use 'viewed' status as fallback if 'verified' doesn't exist
            // The signer is already viewed, so we just mark OTP as verified
            // The signing capability will be checked based on OTP verification
        }

        // 8. Log successful verification
        $this->auditTrailService->record(
            auditable: $signer,
            event: 'otp.verified',
            payload: [
                'otp_code_id' => $otpCode->id,
                'signer_email' => $signer->email,
                'signing_process_id' => $signer->signing_process_id,
            ]
        );

        return true;
    }

    /**
     * Check if a signer can request an OTP (rate limiting).
     */
    public function canRequestOtp(Signer $signer): bool
    {
        $rateLimit = config('otp.rate_limit_per_hour', 3);
        $oneHourAgo = now()->subHour();

        $recentCodesCount = OtpCode::where('signer_id', $signer->id)
            ->where('created_at', '>', $oneHourAgo)
            ->count();

        return $recentCodesCount < $rateLimit;
    }

    /**
     * Check if a signer has verified OTP.
     */
    public function hasVerifiedOtp(Signer $signer): bool
    {
        return OtpCode::where('signer_id', $signer->id)
            ->where('verified', true)
            ->exists();
    }

    /**
     * Generate a cryptographically secure random OTP code.
     */
    private function generateRandomCode(): string
    {
        $length = config('otp.length', 6);
        $min = 10 ** ($length - 1);
        $max = (10 ** $length) - 1;

        return str_pad((string) random_int($min, $max), $length, '0', STR_PAD_LEFT);
    }

    /**
     * Invalidate all previous unverified codes for a signer.
     */
    private function invalidatePreviousCodes(Signer $signer): void
    {
        OtpCode::where('signer_id', $signer->id)
            ->where('verified', false)
            ->delete();
    }
}
