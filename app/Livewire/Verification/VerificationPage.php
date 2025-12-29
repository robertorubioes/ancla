<?php

declare(strict_types=1);

namespace App\Livewire\Verification;

use App\Services\Verification\PublicVerificationService;
use App\Services\Verification\VerificationResult;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Livewire component for the public verification page.
 *
 * Allows users to verify documents by entering a verification code
 * or scanning a QR code.
 *
 * @see ADR-007 in docs/architecture/adr-007-sprint3-retention-verification-upload.md
 */
#[Layout('layouts.public')]
class VerificationPage extends Component
{
    /**
     * The verification code entered by the user.
     */
    #[Url(as: 'code')]
    public string $code = '';

    /**
     * The hash for verification by hash.
     */
    public string $hash = '';

    /**
     * The verification method: 'code' or 'hash'.
     */
    public string $method = 'code';

    /**
     * The verification result.
     */
    public ?array $result = null;

    /**
     * Whether verification is in progress.
     */
    public bool $loading = false;

    /**
     * Error message if any.
     */
    public ?string $error = null;

    /**
     * Details from the verification.
     */
    public ?array $details = null;

    /**
     * Mount the component with optional initial code.
     */
    public function mount(?string $code = null): void
    {
        if ($code) {
            $this->code = $code;
            $this->verifyByCode();
        }
    }

    /**
     * Verify the document by code.
     */
    public function verifyByCode(): void
    {
        $this->validate([
            'code' => ['required', 'string', 'min:6', 'max:20'],
        ]);

        $this->loading = true;
        $this->error = null;
        $this->result = null;
        $this->details = null;

        try {
            $service = app(PublicVerificationService::class);
            $verificationResult = $service->verifyByCode($this->code);

            $this->result = $this->formatResult($verificationResult);

            // Get additional details if verification was successful
            if ($verificationResult->isValid) {
                $this->details = $service->getVerificationDetails($this->code);
            }

        } catch (\Exception $e) {
            $this->error = 'Verification failed. Please try again.';
            report($e);
        }

        $this->loading = false;
    }

    /**
     * Verify the document by hash.
     */
    public function verifyByHash(): void
    {
        $this->validate([
            'hash' => ['required', 'string', 'regex:/^[a-fA-F0-9]{64}$/'],
        ]);

        $this->loading = true;
        $this->error = null;
        $this->result = null;
        $this->details = null;

        try {
            $service = app(PublicVerificationService::class);
            $verificationResult = $service->verifyByHash($this->hash);

            $this->result = $this->formatResult($verificationResult);

            // If we found a verification code, get details
            if ($verificationResult->isValid && $verificationResult->getMetadata('verification_code')) {
                $this->details = $service->getVerificationDetails(
                    $verificationResult->getMetadata('verification_code')
                );
            }

        } catch (\Exception $e) {
            $this->error = 'Verification failed. Please try again.';
            report($e);
        }

        $this->loading = false;
    }

    /**
     * Switch verification method.
     */
    public function switchMethod(string $method): void
    {
        $this->method = $method;
        $this->result = null;
        $this->error = null;
        $this->details = null;
    }

    /**
     * Reset the form.
     */
    public function resetForm(): void
    {
        $this->code = '';
        $this->hash = '';
        $this->result = null;
        $this->error = null;
        $this->details = null;
    }

    /**
     * Get the QR code URL for the current verification code.
     */
    public function getQrCodeUrl(): ?string
    {
        if (! $this->code || ! $this->result || ! ($this->result['valid'] ?? false)) {
            return null;
        }

        return route('api.public.verify.qr', ['code' => $this->code]);
    }

    /**
     * Download evidence dossier.
     */
    public function downloadEvidence(): void
    {
        if (! $this->code || ! $this->result || ! ($this->result['valid'] ?? false)) {
            $this->error = 'Cannot download evidence for invalid or missing verification.';

            return;
        }

        // Redirect to download endpoint
        $this->redirect(route('api.public.verify.evidence', ['code' => $this->code]));
    }

    /**
     * Format the verification result for display.
     */
    private function formatResult(VerificationResult $result): array
    {
        return [
            'valid' => $result->isValid,
            'confidence_score' => $result->confidenceScore,
            'confidence_level' => $result->confidenceLevel,
            'error' => $result->errorMessage,
            'document' => $result->document ? [
                'filename' => $result->document->original_filename,
                'hash' => $result->document->sha256_hash,
                'pages' => $result->document->page_count,
                'size' => $result->document->file_size,
                'uploaded_at' => $result->document->created_at?->format('Y-m-d H:i:s'),
            ] : null,
            'checks' => array_map(fn ($check) => [
                'name' => $this->formatCheckName($check['name']),
                'passed' => $check['passed'],
                'message' => $check['message'] ?? null,
            ], $result->checks),
        ];
    }

    /**
     * Format a check name for display.
     */
    private function formatCheckName(string $name): string
    {
        return match ($name) {
            'document_hash' => 'Document Integrity',
            'chain_hash' => 'Audit Trail',
            'tsa_timestamp' => 'TSA Timestamp',
            'device_fingerprint' => 'Device Fingerprint',
            'geolocation' => 'Geolocation',
            'ip_resolution' => 'IP Resolution',
            'consent_records' => 'Consent Records',
            default => ucwords(str_replace('_', ' ', $name)),
        };
    }

    /**
     * Render the component.
     */
    public function render(): View
    {
        return view('livewire.verification.verification-page');
    }
}
