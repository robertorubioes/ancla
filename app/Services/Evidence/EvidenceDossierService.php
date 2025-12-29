<?php

namespace App\Services\Evidence;

use App\Models\AuditTrailEntry;
use App\Models\ConsentRecord;
use App\Models\DeviceFingerprint;
use App\Models\EvidenceDossier;
use App\Models\GeolocationRecord;
use App\Models\IpResolutionRecord;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class EvidenceDossierService
{
    public function __construct(
        private readonly HashingService $hashingService,
        private readonly TsaService $tsaService,
        private readonly AuditTrailService $auditTrailService
    ) {}

    /**
     * Generate a full evidence dossier PDF.
     */
    public function generate(
        Model $signable,
        string $dossierType = 'full_evidence',
        ?int $generatedBy = null
    ): EvidenceDossier {
        $tenant = app('tenant');

        // Generate verification code
        $verificationCode = $this->generateVerificationCode();

        // Collect all evidence data
        $evidenceData = $this->collectEvidenceData($signable);

        // Verify audit trail integrity
        $chainVerification = $this->auditTrailService->verifyChain($signable);

        // Generate QR code
        $qrPath = null;
        if (config('evidence.dossier.include_qr')) {
            $qrPath = $this->generateQrCode($verificationCode, $tenant?->id);
        }

        // Build dossier data
        $dossierData = [
            'verification_code' => $verificationCode,
            'generated_at' => now(),
            'tenant' => $tenant,
            'signable' => $signable,
            'dossier_type' => $dossierType,
            'evidence' => $evidenceData,
            'chain_verification' => $chainVerification,
            'qr_path' => $qrPath,
        ];

        // Generate PDF
        $pdf = $this->generatePdf($dossierData);
        $pdfContent = $pdf->output();

        // Calculate file hash
        $fileHash = $this->hashingService->hashContent($pdfContent);

        // Save PDF to storage
        $filePath = $this->savePdf($pdfContent, $tenant?->id, $verificationCode);
        $fileSize = strlen($pdfContent);

        // Get page count from PDF (approximation)
        $pageCount = $this->estimatePageCount($pdfContent);

        // Sign the dossier with platform key
        $signature = null;
        $signatureAlgorithm = null;
        $signedAt = null;

        if (config('evidence.dossier.platform_signing_key')) {
            $signatureData = $this->signDossier($fileHash);
            $signature = $signatureData['signature'];
            $signatureAlgorithm = $signatureData['algorithm'];
            $signedAt = now();
        }

        // Create dossier record
        $dossier = EvidenceDossier::create([
            'uuid' => Str::uuid(),
            'tenant_id' => $tenant?->id,
            'signable_type' => get_class($signable),
            'signable_id' => $signable->id,
            'dossier_type' => $dossierType,
            'file_path' => $filePath,
            'file_name' => "dossier_{$verificationCode}.pdf",
            'file_size' => $fileSize,
            'file_hash' => $fileHash,
            'page_count' => $pageCount,
            'includes_document' => true,
            'includes_audit_trail' => true,
            'includes_device_info' => ! empty($evidenceData['devices']),
            'includes_geolocation' => ! empty($evidenceData['geolocations']),
            'includes_ip_info' => ! empty($evidenceData['ip_records']),
            'includes_consents' => ! empty($evidenceData['consents']),
            'includes_tsa_tokens' => true,
            'platform_signature' => $signature,
            'signature_algorithm' => $signatureAlgorithm,
            'signed_at' => $signedAt,
            'verification_code' => $verificationCode,
            'verification_url' => $this->getVerificationUrl($verificationCode),
            'verification_qr_path' => $qrPath,
            'audit_entries_count' => count($evidenceData['audit_entries']),
            'devices_count' => count($evidenceData['devices']),
            'geolocations_count' => count($evidenceData['geolocations']),
            'consents_count' => count($evidenceData['consents']),
            'generated_by' => $generatedBy ?? auth()->id(),
            'generated_at' => now(),
        ]);

        // Get TSA timestamp for the dossier
        $tsaToken = $this->tsaService->getTimestamp($fileHash, $dossier);
        $dossier->update(['tsa_token_id' => $tsaToken->id]);

        // Log to audit trail
        $this->auditTrailService->logEvent(
            'evidence.dossier_generated',
            $signable,
            [
                'dossier_id' => $dossier->id,
                'dossier_type' => $dossierType,
                'verification_code' => $verificationCode,
                'file_hash' => $fileHash,
                'page_count' => $pageCount,
                'audit_entries_count' => count($evidenceData['audit_entries']),
            ]
        );

        return $dossier;
    }

    /**
     * Collect all evidence data for a signable.
     */
    private function collectEvidenceData(Model $signable): array
    {
        $auditEntries = AuditTrailEntry::where('auditable_type', get_class($signable))
            ->where('auditable_id', $signable->id)
            ->orderBy('sequence_number', 'asc')
            ->get();

        $devices = DeviceFingerprint::where('signable_type', get_class($signable))
            ->where('signable_id', $signable->id)
            ->get();

        $geolocations = GeolocationRecord::where('signable_type', get_class($signable))
            ->where('signable_id', $signable->id)
            ->get();

        $ipRecords = IpResolutionRecord::where('signable_type', get_class($signable))
            ->where('signable_id', $signable->id)
            ->get();

        $consents = ConsentRecord::where('signable_type', get_class($signable))
            ->where('signable_id', $signable->id)
            ->get();

        return [
            'audit_entries' => $auditEntries,
            'devices' => $devices,
            'geolocations' => $geolocations,
            'ip_records' => $ipRecords,
            'consents' => $consents,
        ];
    }

    /**
     * Generate the PDF document.
     */
    private function generatePdf(array $dossierData): \Barryvdh\DomPDF\PDF
    {
        $pdf = Pdf::loadView('evidence.dossier-pdf', $dossierData);

        $pdf->setPaper(
            config('evidence.dossier.paper_size', 'A4'),
            config('evidence.dossier.orientation', 'portrait')
        );

        return $pdf;
    }

    /**
     * Save PDF to storage.
     */
    private function savePdf(string $content, ?int $tenantId, string $verificationCode): string
    {
        $path = sprintf(
            '%s/%s/%s/dossier_%s.pdf',
            config('evidence.dossier.path_prefix', 'evidence-dossiers'),
            $tenantId ?? 'global',
            now()->format('Y/m'),
            $verificationCode
        );

        $disk = config('evidence.dossier.storage_disk', 'local');
        Storage::disk($disk)->put($path, $content);

        return $path;
    }

    /**
     * Generate verification code.
     */
    private function generateVerificationCode(): string
    {
        do {
            $code = strtoupper(Str::random(4).'-'.Str::random(4).'-'.Str::random(4));
        } while (EvidenceDossier::where('verification_code', $code)->exists());

        return $code;
    }

    /**
     * Generate QR code for verification.
     */
    private function generateQrCode(string $verificationCode, ?int $tenantId): string
    {
        $url = $this->getVerificationUrl($verificationCode);
        $size = config('evidence.dossier.qr_size', 200);

        $qrContent = QrCode::format('png')
            ->size($size)
            ->margin(1)
            ->generate($url);

        $path = sprintf(
            '%s/%s/qr/%s.png',
            config('evidence.dossier.path_prefix', 'evidence-dossiers'),
            $tenantId ?? 'global',
            $verificationCode
        );

        $disk = config('evidence.dossier.storage_disk', 'local');
        Storage::disk($disk)->put($path, $qrContent);

        return $path;
    }

    /**
     * Get verification URL.
     */
    private function getVerificationUrl(string $verificationCode): string
    {
        $baseUrl = config('evidence.dossier.verification_base_url');

        if ($baseUrl) {
            return rtrim($baseUrl, '/').'/'.$verificationCode;
        }

        return route('evidence.verify', ['code' => $verificationCode]);
    }

    /**
     * Sign dossier with platform key.
     */
    private function signDossier(string $hash): array
    {
        $key = config('evidence.dossier.platform_signing_key');

        // Use HMAC-SHA256 for signing
        $signature = hash_hmac('sha256', $hash, $key);

        return [
            'signature' => $signature,
            'algorithm' => 'HMAC-SHA256',
        ];
    }

    /**
     * Verify dossier signature.
     */
    public function verifySignature(EvidenceDossier $dossier): bool
    {
        if (! $dossier->platform_signature) {
            return true; // No signature to verify
        }

        $key = config('evidence.dossier.platform_signing_key');
        $expectedSignature = hash_hmac('sha256', $dossier->file_hash, $key);

        return hash_equals($expectedSignature, $dossier->platform_signature);
    }

    /**
     * Estimate page count from PDF content.
     */
    private function estimatePageCount(string $pdfContent): int
    {
        // Simple estimation based on /Page occurrences
        preg_match_all('/\/Type\s*\/Page[^s]/i', $pdfContent, $matches);

        return max(1, count($matches[0]));
    }

    /**
     * Verify dossier integrity.
     */
    public function verify(string $verificationCode): array
    {
        $dossier = EvidenceDossier::byVerificationCode($verificationCode)->first();

        if (! $dossier) {
            return [
                'valid' => false,
                'error' => 'Dossier not found',
                'code' => $verificationCode,
            ];
        }

        $errors = [];

        // Verify file exists and hash matches
        $disk = config('evidence.dossier.storage_disk', 'local');
        if (! Storage::disk($disk)->exists($dossier->file_path)) {
            $errors[] = 'Dossier file not found';
        } else {
            $content = Storage::disk($disk)->get($dossier->file_path);
            $currentHash = $this->hashingService->hashContent($content);

            if ($currentHash !== $dossier->file_hash) {
                $errors[] = 'File hash mismatch - document may have been altered';
            }
        }

        // Verify platform signature
        if ($dossier->platform_signature && ! $this->verifySignature($dossier)) {
            $errors[] = 'Platform signature verification failed';
        }

        // Verify TSA token
        if ($dossier->tsa_token_id) {
            $tsaToken = $dossier->tsaToken;
            if ($tsaToken && ! $this->tsaService->verifyToken($tsaToken)) {
                $errors[] = 'TSA token verification failed';
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'dossier' => [
                'uuid' => $dossier->uuid,
                'verification_code' => $dossier->verification_code,
                'type' => $dossier->dossier_type,
                'generated_at' => $dossier->generated_at->toIso8601String(),
                'file_hash' => $dossier->file_hash,
                'page_count' => $dossier->page_count,
                'has_signature' => $dossier->platform_signature !== null,
                'has_tsa' => $dossier->tsa_token_id !== null,
                'stats' => $dossier->stats_summary,
            ],
            'verified_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Get dossier content for download.
     */
    public function getContent(EvidenceDossier $dossier): string
    {
        $dossier->recordDownload();

        return $dossier->getContent();
    }

    /**
     * Get dossiers for a signable.
     */
    public function getForSignable(Model $signable): \Illuminate\Database\Eloquent\Collection
    {
        return EvidenceDossier::where('signable_type', get_class($signable))
            ->where('signable_id', $signable->id)
            ->orderBy('generated_at', 'desc')
            ->get();
    }

    /**
     * Get latest dossier for a signable.
     */
    public function getLatest(Model $signable): ?EvidenceDossier
    {
        return EvidenceDossier::where('signable_type', get_class($signable))
            ->where('signable_id', $signable->id)
            ->orderBy('generated_at', 'desc')
            ->first();
    }

    /**
     * Regenerate dossier with latest evidence.
     */
    public function regenerate(EvidenceDossier $dossier): EvidenceDossier
    {
        return $this->generate(
            $dossier->signable,
            $dossier->dossier_type,
            auth()->id()
        );
    }
}
