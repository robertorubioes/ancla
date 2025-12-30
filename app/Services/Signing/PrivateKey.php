<?php

declare(strict_types=1);

namespace App\Services\Signing;

use OpenSSLAsymmetricKey;

class PrivateKey
{
    private array $details;

    public function __construct(
        private readonly OpenSSLAsymmetricKey $resource
    ) {
        $this->details = openssl_pkey_get_details($this->resource);
    }

    /**
     * Get the private key resource.
     */
    public function getResource(): OpenSSLAsymmetricKey
    {
        return $this->resource;
    }

    /**
     * Get private key in PEM format.
     */
    public function getPem(?string $passphrase = null): string
    {
        openssl_pkey_export($this->resource, $output, $passphrase);

        return $output;
    }

    /**
     * Get key type (RSA, DSA, DH, EC, etc.).
     */
    public function getType(): string
    {
        return match ($this->details['type']) {
            OPENSSL_KEYTYPE_RSA => 'RSA',
            OPENSSL_KEYTYPE_DSA => 'DSA',
            OPENSSL_KEYTYPE_DH => 'DH',
            OPENSSL_KEYTYPE_EC => 'EC',
            default => 'Unknown',
        };
    }

    /**
     * Get key size in bits.
     */
    public function getBits(): int
    {
        return $this->details['bits'];
    }

    /**
     * Get key details.
     */
    public function getDetails(): array
    {
        return $this->details;
    }

    /**
     * Check if key is RSA.
     */
    public function isRsa(): bool
    {
        return $this->details['type'] === OPENSSL_KEYTYPE_RSA;
    }

    /**
     * Check if key meets minimum size requirements.
     */
    public function meetsMinimumSize(int $minBits = 2048): bool
    {
        return $this->getBits() >= $minBits;
    }

    /**
     * Get key info as array.
     */
    public function toArray(): array
    {
        return [
            'type' => $this->getType(),
            'bits' => $this->getBits(),
            'is_rsa' => $this->isRsa(),
        ];
    }
}
