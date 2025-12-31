<?php

declare(strict_types=1);

namespace App\Livewire\Signing;

use App\Models\Signer;
use App\Services\Otp\OtpException;
use App\Services\Otp\OtpService;
use App\Services\Signing\SignatureException;
use App\Services\Signing\SignatureService;
use App\Services\Signing\SigningAccessException;
use App\Services\Signing\SigningAccessService;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Public signing page component.
 *
 * Handles the signer's access to the signing link and displays
 * the document information and signing interface.
 */
class SigningPage extends Component
{
    use WithFileUploads;

    public string $token;

    public ?int $signerId = null;

    public ?string $errorMessage = null;

    public int $errorCode = 0;

    public bool $isLoading = true;

    public bool $otpRequested = false;

    public bool $otpVerified = false;

    public string $otpCode = '';

    public ?string $otpMessage = null;

    public bool $otpError = false;

    // Signature fields
    public string $signatureType = 'draw';

    public ?string $signatureData = null;

    public string $typedSignature = '';

    public $uploadedSignature;

    public bool $consentGiven = false;

    public bool $isSigning = false;

    public ?string $signatureMessage = null;

    public bool $signatureError = false;

    // New step-based flow properties
    public bool $showSignaturePad = false;

    public bool $documentRead = false;

    public bool $hasReadDocument = false;

    /**
     * Proceed to the OTP verification step after reading the document.
     */
    public function proceedToVerification(): void
    {
        if ($this->documentRead) {
            $this->hasReadDocument = true;
        }
    }

    /**
     * Go back to the document reading step from OTP.
     */
    public function backToReading(): void
    {
        $this->hasReadDocument = false;
    }

    /**
     * Mount the component and validate access.
     */
    public function mount(string $token, SigningAccessService $accessService): void
    {
        $this->token = $token;

        try {
            $result = $accessService->validateAccess($token);
            $this->signerId = $result->signer->id;
            $this->isLoading = false;
            
            // Store signer token in session for document preview access
            session(['signer_token' => $token]);
        } catch (SigningAccessException $e) {
            $this->errorMessage = $e->getMessage();
            $this->errorCode = $e->getCode();
            $this->isLoading = false;

            // Handle special case: token not found = 404
            if ($e->getCode() === SigningAccessException::CODE_TOKEN_NOT_FOUND) {
                abort(404, $e->getMessage());
            }
        }
    }

    /**
     * Request OTP verification code.
     */
    public function requestOtp(OtpService $otpService): void
    {
        $this->resetOtpMessages();

        try {
            $signer = $this->signer;

            if (! $signer) {
                $this->otpError = true;
                $this->otpMessage = 'Signer not found.';

                return;
            }

            // Generate and send OTP
            $result = $otpService->generate($signer);

            if ($result->isSuccess()) {
                $this->otpRequested = true;
                $this->otpError = false;
                $this->otpMessage = '📧 Verification code sent to your email';
                $this->dispatch('otp-requested');
            } else {
                $this->otpError = true;
                $this->otpMessage = $result->message ?? 'Failed to send verification code.';
            }
        } catch (OtpException $e) {
            $this->otpError = true;
            $this->otpMessage = $e->getMessage();
        }
    }

    /**
     * Verify OTP code.
     */
    public function verifyOtp(OtpService $otpService): void
    {
        $this->resetOtpMessages();

        // Validate input
        if (empty($this->otpCode)) {
            $this->otpError = true;
            $this->otpMessage = 'Please enter the verification code.';

            return;
        }

        if (strlen($this->otpCode) !== 6) {
            $this->otpError = true;
            $this->otpMessage = 'Verification code must be 6 digits.';

            return;
        }

        try {
            $signer = $this->signer;

            if (! $signer) {
                $this->otpError = true;
                $this->otpMessage = 'Signer not found.';

                return;
            }

            // Verify OTP
            if ($otpService->verify($signer, $this->otpCode)) {
                $this->otpVerified = true;
                $this->otpError = false;
                $this->otpMessage = '✅ Verified successfully! You can now sign the document.';
                $this->otpCode = '';
                $this->dispatch('otp-verified');
            }
        } catch (OtpException $e) {
            $this->otpError = true;
            $this->otpMessage = $e->getMessage();
        }
    }

