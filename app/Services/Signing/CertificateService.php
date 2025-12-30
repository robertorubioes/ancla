<?php

declare(strict_types=1);

namespace App\Services\Signing;

use Illuminate\Support\Facades\Log;

class CertificateService
{
    private array $config;

    public function __construct()
    {
        $this->config = config('signing.certificate');
    }

    /**
     * Load the platform certificate.
     */
    public function loadCertificate(): X509Certificate
    {
        $certPath = $this->getCertificatePath();

        if (! file_exists($certPath)) {
            throw PdfSignatureException::certificateLoadFailed("Certificate not found at: {$certPath}");
        }

        $certContent = file_get_contents($certPath);
        $cert = openssl_x509_read($certContent);

        if (! $cert) {
            throw PdfSignatureException::certificateLoadFailed(
                'Invalid certificate format: '.openssl_error_string()
            );
        }

        $certificate = new X509Certificate($cert);

        // Validate certificate is not expired
        if ($certificate->isExpired()) {
            throw PdfSignatureException::certificateLoadFailed(
                'Certificate has expired on '.$certificate->getValidTo()->format('Y-m-d')
            );
        }

        // Warn if certificate expires soon (< 30 days)
        $daysUntilExpiration = $certificate->getDaysUntilExpiration();
        if ($daysUntilExpiration < 30 && $daysUntilExpiration > 0) {
            Log::warning('Certificate expires soon', [
                'days_remaining' => $daysUntilExpiration,
                'expires_at' => $certificate->getValidTo()->format('Y-m-d'),
            ]);
        }

        return $certificate;
    }

    /**
     * Load the private key.
     */
    public function getPrivateKey(): PrivateKey
    {
        $keyPath = $this->getPrivateKeyPath();
        $password = $this->getPrivateKeyPassword();

        if (! file_exists($keyPath)) {
            throw PdfSignatureException::privateKeyLoadFailed("Private key not found at: {$keyPath}");
        }

        $keyContent = file_get_contents($keyPath);
        $key = openssl_pkey_get_private($keyContent, $password);

        if (! $key) {
            throw PdfSignatureException::privateKeyLoadFailed(
                'Invalid private key or wrong password: '.openssl_error_string()
            );
        }

        $privateKey = new PrivateKey($key);

        // Validate key meets minimum size requirements
        $minKeySize = config('signing.security.rsa_key_size', 2048);
        if (! $privateKey->meetsMinimumSize($minKeySize)) {
            throw PdfSignatureException::privateKeyLoadFailed(
                "Private key size ({$privateKey->getBits()} bits) is below minimum required ({$minKeySize} bits)"
            );
        }

        return $privateKey;
    }

    /**
     * Load certificate from PKCS#12 file.
     */
    public function loadFromPkcs12(string $password): array
    {
        $pkcs12Path = $this->getPkcs12Path();

        if (! $pkcs12Path || ! file_exists($pkcs12Path)) {
            throw PdfSignatureException::certificateLoadFailed('PKCS#12 file not found');
        }

        $pkcs12Content = file_get_contents($pkcs12Path);
        $certs = [];

        if (! openssl_pkcs12_read($pkcs12Content, $certs, $password)) {
            throw PdfSignatureException::certificateLoadFailed(
                'Failed to read PKCS#12 file: '.openssl_error_string()
            );
        }

        return [
            'certificate' => new X509Certificate(openssl_x509_read($certs['cert'])),
            'private_key' => new PrivateKey(openssl_pkey_get_private($certs['pkey'])),
            'ca_chain' => $certs['extracerts'] ?? [],
        ];
    }

    /**
     * Check if certificate is revoked (OCSP/CRL check).
     *
     * @param  string  $serialNumber  Certificate serial number
     */
    public function checkRevocation(string $serialNumber): bool
    {
        // Simplified implementation for MVP
        // In production, implement OCSP responder query or CRL check

        if (! config('signing.validation.check_revocation', false)) {
            return true; // Assume valid if revocation check disabled
        }

        // TODO: Implement OCSP check
        // 1. Get OCSP responder URL from certificate
        // 2. Build OCSP request
        // 3. Send HTTP request to OCSP responder
        // 4. Parse and validate OCSP response

        // For self-signed certificates, skip revocation check
        if ($this->isSelfSigned()) {
            return true;
        }

        Log::info('Certificate revocation check skipped (not implemented)', [
            'serial_number' => $serialNumber,
        ]);

        return true;
    }

    /**
     * Validate certificate chain.
     */
    public function validateChain(X509Certificate $cert): bool
    {
        if (! config('signing.validation.verify_certificate_chain', true)) {
            return true;
        }

        // If CA bundle is configured, validate chain
        $caBundlePath = $this->getCaBundlePath();

        if ($caBundlePath && file_exists($caBundlePath)) {
            $result = openssl_x509_checkpurpose(
                $cert->getResource(),
                X509_PURPOSE_SMIME_SIGN,
                [$caBundlePath]
            );

            return $result === true || $result === 1;
        }

        // For self-signed certificates, no chain validation needed
        return true;
    }

    /**
     * Get certificate information.
     */
    public function getCertificateInfo(): array
    {
        try {
            $certificate = $this->loadCertificate();
            $privateKey = $this->getPrivateKey();

            return [
                'certificate' => $certificate->toArray(),
                'private_key' => $privateKey->toArray(),
                'is_valid' => $certificate->isValid(),
                'is_expired' => $certificate->isExpired(),
                'self_signed' => $this->isSelfSigned(),
            ];
        } catch (\Exception $e) {
            return [
                'error' => $e->getMessage(),
                'is_valid' => false,
            ];
        }
    }

    /**
     * Check if using self-signed certificate.
     */
    public function isSelfSigned(): bool
    {
        try {
            $certificate = $this->loadCertificate();
            $subject = $certificate->getSubject();
            $issuer = $certificate->getIssuer();

            return $subject === $issuer;
        } catch (\Exception) {
            return false;
        }
    }

    /**
     * Get full path to certificate file.
     */
    private function getCertificatePath(): string
    {
        $path = $this->config['cert_path'];

        // If path is absolute, return as-is
        if ($this->isAbsolutePath($path)) {
            return $path;
        }

        // Otherwise, resolve relative to base path
        return base_path($path);
    }

    /**
     * Get full path to private key file.
     */
    private function getPrivateKeyPath(): string
    {
        $path = $this->config['key_path'];

        if ($this->isAbsolutePath($path)) {
            return $path;
        }

        return base_path($path);
    }

    /**
     * Get full path to PKCS#12 file.
     */
    private function getPkcs12Path(): ?string
    {
        $path = $this->config['pkcs12_path'];

        if (! $path) {
            return null;
        }

        if ($this->isAbsolutePath($path)) {
            return $path;
        }

        return base_path($path);
    }

    /**
     * Get full path to CA bundle file.
     */
    private function getCaBundlePath(): ?string
    {
        $path = $this->config['ca_bundle_path'];

        if (! $path) {
            return null;
        }

        if ($this->isAbsolutePath($path)) {
            return $path;
        }

        return base_path($path);
    }

    /**
     * Get private key password.
     */
    private function getPrivateKeyPassword(): ?string
    {
        return $this->config['key_password'];
    }

    /**
     * Check if path is absolute.
     */
    private function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, '/') || preg_match('/^[a-zA-Z]:\\\/', $path);
    }
}
