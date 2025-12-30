<?php

declare(strict_types=1);

namespace App\Services\Signing;

use App\Models\Document;
use App\Models\SignedDocument;
use App\Models\Signer;
use App\Services\Evidence\TsaService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PdfSignatureService
{
    public function __construct(
        private readonly CertificateService $certificateService,
        private readonly Pkcs7Builder $pkcs7Builder,
        private readonly PdfEmbedder $pdfEmbedder,
        private readonly TsaService $tsaService
    ) {}

    /**
     * Sign a PDF document with PAdES signature.
     *
     * This is the main orchestrator method that coordinates the entire
     * PDF signing process according to PAdES-B-LT standard.
     */
    public function signDocument(
        Document $document,
        Signer $signer,
        array $metadata = []
    ): SignedDocument {
        Log::info('Starting PDF signature process', [
            'document_id' => $document->id,
            'signer_id' => $signer->id,
            'pades_level' => config('signing.pades_level'),
        ]);

        // Validate signer is ready to sign
        $this->validateSignerReadiness($signer);

        return DB::transaction(function () use ($document, $signer, $metadata) {
            try {
                // 1. Get original PDF content
                $pdfContent = $this->getOriginalPdfContent($document);
                $originalHash = hash('sha256', $pdfContent);

                Log::info('PDF content loaded', [
                    'size' => strlen($pdfContent),
                    'hash' => $originalHash,
                ]);

                // 2. Load platform certificate and private key
                $certificate = $this->certificateService->loadCertificate();
                $privateKey = $this->certificateService->getPrivateKey();

                Log::info('Certificate loaded', [
                    'subject' => $certificate->getSubject(),
                    'valid_until' => $certificate->getValidTo()->format('Y-m-d'),
                ]);

                // 3. Create PKCS#7 SignedData
                $pkcs7 = $this->pkcs7Builder
                    ->setCertificate($certificate)
                    ->setPrivateKey($privateKey)
                    ->setContentHash($originalHash)
                    ->setSigningTime(now())
                    ->setReason(config('signing.reasons.custom'))
                    ->setLocation(config('signing.locations.default'))
                    ->setContactInfo(config('signing.contact.info'))
                    ->build();

                Log::info('PKCS#7 signature created', [
                    'size' => strlen($pkcs7),
                ]);

                // 4. Request TSA timestamp if PAdES-B-LT
                $tsaToken = null;
                if ($this->requiresTsaToken()) {
                    try {
                        $tsaToken = $this->tsaService->requestTimestamp($originalHash);
                        $pkcs7 = $this->pkcs7Builder->embedTsaToken($pkcs7, $tsaToken);

                        Log::info('TSA timestamp obtained', [
                            'tsa_token_id' => $tsaToken->id,
                            'provider' => $tsaToken->provider,
                        ]);
                    } catch (\Exception $e) {
                        Log::error('TSA timestamp request failed', [
                            'error' => $e->getMessage(),
                        ]);

                        throw PdfSignatureException::tsaRequestFailed($e->getMessage());
                    }
                }

                // 5. Prepare signature appearance
                $appearance = $this->prepareSignatureAppearance($signer, $metadata);

                // 6. Embed signature in PDF
                $signedPdfContent = $this->pdfEmbedder
                    ->importPdf($pdfContent)
                    ->addSignatureField(config('signing.appearance.position'))
                    ->addSignatureAppearance($appearance)
                    ->embedPkcs7($pkcs7)
                    ->embedMetadata($this->prepareEmbeddedMetadata($signer, $metadata))
                    ->generate();

                $signedHash = hash('sha256', $signedPdfContent);

                Log::info('Signed PDF generated', [
                    'size' => strlen($signedPdfContent),
                    'hash' => $signedHash,
                ]);

                // 7. Store signed PDF
                $signedPath = $this->storeSignedPdf($signedPdfContent, $signer);

                // 8. Create SignedDocument record
                $signedDocument = SignedDocument::create([
                    'uuid' => Str::uuid(),
                    'tenant_id' => $document->tenant_id,
                    'signing_process_id' => $signer->signing_process_id,
                    'signer_id' => $signer->id,
                    'original_document_id' => $document->id,
                    'storage_disk' => 'local',
                    'signed_path' => $signedPath,
                    'signed_name' => $this->generateSignedFilename($document, $signer),
                    'file_size' => strlen($signedPdfContent),
                    'content_hash' => $signedHash,
                    'original_hash' => $originalHash,
                    'hash_algorithm' => 'SHA-256',
                    'pkcs7_signature' => bin2hex($pkcs7),
                    'certificate_subject' => $certificate->getSubject(),
                    'certificate_issuer' => $certificate->getIssuer(),
                    'certificate_serial' => $certificate->getSerialNumber(),
                    'certificate_fingerprint' => $certificate->getFingerprint('sha256'),
                    'pades_level' => config('signing.pades_level'),
                    'has_tsa_token' => $tsaToken !== null,
                    'tsa_token_id' => $tsaToken?->id,
                    'has_validation_data' => $tsaToken !== null,
                    'signature_position' => config('signing.appearance.position'),
                    'signature_visible' => config('signing.appearance.mode') === 'visible',
                    'signature_appearance' => $appearance,
                    'embedded_metadata' => $metadata,
                    'verification_code_id' => $metadata['verification_code_id'] ?? null,
                    'qr_code_embedded' => ! empty($metadata['qr_code_path']),
                    'evidence_package_id' => $metadata['evidence_package_id'] ?? $signer->evidence_package_id,
                    'status' => 'signed',
                    'signed_at' => now(),
                ]);

                Log::info('PDF signature completed successfully', [
                    'signed_document_id' => $signedDocument->id,
                    'signed_document_uuid' => $signedDocument->uuid,
                ]);

                return $signedDocument;
            } catch (PdfSignatureException $e) {
                Log::error('PDF signature failed', [
                    'document_id' => $document->id,
                    'signer_id' => $signer->id,
                    'error' => $e->getMessage(),
                ]);

                throw $e;
            } catch (\Exception $e) {
                Log::error('Unexpected error during PDF signature', [
                    'document_id' => $document->id,
                    'signer_id' => $signer->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);

                throw new PdfSignatureException(
                    'Failed to sign PDF: '.$e->getMessage(),
                    previous: $e
                );
            }
        });
    }

    /**
     * Validate a signed PDF document.
     */
    public function validateSignature(SignedDocument $signedDocument): SignatureValidationResult
    {
        Log::info('Validating signed document', [
            'signed_document_id' => $signedDocument->id,
        ]);

        try {
            // 1. Verify PDF file exists
            $pdfPath = Storage::path($signedDocument->signed_path);
            if (! file_exists($pdfPath)) {
                return new SignatureValidationResult(
                    isValid: false,
                    hashValid: false,
                    pkcs7Valid: false,
                    tsaValid: false,
                    certificateValid: false,
                    validatedAt: now(),
                    errorMessage: 'Signed PDF file not found'
                );
            }

            // 2. Verify hash integrity
            $currentHash = hash_file('sha256', $pdfPath);
            $hashValid = hash_equals($signedDocument->content_hash, $currentHash);

            // 3. Verify PKCS#7 signature
            $pkcs7Valid = false;
            try {
                $certificate = $this->certificateService->loadCertificate();
                $pkcs7Valid = $this->pkcs7Builder->verify(
                    hex2bin($signedDocument->pkcs7_signature),
                    $certificate
                );
            } catch (\Exception $e) {
                Log::error('PKCS#7 verification failed', ['error' => $e->getMessage()]);
            }

            // 4. Verify TSA token
            $tsaValid = true;
            if ($signedDocument->has_tsa_token && $signedDocument->tsaToken) {
                try {
                    $tsaValid = $this->tsaService->verifyTimestamp($signedDocument->tsaToken);
                } catch (\Exception $e) {
                    Log::error('TSA verification failed', ['error' => $e->getMessage()]);
                    $tsaValid = false;
                }
            }

            // 5. Verify certificate validity
            $certificateValid = false;
            try {
                $certificateValid = $this->certificateService->checkRevocation(
                    $signedDocument->certificate_serial
                );
            } catch (\Exception $e) {
                Log::error('Certificate validation failed', ['error' => $e->getMessage()]);
            }

            $isValid = $hashValid && $pkcs7Valid && $tsaValid && $certificateValid;

            // Update validation status
            $signedDocument->update([
                'adobe_validated' => $isValid,
                'adobe_validation_date' => now(),
                'validation_errors' => $isValid ? null : [
                    'hash_valid' => $hashValid,
                    'pkcs7_valid' => $pkcs7Valid,
                    'tsa_valid' => $tsaValid,
                    'certificate_valid' => $certificateValid,
                ],
            ]);

            return new SignatureValidationResult(
                isValid: $isValid,
                hashValid: $hashValid,
                pkcs7Valid: $pkcs7Valid,
                tsaValid: $tsaValid,
                certificateValid: $certificateValid,
                validatedAt: now()
            );
        } catch (\Exception $e) {
            Log::error('Signature validation failed', [
                'signed_document_id' => $signedDocument->id,
                'error' => $e->getMessage(),
            ]);

            return new SignatureValidationResult(
                isValid: false,
                hashValid: false,
                pkcs7Valid: false,
                tsaValid: false,
                certificateValid: false,
                validatedAt: now(),
                errorMessage: $e->getMessage()
            );
        }
    }

    /**
     * Validate signer is ready for PDF signature.
     */
    private function validateSignerReadiness(Signer $signer): void
    {
        // Signer must have signed (signature captured)
        if (! $signer->signed_at) {
            throw PdfSignatureException::signerNotReady('Signer has not provided signature yet');
        }

        // OTP must be verified
        if (! $signer->otp_verified) {
            throw PdfSignatureException::signerNotReady('OTP not verified');
        }

        // Must have signature data
        if (empty($signer->signature_data)) {
            throw PdfSignatureException::signerNotReady('No signature data available');
        }
    }

    /**
     * Check if TSA token is required based on PAdES level.
     */
    private function requiresTsaToken(): bool
    {
        $padesLevel = config('signing.pades_level');

        return in_array($padesLevel, ['B-LT', 'B-LTA']);
    }

    /**
     * Get original PDF content (decrypt if necessary).
     */
    private function getOriginalPdfContent(Document $document): string
    {
        $content = Storage::disk($document->storage_disk)->get($document->stored_path);

        if (! $content) {
            throw PdfSignatureException::pdfReadFailed($document->stored_path);
        }

        // Decrypt if encrypted
        if ($document->is_encrypted) {
            try {
                $content = decrypt($content);
            } catch (\Exception $e) {
                throw PdfSignatureException::pdfReadFailed(
                    'Failed to decrypt document: '.$e->getMessage()
                );
            }
        }

        return $content;
    }

    /**
     * Store signed PDF file.
     */
    private function storeSignedPdf(string $content, Signer $signer): string
    {
        $path = sprintf(
            'signed/%s/%s/%s_%s.pdf',
            $signer->tenant_id,
            now()->format('Y/m'),
            $signer->signing_process_id,
            $signer->id
        );

        Storage::disk('local')->put($path, $content);

        Log::info('Signed PDF stored', ['path' => $path]);

        return $path;
    }

    /**
     * Generate filename for signed document.
     */
    private function generateSignedFilename(Document $document, Signer $signer): string
    {
        $basename = pathinfo($document->original_name, PATHINFO_FILENAME);

        return sprintf('%s_signed_%s.pdf', $basename, $signer->id);
    }

    /**
     * Prepare signature appearance data.
     */
    private function prepareSignatureAppearance(Signer $signer, array $metadata): array
    {
        return [
            'signature_image_path' => $signer->signature_data ?? null,
            'signer_name' => $signer->name,
            'signer_email' => $signer->email,
            'signing_time' => now()->format('d/m/Y H:i:s'),
            'verification_code' => $metadata['verification_code'] ?? null,
            'verification_url' => $metadata['verification_url'] ?? null,
            'qr_code_path' => $metadata['qr_code_path'] ?? null,
            'logo_path' => config('signing.appearance.style.logo_path'),
            'layout' => config('signing.appearance.layout'),
            'style' => config('signing.appearance.style'),
        ];
    }

    /**
     * Prepare metadata to embed in PDF.
     */
    private function prepareEmbeddedMetadata(Signer $signer, array $metadata): array
    {
        return [
            'Firmalum_Version' => config('signing.metadata.version', '1.0'),
            'Firmalum_Evidence_ID' => $metadata['evidence_package_uuid'] ?? null,
            'Firmalum_Process_ID' => $signer->signing_process_id,
            'Firmalum_Signer_ID' => $signer->id,
            'Firmalum_Verify_Code' => $metadata['verification_code'] ?? null,
            'Firmalum_Verify_URL' => $metadata['verification_url'] ?? config('app.url'),
            'Firmalum_IP_Hash' => isset($metadata['ip_address']) ? hash('sha256', $metadata['ip_address']) : null,
            'Firmalum_Location' => $metadata['location_summary'] ?? null,
            'Firmalum_Device_FP' => isset($metadata['device_fingerprint']) ? hash('sha256', $metadata['device_fingerprint']) : null,
            'Firmalum_Consent_ID' => $metadata['consent_id'] ?? null,
            'Firmalum_Audit_Chain' => $metadata['audit_chain_hash'] ?? null,
        ];
    }
}