    /**
     * Reset OTP messages.
     */
    private function resetOtpMessages(): void
    {
        $this->otpMessage = null;
        $this->otpError = false;
    }

    /**
     * Set signature type.
     */
    public function setSignatureType(string $type): void
    {
        $this->signatureType = $type;
        $this->signatureData = null;
        $this->typedSignature = '';
        $this->uploadedSignature = null;
        $this->resetSignatureMessages();
    }

    /**
     * Clear signature data.
     */
    public function clearSignature(): void
    {
        $this->signatureData = null;
        $this->typedSignature = '';
        $this->uploadedSignature = null;
        $this->resetSignatureMessages();
        $this->dispatch('signature-cleared');
    }

    /**
     * Update typed signature preview.
     */
    public function updatedTypedSignature(): void
    {
        if (! empty($this->typedSignature)) {
            $this->signatureData = $this->typedSignature;
        }
    }

    /**
     * Handle uploaded signature file.
     */
    public function updatedUploadedSignature(): void
    {
        $this->resetSignatureMessages();

        if (! $this->uploadedSignature) {
            return;
        }

        // Validate file
        try {
            $this->validate([
                'uploadedSignature' => 'required|image|mimes:png,jpg,jpeg|max:2048',
            ]);

            // Convert to base64 data URL
            $imageData = file_get_contents($this->uploadedSignature->getRealPath());
            $mimeType = $this->uploadedSignature->getMimeType();
            $base64 = base64_encode($imageData);
            $this->signatureData = "data:{$mimeType};base64,{$base64}";
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->signatureError = true;
            $this->signatureMessage = $e->validator->errors()->first('uploadedSignature');
            $this->uploadedSignature = null;
        }
    }

    /**
     * Sign the document.
     */
    public function signDocument(SignatureService $signatureService): void
    {
        $this->resetSignatureMessages();
        $this->isSigning = true;

        try {
            $signer = $this->signer;

            if (! $signer) {
                throw new \Exception('Signer not found.');
            }

            // Validate consent
            if (! $this->consentGiven) {
                throw SignatureException::consentRequired();
            }

            // Validate signature data exists
            if (empty($this->signatureData)) {
                throw SignatureException::invalidData('Please create or upload a signature first.');
            }

            // Process signature
            $result = $signatureService->processSignature(
                signer: $signer,
                type: $this->signatureType,
                data: $this->signatureData,
                consentGiven: $this->consentGiven,
                metadata: [
                    'typed_text' => $this->signatureType === 'type' ? $this->typedSignature : null,
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                ]
            );

            if ($result->isSuccess()) {
                $this->signatureError = false;
                $this->signatureMessage = '✅ Document signed successfully!';
                $this->dispatch('document-signed');

                // Reset form
                $this->signatureData = null;
                $this->typedSignature = '';
                $this->uploadedSignature = null;
                $this->consentGiven = false;
            }
        } catch (SignatureException $e) {
            $this->signatureError = true;
            $this->signatureMessage = $e->getMessage();
        } catch (\Exception $e) {
            $this->signatureError = true;
            $this->signatureMessage = 'An error occurred while signing the document. Please try again.';
        } finally {
            $this->isSigning = false;
        }
    }

    /**
     * Reset signature messages.
     */
    private function resetSignatureMessages(): void
    {
        $this->signatureMessage = null;
        $this->signatureError = false;
    }

    /**
     * Decline signing (reject the document).
     *
     * This will be implemented in a future story.
     */
    public function declineSignature(): void
    {
        // TODO: Implement rejection flow
        $this->dispatch('signature-declined');
    }

