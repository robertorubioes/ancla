<?php

namespace App\Exceptions;

use Exception;

/**
 * EncryptionException
 *
 * Thrown when encryption or decryption operations fail.
 * This can indicate issues with:
 * - Invalid encryption keys
 * - Corrupted encrypted data
 * - Tampered data (GCM auth tag mismatch)
 * - Missing encryption configuration
 */
class EncryptionException extends Exception
{
    /**
     * Create exception for failed encryption.
     */
    public static function encryptionFailed(string $reason = ''): self
    {
        $message = 'Encryption operation failed';
        if ($reason) {
            $message .= ": {$reason}";
        }

        return new self($message);
    }

    /**
     * Create exception for failed decryption.
     */
    public static function decryptionFailed(string $reason = ''): self
    {
        $message = 'Decryption operation failed or data has been tampered with';
        if ($reason) {
            $message .= ": {$reason}";
        }

        return new self($message);
    }

    /**
     * Create exception for invalid encrypted data format.
     */
    public static function invalidFormat(): self
    {
        return new self('Invalid encrypted data format. Data must be at least 28 bytes (12-byte nonce + content + 16-byte tag).');
    }

    /**
     * Create exception for missing master key.
     */
    public static function missingMasterKey(): self
    {
        return new self('Master encryption key not configured. Set APP_ENCRYPTION_KEY in .env');
    }

    /**
     * Create exception for missing tenant context.
     */
    public static function missingTenantContext(): self
    {
        return new self('Tenant context required for encryption operations.');
    }

    /**
     * Create exception for data integrity check failure.
     */
    public static function integrityCheckFailed(): self
    {
        return new self('Data integrity check failed. Data may have been tampered with.');
    }
}
