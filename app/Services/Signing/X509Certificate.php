<?php

declare(strict_types=1);

namespace App\Services\Signing;

use OpenSSLCertificate;

class X509Certificate
{
    private array $parsed;

    public function __construct(
        private readonly OpenSSLCertificate $resource
    ) {
        $this->parsed = openssl_x509_parse($this->resource);
    }

    /**
     * Get the certificate resource.
     */
    public function getResource(): OpenSSLCertificate
    {
        return $this->resource;
    }

    /**
     * Get certificate in PEM format.
     */
    public function getPem(): string
    {
        openssl_x509_export($this->resource, $output);

        return $output;
    }

    /**
     * Get certificate in DER format.
     */
    public function getDer(): string
    {
        $pem = $this->getPem();
        $der = base64_decode(
            str_replace(
                ['-----BEGIN CERTIFICATE-----', '-----END CERTIFICATE-----', "\n", "\r"],
                '',
                $pem
            )
        );

        return $der;
    }

    /**
     * Get subject Distinguished Name.
     */
    public function getSubject(): string
    {
        return $this->formatDN($this->parsed['subject']);
    }

    /**
     * Get subject DN as array.
     */
    public function getSubjectArray(): array
    {
        return $this->parsed['subject'];
    }

    /**
     * Get issuer Distinguished Name.
     */
    public function getIssuer(): string
    {
        return $this->formatDN($this->parsed['issuer']);
    }

    /**
     * Get issuer DN as array.
     */
    public function getIssuerArray(): array
    {
        return $this->parsed['issuer'];
    }

    /**
     * Get issuer DN in format for PKCS#7.
     */
    public function getIssuerDN(): array
    {
        return $this->parsed['issuer'];
    }

    /**
     * Get certificate serial number.
     */
    public function getSerialNumber(): string
    {
        return (string) $this->parsed['serialNumber'];
    }

    /**
     * Get certificate fingerprint.
     */
    public function getFingerprint(string $algorithm = 'sha256'): string
    {
        return openssl_x509_fingerprint($this->resource, $algorithm);
    }

    /**
     * Get certificate valid from date.
     */
    public function getValidFrom(): \DateTimeInterface
    {
        return \DateTimeImmutable::createFromFormat('U', (string) $this->parsed['validFrom_time_t']);
    }

    /**
     * Get certificate valid to date.
     */
    public function getValidTo(): \DateTimeInterface
    {
        return \DateTimeImmutable::createFromFormat('U', (string) $this->parsed['validTo_time_t']);
    }

    /**
     * Check if certificate is currently valid.
     */
    public function isValid(): bool
    {
        $now = time();

        return $now >= $this->parsed['validFrom_time_t']
            && $now <= $this->parsed['validTo_time_t'];
    }

    /**
     * Check if certificate has expired.
     */
    public function isExpired(): bool
    {
        return time() > $this->parsed['validTo_time_t'];
    }

    /**
     * Get days until expiration.
     */
    public function getDaysUntilExpiration(): int
    {
        $now = new \DateTime;
        $validTo = $this->getValidTo();

        return $now->diff($validTo)->days * ($now > $validTo ? -1 : 1);
    }

    /**
     * Get certificate version.
     */
    public function getVersion(): int
    {
        return $this->parsed['version'];
    }

    /**
     * Get certificate extensions.
     */
    public function getExtensions(): array
    {
        return $this->parsed['extensions'] ?? [];
    }

    /**
     * Get specific extension value.
     */
    public function getExtension(string $name): ?string
    {
        return $this->parsed['extensions'][$name] ?? null;
    }

    /**
     * Get certificate purposes.
     */
    public function getPurposes(): array
    {
        return $this->parsed['purposes'] ?? [];
    }

    /**
     * Check if certificate has specific key usage.
     */
    public function hasKeyUsage(string $usage): bool
    {
        $keyUsage = $this->getExtension('keyUsage');

        return $keyUsage && str_contains($keyUsage, $usage);
    }

    /**
     * Get certificate info as array.
     */
    public function toArray(): array
    {
        return [
            'subject' => $this->getSubject(),
            'issuer' => $this->getIssuer(),
            'serial_number' => $this->getSerialNumber(),
            'fingerprint' => $this->getFingerprint(),
            'valid_from' => $this->getValidFrom()->format('c'),
            'valid_to' => $this->getValidTo()->format('c'),
            'is_valid' => $this->isValid(),
            'days_until_expiration' => $this->getDaysUntilExpiration(),
            'version' => $this->getVersion(),
        ];
    }

    /**
     * Format Distinguished Name from array to string.
     */
    private function formatDN(array $dn): string
    {
        $parts = [];

        foreach ($dn as $key => $value) {
            $parts[] = $key.'='.(is_array($value) ? implode(', ', $value) : $value);
        }

        return implode(', ', $parts);
    }
}
