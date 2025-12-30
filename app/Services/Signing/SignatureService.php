<?php

declare(strict_types=1);

namespace App\Services\Signing;

use App\Models\EvidencePackage;
use App\Models\Signer;
use App\Services\Evidence\AuditTrailService;
use App\Services\Evidence\ConsentCaptureService;
use App\Services\Evidence\DeviceFingerprintService;
use App\Services\Evidence\GeolocationService;
use App\Services\Evidence\IpResolutionService;
use App\Services\Evidence\TsaService;
use App\Services\Otp\OtpService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Service for processing digital signatures.
 *
 * Handles validation, evidence capture, and signature application.
 */
class SignatureService
{
    /**
     * Valid signature types.
     */
    public const TYPE_DRAW = 'draw';

    public const TYPE_TYPE = 'type';

    public const TYPE_UPLOAD = 'upload';

    /**
     * Maximum file size for uploaded signatures (2MB).
     */
    public const MAX_FILE_SIZE = 2 * 1024 * 1024;

    /**
     * Maximum image dimensions.
     */
    public const MAX_WIDTH = 4000;

    public const MAX_HEIGHT = 4000;

    /**
     * Create a new signature service.
     */
    public function __construct(
        private readonly DeviceFingerprintService $deviceFingerprintService,
        private readonly GeolocationService $geolocationService,
        private readonly IpResolutionService $ipResolutionService,
        private readonly ConsentCaptureService $consentCaptureService,
        private readonly TsaService $tsaService,
        private readonly AuditTrailService $auditTrailService,
        private readonly OtpService $otpService,
    ) {}

