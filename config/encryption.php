<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Document Encryption Configuration
    |--------------------------------------------------------------------------
    |
    | This configuration file defines settings for document encryption at-rest.
    | Documents are encrypted using AES-256-GCM with per-tenant key derivation.
    |
    | Architecture:
    | - Master Key: APP_ENCRYPTION_KEY in .env (base64 encoded 256-bit key)
    | - Key Derivation: HKDF-SHA256 per tenant
    | - Algorithm: AES-256-GCM (Authenticated Encryption)
    | - Format: [12-byte nonce][ciphertext][16-byte auth tag]
    |
    | @see docs/architecture/adr-010-encryption-at-rest.md
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Master Encryption Key
    |--------------------------------------------------------------------------
    |
    | The master key is used to derive tenant-specific encryption keys.
    | This should be a base64-encoded 256-bit (32-byte) random key.
    |
    | Generate a new key:
    | $ openssl rand -base64 32
    |
    | Store in .env:
    | APP_ENCRYPTION_KEY=base64:XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX
    |
    */

    'master_key' => env('APP_ENCRYPTION_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Encryption Algorithm
    |--------------------------------------------------------------------------
    |
    | The encryption algorithm used for document encryption.
    | Default: AES-256-GCM (Galois/Counter Mode with authentication)
    |
    | Supported: aes-256-gcm, aes-256-cbc (not recommended)
    |
    */

    'algorithm' => env('ENCRYPTION_ALGORITHM', 'aes-256-gcm'),

    /*
    |--------------------------------------------------------------------------
    | Key Version
    |--------------------------------------------------------------------------
    |
    | Current encryption key version. Increment this when rotating master key.
    | Used to track which key version was used to encrypt data.
    |
    | Increment after key rotation:
    | 1. Generate new master key
    | 2. Increment key_version to 'v2'
    | 3. Run: php artisan documents:re-encrypt --from=v1 --to=v2
    |
    */

    'key_version' => env('ENCRYPTION_KEY_VERSION', 'v1'),

    /*
    |--------------------------------------------------------------------------
    | Key Cache TTL
    |--------------------------------------------------------------------------
    |
    | How long (in seconds) to cache derived tenant keys in Redis/memory.
    | Longer cache = better performance but more memory usage.
    |
    | Recommended: 3600 (1 hour)
    | Set to 0 to disable caching (impacts performance)
    |
    */

    'key_cache_ttl' => env('ENCRYPTION_KEY_CACHE_TTL', 3600),

    /*
    |--------------------------------------------------------------------------
    | Enable Debug Logging
    |--------------------------------------------------------------------------
    |
    | When enabled, logs encryption/decryption operations (for debugging).
    | WARNING: Do NOT enable in production (performance impact + log bloat)
    |
    */

    'debug_logging' => env('ENCRYPTION_DEBUG_LOGGING', false),

    /*
    |--------------------------------------------------------------------------
    | Batch Encryption Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for batch encryption operations (e.g., encrypt-existing command)
    |
    */

    'batch' => [
        // Number of documents to process per batch
        'chunk_size' => env('ENCRYPTION_BATCH_CHUNK_SIZE', 100),

        // Delay between batches (microseconds) to avoid overwhelming the system
        'delay' => env('ENCRYPTION_BATCH_DELAY', 100000), // 100ms

        // Maximum execution time per document (seconds)
        'timeout' => env('ENCRYPTION_BATCH_TIMEOUT', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Backup Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for automated encrypted backups
    |
    */

    'backup' => [
        // Enable automatic encrypted backups
        'enabled' => env('BACKUP_ENCRYPTION_ENABLED', true),

        // Backup schedule (cron expression)
        'schedule' => env('BACKUP_SCHEDULE', '0 2 * * *'), // 2 AM daily

        // Backup retention days
        'retention_days' => env('BACKUP_RETENTION_DAYS', 30),

        // Backup disk (from filesystems.php)
        'disk' => env('BACKUP_DISK', 's3'),

        // Backup path prefix
        'path' => env('BACKUP_PATH', 'backups/encrypted'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Settings
    |--------------------------------------------------------------------------
    |
    | Additional security configurations
    |
    */

    'security' => [
        // Require HTTPS for operations (recommended in production)
        'require_https' => env('ENCRYPTION_REQUIRE_HTTPS', true),

        // Minimum encrypted data size (prevents encrypting empty/tiny data)
        'min_plaintext_size' => env('ENCRYPTION_MIN_SIZE', 1),

        // Maximum plaintext size before chunking (bytes)
        'max_plaintext_size' => env('ENCRYPTION_MAX_SIZE', 104857600), // 100MB

        // Enable integrity checks after encryption
        'verify_after_encrypt' => env('ENCRYPTION_VERIFY', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | HKDF Configuration
    |--------------------------------------------------------------------------
    |
    | HKDF (HMAC-based Key Derivation Function) settings
    |
    */

    'hkdf' => [
        // Hash algorithm for HKDF
        'hash' => env('ENCRYPTION_HKDF_HASH', 'sha256'),

        // Output key length (bytes)
        'length' => env('ENCRYPTION_HKDF_LENGTH', 32), // 256-bit

        // Info string format (tenant ID will be interpolated)
        'info_format' => env('ENCRYPTION_HKDF_INFO', 'tenant:%d:documents:v1'),
    ],

];
