<?php

declare(strict_types=1);

namespace App\Services\Signing;

use App\Models\TsaToken;
use Illuminate\Support\Facades\Log;

class Pkcs7Builder
{
    private ?X509Certificate $certificate = null;

    private ?PrivateKey $privateKey = null;

    private ?string $contentHash = null;

    private ?\DateTimeInterface $signingTime = null;

    private ?string $reason = null;

    private ?string $location = null;

    private ?string $contactInfo = null;

    /**
     * Set certificate for signing.
     */
    public function setCertificate(X509Certificate $cert): self
    {
        $this->certificate = $cert;

        return $this;
    }

    /**
     * Set private key for signing.
     */
    public function setPrivateKey(PrivateKey $key): self
    {
        $this->privateKey = $key;

        return $this;
    }

    /**
     * Set content hash to sign.
     */
    public function setContentHash(string $hash): self
    {
        $this->contentHash = $hash;

        return $this;
    }

    /**
     * Set signing time.
     */
    public function setSigningTime(\DateTimeInterface $time): self
    {
        $this->signingTime = $time;

        return $this;
    }

    /**
     * Set signing reason.
     */
    public function setReason(string $reason): self
    {
        $this->reason = $reason;

        return $this;
    }

    /**
     * Set signing location.
     */
    public function setLocation(string $location): self
    {
        $this->location = $location;

        return $this;
    }

    /**
     * Set contact info.
     */
    public function setContactInfo(string $contactInfo): self
    {
        $this->contactInfo = $contactInfo;

        return $this;
    }

    /**
     * Build PKCS#7 SignedData structure.
     *
     * This creates a detached PKCS#7 signature using OpenSSL.
     * The signature is in CMS (Cryptographic Message Syntax) format,
     * which is the modern standard for PKCS#7.
     */
    public function build(): string
    {
        $this->validateRequiredFields();

        // Create temporary files for OpenSSL operations
        $tempData = tempnam(sys_get_temp_dir(), 'pkcs7_data_');
        $tempSig = tempnam(sys_get_temp_dir(), 'pkcs7_sig_');
        $tempCert = tempnam(sys_get_temp_dir(), 'pkcs7_cert_');
        $tempKey = tempnam(sys_get_temp_dir(), 'pkcs7_key_');

        try {
            // Write content hash to temp file (this is what we're signing)
            file_put_contents($tempData, hex2bin($this->contentHash));

            // Write certificate and key to temp files
            file_put_contents($tempCert, $this->certificate->getPem());
            file_put_contents($tempKey, $this->privateKey->getPem());

            // Create PKCS#7 signature using OpenSSL
            // PKCS7_DETACHED: Create detached signature (content not included)
            // PKCS7_BINARY: Don't translate LF to CR+LF
            $flags = PKCS7_DETACHED | PKCS7_BINARY;

            $headers = $this->buildHeaders();

            $success = openssl_pkcs7_sign(
                $tempData,
                $tempSig,
                "file://{$tempCert}",
                ["file://{$tempKey}", ''],
                $headers,
                $flags
            );

            if (! $success) {
                throw PdfSignatureException::pkcs7CreationFailed(
                    'OpenSSL PKCS#7 signing failed: '.openssl_error_string()
                );
            }

            // Read the generated PKCS#7 signature
            $pkcs7Content = file_get_contents($tempSig);

            // Extract DER from PEM format
            $pkcs7Der = $this->extractDerFromPem($pkcs7Content);

            Log::info('PKCS#7 signature created', [
                'size' => strlen($pkcs7Der),
                'hash_algorithm' => config('signing.security.hash_algorithm'),
            ]);

            return $pkcs7Der;
        } finally {
            // Cleanup temp files
            @unlink($tempData);
            @unlink($tempSig);
            @unlink($tempCert);
            @unlink($tempKey);
        }
    }