    /**
     * Proceed to the signature pad after OTP verification.
     */
    public function proceedToSign(): void
    {
        if ($this->hasVerifiedOtp) {
            $this->showSignaturePad = true;
        }
    }

    /**
     * Go back to the OTP verification step.
     */
    public function backToVerification(): void
    {
        $this->showSignaturePad = false;
    }

    /**
     * Use the typed signature as signature data.
     */
    public function useTypedSignature(): void
    {
        if (! empty($this->typedSignature)) {
            // Create a simple text-based signature indicator
            // The actual conversion to image happens server-side when signing
            $this->signatureData = 'typed:' . $this->typedSignature;
        }
    }

    /**
     * Render the component.
     */
    #[Layout('layouts.signing')]
    public function render(): View
    {
        return view('livewire.signing.signing-page');
    }

    /**
     * Get the signer information.
     */
    public function getSignerProperty(): ?Signer
    {
        if (! $this->signerId) {
            return null;
        }

        return Signer::with(['signingProcess.document', 'signingProcess.createdBy', 'signingProcess.signers', 'otpCodes'])
            ->find($this->signerId);
    }

    /**
     * Check if signer has verified OTP.
     */
    public function getHasVerifiedOtpProperty(): bool
    {
        if ($this->otpVerified) {
            return true;
        }

        $otpService = app(OtpService::class);

        return $this->signer ? $otpService->hasVerifiedOtp($this->signer) : false;
    }

    /**
     * Get the process information.
     */
    public function getProcessProperty(): mixed
    {
        return $this->signer?->signingProcess;
    }

    /**
     * Get the document information.
     */
    public function getDocumentProperty(): mixed
    {
        return $this->process?->document;
    }

    /**
     * Get the promoter (creator) information.
     */
    public function getPromoterProperty(): mixed
    {
        return $this->process?->createdBy;
    }

    /**
     * Check if the signer can proceed to sign.
     */
    public function getCanSignProperty(): bool
    {
        if (! $this->signer) {
            return false;
        }

        // Already signed?
        if ($this->signer->hasSigned()) {
            return false;
        }

        // Process must be active
        $process = $this->process;
        if (! $process || ! in_array($process->status, ['sent', 'in_progress'])) {
            return false;
        }

        // If parallel, can sign
        if ($process->isParallel()) {
            return true;
        }

        // If sequential, check previous signers
        if ($process->isSequential()) {
            $previousSigners = $process->signers()
                ->where('order', '<', $this->signer->order)
                ->get();

            foreach ($previousSigners as $prev) {
                if (! $prev->hasSigned()) {
                    return false;
                }
            }

            return true;
        }

        return false;
    }

    /**
     * Check if the signer has already signed.
     */
    public function getAlreadySignedProperty(): bool
    {
        return $this->signer?->hasSigned() ?? false;
    }

    /**
     * Check if there's an error.
     */
    public function getHasErrorProperty(): bool
    {
        return $this->errorMessage !== null;
    }

    /**
     * Get the name of the signer waiting for (sequential).
     */
    public function getWaitingForProperty(): ?string
    {
        if (! $this->signer || $this->canSign) {
            return null;
        }

        $process = $this->process;
        if (! $process || ! $process->isSequential()) {
            return null;
        }

        $previousSigner = $process->signers()
            ->where('order', '<', $this->signer->order)
            ->whereNotIn('status', ['signed'])
            ->orderBy('order')
            ->first();

        return $previousSigner?->name;
    }

    /**
     * Get total signers count.
     */
    public function getTotalSignersProperty(): int
    {
        return $this->process?->getTotalSignersCount() ?? 0;
    }

    /**
     * Get completed signers count.
     */
    public function getCompletedSignersProperty(): int
    {
        return $this->process?->getCompletedSignersCount() ?? 0;
    }
}