    /**
     * Process a signature for a signer.
     *
     * @throws SignatureException
     */
    public function processSignature(
        Signer $signer,
        string $type,
        string $data,
        bool $consentGiven,
        ?array $metadata = null
    ): SignatureResult {
        try {
            DB::beginTransaction();

            // 1. Validate consent
            if (! $consentGiven) {
                throw SignatureException::consentRequired();
            }

            // 2. Validate OTP verification
            if (! $this->otpService->hasVerifiedOtp($signer)) {
                throw SignatureException::otpNotVerified();
            }

            // 3. Validate signer can sign
            if (! $signer->canSignNow()) {
                throw SignatureException::signerNotReady(
                    'Signer is not ready to sign or has already signed.'
                );
            }

            // 4. Validate type and data
            $this->validateSignatureData($type, $data, $metadata);

            // 5. Capture signature evidences
            $evidencePackage = $this->captureSignatureEvidences($signer, $consentGiven);

            // 6. Save signature data
            $signer->update([
                'signature_type' => $type,
                'signature_data' => $data,
                'signed_at' => now(),
                'status' => Signer::STATUS_SIGNED,
                'evidence_package_id' => $evidencePackage->id,
                'signature_metadata' => $metadata,
            ]);

            // 7. Check if all signers have completed
            $process = $signer->signingProcess()->with('signers')->first();
            if ($process && $process->allSignersCompleted()) {
                $process->markAsCompleted();
            } elseif ($process && ! $process->isInProgress()) {
                $process->markAsInProgress();
            }

            // 8. Register in audit trail
            $this->auditTrailService->log(
                action: 'signer.signed',
                auditable: $signer,
                data: [
                    'signer_id' => $signer->id,
                    'signer_email' => $signer->email,
                    'signature_type' => $type,
                    'process_id' => $process?->id,
                    'evidence_package_id' => $evidencePackage->id,
                ],
                description: "Signer {$signer->name} signed the document using {$type} signature"
            );

            DB::commit();

            Log::info('Signature processed successfully', [
                'signer_id' => $signer->id,
                'type' => $type,
                'evidence_package_id' => $evidencePackage->id,
            ]);

            return SignatureResult::success($signer);
        } catch (SignatureException $e) {
            DB::rollBack();
            Log::warning('Signature processing failed', [
                'signer_id' => $signer->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Signature processing error', [
                'signer_id' => $signer->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw SignatureException::invalidData($e->getMessage());
        }
    }

    /**
     * Validate signature data based on type.
     *
     * @throws SignatureException
     */
    private function validateSignatureData(string $type, string $data, ?array $metadata): void
    {
        // Validate type
        if (! in_array($type, [self::TYPE_DRAW, self::TYPE_TYPE, self::TYPE_UPLOAD])) {
            throw SignatureException::invalidType($type);
        }

        // Validate data is not empty
        if (empty($data)) {
            throw SignatureException::invalidData('Signature data cannot be empty');
        }

        switch ($type) {
            case self::TYPE_DRAW:
                $this->validateCanvasSignature($data);
                break;
            case self::TYPE_TYPE:
                $this->validateTypedSignature($data);
                break;
            case self::TYPE_UPLOAD:
                $this->validateUploadedSignature($data, $metadata);
                break;
        }
    }

    /**
     * Validate canvas (drawn) signature.
     *
     * @throws SignatureException
     */
    private function validateCanvasSignature(string $data): void
    {
        // Check if it's a valid base64 PNG data URL
        if (! str_starts_with($data, 'data:image/png;base64,')) {
            throw SignatureException::invalidData('Canvas signature must be a PNG data URL');
        }

        // Extract base64 data
        $base64Data = substr($data, strlen('data:image/png;base64,'));
        $imageData = base64_decode($base64Data, true);

        if ($imageData === false) {
            throw SignatureException::invalidData('Invalid base64 encoding');
        }

        // Verify it's a valid PNG
        $image = @imagecreatefromstring($imageData);
        if ($image === false) {
            throw SignatureException::corruptedImage();
        }

        // Check if canvas has meaningful content (not just empty/white)
        $width = imagesx($image);
        $height = imagesy($image);
        $pixelCount = 0;

        // Sample pixels to detect if there's actual drawing
        for ($x = 0; $x < $width; $x += 5) {
            for ($y = 0; $y < $height; $y += 5) {
                $rgb = imagecolorat($image, $x, $y);
                $colors = imagecolorsforindex($image, $rgb);
                // Check if pixel is not white/transparent
                if ($colors['red'] < 250 || $colors['green'] < 250 || $colors['blue'] < 250) {
                    $pixelCount++;
                }
            }
        }

        imagedestroy($image);

        // Require at least 10 colored pixels
        if ($pixelCount < 10) {
            throw SignatureException::canvasEmpty();
        }
    }

    /**
     * Validate typed signature.
     *
     * @throws SignatureException
     */
    private function validateTypedSignature(string $data): void
    {
        // For typed signature, data should be the text
        // If it's a data URL, extract the text from metadata
        if (str_starts_with($data, 'data:image/')) {
            // This is the rendered image, validation passes
            return;
        }

        $length = mb_strlen($data);

        if ($length < 2) {
            throw SignatureException::textTooShort();
        }

        if ($length > 100) {
            throw SignatureException::textTooLong();
        }

        // Validate only contains letters, spaces, and common punctuation
        if (! preg_match('/^[\p{L}\s\-\'.]+$/u', $data)) {
            throw SignatureException::invalidData('Typed signature contains invalid characters');
        }
    }

    /**
     * Validate uploaded signature image.
     *
     * @throws SignatureException
     */
    private function validateUploadedSignature(string $data, ?array $metadata): void
    {
        // Check if it's a valid data URL
        if (! preg_match('/^data:image\/(png|jpeg|jpg);base64,/', $data, $matches)) {
            throw SignatureException::invalidFormat();
        }

        // Extract base64 data
        $base64Data = preg_replace('/^data:image\/(png|jpeg|jpg);base64,/', '', $data);
        $imageData = base64_decode($base64Data, true);

        if ($imageData === false) {
            throw SignatureException::invalidData('Invalid base64 encoding');
        }

        // Check file size
        $fileSize = strlen($imageData);
        if ($fileSize > self::MAX_FILE_SIZE) {
            throw SignatureException::fileTooLarge();
        }

        // Verify image is valid
        $image = @imagecreatefromstring($imageData);
        if ($image === false) {
            throw SignatureException::corruptedImage();
        }

        // Check dimensions
        $width = imagesx($image);
        $height = imagesy($image);

        imagedestroy($image);

        if ($width > self::MAX_WIDTH || $height > self::MAX_HEIGHT) {
            throw SignatureException::invalidDimensions();
        }

        // Validate magic bytes for additional security
        $magicBytes = substr($imageData, 0, 4);
        $isPng = $magicBytes === "\x89PNG";
        $isJpeg = substr($imageData, 0, 2) === "\xFF\xD8";

        if (! $isPng && ! $isJpeg) {
            throw SignatureException::invalidFormat();
        }
    }

    /**
     * Capture all signature evidences.
     */
    private function captureSignatureEvidences(Signer $signer, bool $consentGiven): EvidencePackage
    {
        $process = $signer->signingProcess;
        $document = $process->document;

        // Create evidence package
        $evidencePackage = EvidencePackage::create([
            'tenant_id' => $process->tenant_id,
            'document_id' => $document->id,
            'type' => 'signature',
            'status' => 'active',
        ]);

        // Capture device fingerprint
        $deviceFingerprint = $this->deviceFingerprintService->captureFromRequest();
        $deviceFingerprint->evidence_package_id = $evidencePackage->id;
        $deviceFingerprint->save();

        // Capture IP resolution
        $ipResolution = $this->ipResolutionService->resolveFromRequest();
        $ipResolution->evidence_package_id = $evidencePackage->id;
        $ipResolution->save();

        // Capture geolocation (if available)
        try {
            $geolocation = $this->geolocationService->captureFromRequest();
            if ($geolocation) {
                $geolocation->evidence_package_id = $evidencePackage->id;
                $geolocation->save();
            }
        } catch (\Exception $e) {
            // Geolocation is optional, log but continue
            Log::info('Geolocation capture failed (optional)', ['error' => $e->getMessage()]);
        }

        // Capture consent record
        $consent = $this->consentCaptureService->captureConsent(
            type: 'electronic_signature',
            consentGiven: $consentGiven,
            metadata: [
                'signer_id' => $signer->id,
                'signer_email' => $signer->email,
                'document_id' => $document->id,
                'process_id' => $process->id,
            ]
        );
        $consent->evidence_package_id = $evidencePackage->id;
        $consent->save();

        // Generate TSA token for timestamp
        $tsaToken = $this->tsaService->timestamp(
            data: json_encode([
                'signer_id' => $signer->id,
                'document_id' => $document->id,
                'signed_at' => now()->toIso8601String(),
            ])
        );
        $tsaToken->evidence_package_id = $evidencePackage->id;
        $tsaToken->save();

        // Update evidence package status
        $evidencePackage->update(['status' => 'sealed']);

        return $evidencePackage;
    }
}