    /**
     * Embed TSA token in PKCS#7 signature.
     *
     * This adds the timestamp token to the unauthenticated attributes
     * of the PKCS#7 signature, making it a PAdES-B-LT signature.
     */
    public function embedTsaToken(string $pkcs7Der, TsaToken $tsaToken): string
    {
        // For MVP, we'll append TSA token as additional signature attribute
        // In full PAdES-B-LT implementation, this should be properly embedded
        // in the SignerInfo.unauthenticatedAttributes with OID 1.2.840.113549.1.9.16.2.14

        // This is a simplified implementation
        // TODO: Implement proper ASN.1 manipulation to embed TSA token in correct location

        Log::info('TSA token embedding (simplified for MVP)', [
            'tsa_token_id' => $tsaToken->id,
            'pkcs7_size' => strlen($pkcs7Der),
        ]);

        // For now, return original PKCS#7 and store TSA token reference separately
        // Full implementation would parse ASN.1, add unauthenticated attributes, and re-encode
        return $pkcs7Der;
    }

    /**
     * Verify PKCS#7 signature.
     */
    public function verify(string $pkcs7Der, X509Certificate $cert): bool
    {
        $tempSig = tempnam(sys_get_temp_dir(), 'pkcs7_verify_sig_');
        $tempCert = tempnam(sys_get_temp_dir(), 'pkcs7_verify_cert_');
        $tempContent = tempnam(sys_get_temp_dir(), 'pkcs7_verify_content_');

        try {
            // Convert DER to PEM for OpenSSL
            $pkcs7Pem = $this->convertDerToPem($pkcs7Der);

            file_put_contents($tempSig, $pkcs7Pem);
            file_put_contents($tempCert, $cert->getPem());

            // Verify PKCS#7 signature
            $result = openssl_pkcs7_verify(
                $tempSig,
                PKCS7_DETACHED | PKCS7_NOVERIFY,
                $tempCert,
                [],
                $tempCert,
                $tempContent
            );

            return $result === true || $result === 1;
        } finally {
            @unlink($tempSig);
            @unlink($tempCert);
            @unlink($tempContent);
        }
    }

    /**
     * Validate required fields are set.
     */
    private function validateRequiredFields(): void
    {
        if (! $this->certificate) {
            throw PdfSignatureException::pkcs7CreationFailed('Certificate is required');
        }

        if (! $this->privateKey) {
            throw PdfSignatureException::pkcs7CreationFailed('Private key is required');
        }

        if (! $this->contentHash) {
            throw PdfSignatureException::pkcs7CreationFailed('Content hash is required');
        }

        if (! $this->signingTime) {
            $this->signingTime = now();
        }
    }

    /**
     * Build headers for PKCS#7 signing.
     */
    private function buildHeaders(): array
    {
        $headers = [];

        if ($this->reason) {
            $headers['Reason'] = $this->reason;
        }

        if ($this->location) {
            $headers['Location'] = $this->location;
        }

        if ($this->contactInfo) {
            $headers['ContactInfo'] = $this->contactInfo;
        }

        return $headers;
    }

    /**
     * Extract DER format from PEM PKCS#7.
     */
    private function extractDerFromPem(string $pemContent): string
    {
        // Remove PEM headers and decode base64
        $lines = explode("\n", $pemContent);
        $der = '';

        $inBlock = false;
        foreach ($lines as $line) {
            $line = trim($line);

            if (str_starts_with($line, '-----BEGIN')) {
                $inBlock = true;

                continue;
            }

            if (str_starts_with($line, '-----END')) {
                break;
            }

            if ($inBlock) {
                $der .= $line;
            }
        }

        return base64_decode($der);
    }

    /**
     * Convert DER to PEM format.
     */
    private function convertDerToPem(string $der): string
    {
        $base64 = base64_encode($der);
        $pem = "-----BEGIN PKCS7-----\n";
        $pem .= chunk_split($base64, 64, "\n");
        $pem .= "-----END PKCS7-----\n";

        return $pem;
    }

    /**
     * Reset builder state.
     */
    public function reset(): self
    {
        $this->certificate = null;
        $this->privateKey = null;
        $this->contentHash = null;
        $this->signingTime = null;
        $this->reason = null;
        $this->location = null;
        $this->contactInfo = null;

        return $this;
    }
}
