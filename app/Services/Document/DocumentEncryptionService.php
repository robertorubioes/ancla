<?php

namespace App\Services\Document;

use App\Exceptions\EncryptionException;
use App\Services\TenantContext;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * DocumentEncryptionService
 *
 * Provides document encryption at-rest using AES-256-GCM with per-tenant key derivation.
 *
 * Architecture:
 * - Master Key: Stored in .env (APP_ENCRYPTION_KEY)
 * - Key Derivation: HKDF-SHA256 per tenant
 * - Algorithm: AES-256-GCM (AEAD - Authenticated Encryption with Associated Data)
 * - Format: [12-byte nonce][ciphertext][16-byte auth tag]
 *
 * Security Features:
 * - Per-tenant encryption keys (isolated data breach)
 * - Authenticated encryption (detects tampering)
 * - Random nonces (prevents pattern analysis)
 * - Stateless key derivation (no key storage needed)
 *
 * @see docs/architecture/adr-010-encryption-at-rest.md
 */
class DocumentEncryptionService
{
    /**
     * Nonce size in bytes (96-bit for GCM).
     */
    private const NONCE_SIZE = 12;

    /**
     * Authentication tag size in bytes (128-bit for GCM).
     */
    private const TAG_SIZE = 16;

    /**
     * Minimum encrypted data size (nonce + tag).
     */
    private const MIN_ENCRYPTED_SIZE = self::NONCE_SIZE + self::TAG_SIZE;

    /**
     * Encryption algorithm.
     */
    private const ALGORITHM = 'aes-256-gcm';

    /**
     * Derived encryption key cache TTL (seconds).
     */
    private const CACHE_TTL = 3600; // 1 hour

    public function __construct(
        private readonly TenantContext $tenantContext
    ) {}

    /**
     * Encrypt plaintext content.
     *
     * @param  string  $plaintext  The data to encrypt
     * @return string Binary encrypted data (nonce + ciphertext + tag)
     *
     * @throws EncryptionException If encryption fails
     */
    public function encrypt(string $plaintext): string
    {
        // Validate we have a tenant context
        $tenantId = $this->tenantContext->id();
        if (! $tenantId) {
            throw EncryptionException::missingTenantContext();
        }

        // Derive tenant-specific encryption key
        $dek = $this->deriveTenantKey($tenantId);

        // Generate random nonce (96-bit for GCM)
        $nonce = random_bytes(self::NONCE_SIZE);

        // Initialize auth tag variable
        $tag = '';

        // Encrypt with AES-256-GCM
        $ciphertext = openssl_encrypt(
            $plaintext,
            self::ALGORITHM,
            $dek,
            OPENSSL_RAW_DATA,
            $nonce,
            $tag,
            '', // no additional authenticated data
            self::TAG_SIZE
        );

        if ($ciphertext === false) {
            Log::error('Encryption failed', [
                'tenant_id' => $tenantId,
                'error' => openssl_error_string(),
            ]);
            throw EncryptionException::encryptionFailed(openssl_error_string() ?: 'Unknown OpenSSL error');
        }

        // Combine: nonce + ciphertext + tag
        return $nonce.$ciphertext.$tag;
    }

    /**
     * Decrypt encrypted content.
     *
     * @param  string  $encrypted  Binary encrypted data (nonce + ciphertext + tag)
     * @return string Decrypted plaintext
     *
     * @throws EncryptionException If decryption fails or data is tampered
     */
    public function decrypt(string $encrypted): string
    {
        // Validate minimum size
        if (strlen($encrypted) < self::MIN_ENCRYPTED_SIZE) {
            throw EncryptionException::invalidFormat();
        }

        // Extract components
        $nonce = substr($encrypted, 0, self::NONCE_SIZE);
        $tag = substr($encrypted, -self::TAG_SIZE);
        $ciphertext = substr($encrypted, self::NONCE_SIZE, -self::TAG_SIZE);

        // Validate we have a tenant context
        $tenantId = $this->tenantContext->id();
        if (! $tenantId) {
            throw EncryptionException::missingTenantContext();
        }

        // Derive tenant-specific encryption key
        $dek = $this->deriveTenantKey($tenantId);

        // Decrypt with AES-256-GCM
        $plaintext = openssl_decrypt(
            $ciphertext,
            self::ALGORITHM,
            $dek,
            OPENSSL_RAW_DATA,
            $nonce,
            $tag
        );

        if ($plaintext === false) {
            Log::warning('Decryption failed - possible tampering', [
                'tenant_id' => $tenantId,
                'encrypted_size' => strlen($encrypted),
            ]);
            throw EncryptionException::decryptionFailed('Invalid auth tag or corrupted data');
        }

        return $plaintext;
    }

