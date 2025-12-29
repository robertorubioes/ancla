<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Rate Limiting Configuration
    |--------------------------------------------------------------------------
    |
    | Configure rate limits for the public verification API to prevent abuse.
    |
    */
    'rate_limit' => [
        'per_minute' => (int) env('VERIFICATION_RATE_LIMIT_PER_MINUTE', 60),
        'per_day' => (int) env('VERIFICATION_RATE_LIMIT_PER_DAY', 1000),
        'download_per_minute' => (int) env('VERIFICATION_DOWNLOAD_RATE_LIMIT_PER_MINUTE', 10),
        'download_per_day' => (int) env('VERIFICATION_DOWNLOAD_RATE_LIMIT_PER_DAY', 100),
    ],

    /*
    |--------------------------------------------------------------------------
    | Confidence Level Thresholds
    |--------------------------------------------------------------------------
    |
    | Define the score thresholds for confidence levels.
    | Scores are out of 100.
    |
    */
    'confidence' => [
        'high' => (int) env('VERIFICATION_CONFIDENCE_HIGH', 90),     // >= 90%
        'medium' => (int) env('VERIFICATION_CONFIDENCE_MEDIUM', 70), // >= 70%
        'low' => 0,                                                   // < 70%
    ],

    /*
    |--------------------------------------------------------------------------
    | Confidence Score Points
    |--------------------------------------------------------------------------
    |
    | Points awarded for each successful verification check.
    | Total should sum to 100 for full integrity.
    |
    */
    'score_points' => [
        'document_hash_valid' => 20,      // Document hash verification
        'chain_hash_valid' => 20,         // Audit trail chain verification
        'tsa_timestamp_valid' => 20,      // TSA timestamp verification
        'device_fingerprint' => 15,       // Device fingerprint present
        'geolocation' => 10,              // Geolocation data present
        'ip_resolution' => 10,            // IP resolution present
        'consent_records' => 5,           // Consent records present
    ],

    /*
    |--------------------------------------------------------------------------
    | Verification Code Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for generating verification codes.
    |
    */
    'code' => [
        // Full verification code length (without dashes)
        'length' => (int) env('VERIFICATION_CODE_LENGTH', 12),

        // Short code length for QR
        'short_length' => (int) env('VERIFICATION_SHORT_CODE_LENGTH', 6),

        // Characters to use (avoiding confusing chars: 0/O, 1/I/L)
        'charset' => 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789',

        // Default expiration in days (null = never expires)
        'default_expiration_days' => env('VERIFICATION_CODE_EXPIRATION_DAYS'),
    ],

    /*
    |--------------------------------------------------------------------------
    | QR Code Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for generating QR codes.
    |
    */
    'qr' => [
        'size' => (int) env('VERIFICATION_QR_SIZE', 300),
        'margin' => (int) env('VERIFICATION_QR_MARGIN', 10),
        'format' => env('VERIFICATION_QR_FORMAT', 'png'),
        'error_correction' => env('VERIFICATION_QR_ERROR_CORRECTION', 'M'), // L, M, Q, H
        'storage_disk' => env('VERIFICATION_QR_STORAGE_DISK', 'local'),
        'storage_path' => env('VERIFICATION_QR_STORAGE_PATH', 'qr-codes'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Configuration
    |--------------------------------------------------------------------------
    |
    | Cache settings for verification results.
    |
    */
    'cache' => [
        'enabled' => (bool) env('VERIFICATION_CACHE_ENABLED', true),
        'ttl_minutes' => (int) env('VERIFICATION_CACHE_TTL_MINUTES', 5),
        'prefix' => 'verification:',
    ],

    /*
    |--------------------------------------------------------------------------
    | Public Verification URL
    |--------------------------------------------------------------------------
    |
    | Base URL for public verification pages and API.
    |
    */
    'public_url' => env('VERIFICATION_PUBLIC_URL', env('APP_URL', 'http://localhost')),

    /*
    |--------------------------------------------------------------------------
    | Verification Page Settings
    |--------------------------------------------------------------------------
    |
    | Settings for the public verification web page.
    |
    */
    'page' => [
        // Show detailed verification results
        'show_details' => (bool) env('VERIFICATION_SHOW_DETAILS', true),

        // Allow downloading evidence dossier
        'allow_download' => (bool) env('VERIFICATION_ALLOW_DOWNLOAD', true),

        // Show document preview (thumbnail)
        'show_preview' => (bool) env('VERIFICATION_SHOW_PREVIEW', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for logging verification attempts.
    |
    */
    'logging' => [
        // Log all verification attempts
        'enabled' => (bool) env('VERIFICATION_LOGGING_ENABLED', true),

        // Log IP addresses
        'log_ip' => (bool) env('VERIFICATION_LOG_IP', true),

        // Log user agents
        'log_user_agent' => (bool) env('VERIFICATION_LOG_USER_AGENT', true),

        // Retention period for logs in days
        'retention_days' => (int) env('VERIFICATION_LOG_RETENTION_DAYS', 365),
    ],
];