    /**
     * Check if data is encrypted.
     *
     * Uses heuristic check: minimum size and successful decryption attempt.
     *
     * @param  string  $data  Data to check
     * @return bool True if data appears to be encrypted
     */
    public function isEncrypted(string $data): bool
    {
        // Heuristic 1: Check minimum size
        if (strlen($data) < self::MIN_ENCRYPTED_SIZE) {
            return false;
        }

        // Heuristic 2: Try to decrypt without throwing
        try {
            $this->decrypt($data);

            return true;
        } catch (EncryptionException) {
            return false;
        }
    }

    /**
     * Derive tenant-specific encryption key using HKDF.
     *
     * Uses HKDF (HMAC-based Key Derivation Function, RFC 5869) to derive
     * a unique 256-bit encryption key per tenant from the master key.
     *
     * Benefits:
     * - Stateless (no key storage needed)
     * - Isolated breach (compromising one tenant doesn't affect others)
     * - Cryptographically secure key derivation
     *
     * @param  int  $tenantId  Tenant identifier
     * @return string 256-bit derived encryption key
     *
     * @throws EncryptionException If master key is not configured
     */
    private function deriveTenantKey(int $tenantId): string
    {
        // Check cache first (performance optimization)
        $cacheKey = "encryption:dek:tenant:{$tenantId}";
        $cached = Cache::get($cacheKey);
        if ($cached) {
            return $cached;
        }

        // Get master key from config
        $masterKeyEncoded = config('app.encryption_key');
        if (! $masterKeyEncoded) {
            throw EncryptionException::missingMasterKey();
        }

        // Decode master key from base64
        $masterKey = base64_decode(substr($masterKeyEncoded, 7)); // Remove 'base64:' prefix
        if (strlen($masterKey) !== 32) {
            throw EncryptionException::encryptionFailed('Invalid master key length');
        }

        // Derive tenant-specific key using HKDF
        $info = "tenant:{$tenantId}:documents:v1";
        $dek = hash_hkdf(
            'sha256',
            $masterKey,
            32, // 256-bit key
            $info
        );

        // Cache derived key for performance
        Cache::put($cacheKey, $dek, self::CACHE_TTL);

        return $dek;
    }

    /**
     * Get encryption metadata for a given data.
     *
     * Useful for auditing and debugging.
     *
     * @param  string  $encrypted  Encrypted data
     * @return array Metadata including algorithm, nonce, tag size
     */
    public function getMetadata(string $encrypted): array
    {
        if (strlen($encrypted) < self::MIN_ENCRYPTED_SIZE) {
            return [
                'encrypted' => false,
                'valid' => false,
            ];
        }

        $nonce = substr($encrypted, 0, self::NONCE_SIZE);

        return [
            'encrypted' => true,
            'valid' => $this->isEncrypted($encrypted),
            'algorithm' => self::ALGORITHM,
            'nonce_size' => self::NONCE_SIZE,
            'tag_size' => self::TAG_SIZE,
            'total_size' => strlen($encrypted),
            'ciphertext_size' => strlen($encrypted) - self::MIN_ENCRYPTED_SIZE,
            'nonce_hex' => bin2hex($nonce),
        ];
    }

    /**
     * Clear cached derived keys for a tenant.
     *
     * Should be called after key rotation or security events.
     *
     * @param  int  $tenantId  Tenant identifier
     */
    public function clearKeyCache(int $tenantId): void
    {
        $cacheKey = "encryption:dek:tenant:{$tenantId}";
        Cache::forget($cacheKey);
    }

    /**
     * Clear all cached derived keys.
     *
     * Should be called after master key rotation.
     */
    public function clearAllKeyCaches(): void
    {
        // This would require storing all tenant IDs in a set
        // For now, we rely on TTL expiration
        Log::info('Encryption key caches will expire naturally after TTL');
    }
}
