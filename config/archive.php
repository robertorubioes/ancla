<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Storage Tiers Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the storage tiers for document archiving. Documents are
    | automatically migrated between tiers based on their age and the
    | retention policy settings.
    |
    */

    'tiers' => [
        'hot' => [
            'max_age_days' => (int) env('ARCHIVE_HOT_MAX_AGE_DAYS', 365),
            'storage_disk' => env('ARCHIVE_HOT_STORAGE_DISK', 'local'),
            'storage_bucket' => env('ARCHIVE_HOT_STORAGE_BUCKET'),
            'storage_class' => env('ARCHIVE_HOT_STORAGE_CLASS', 'STANDARD'),
            'description' => 'Fast access storage for recent documents',
        ],

        'cold' => [
            'max_age_days' => (int) env('ARCHIVE_COLD_MAX_AGE_DAYS', 3650), // 10 years
            'storage_disk' => env('ARCHIVE_COLD_STORAGE_DISK', 's3-glacier'),
            'storage_bucket' => env('ARCHIVE_COLD_STORAGE_BUCKET'),
            'storage_class' => env('ARCHIVE_COLD_STORAGE_CLASS', 'GLACIER_IR'),
            'restore_time_hours' => (int) env('ARCHIVE_COLD_RESTORE_TIME', 5),
            'description' => 'Reduced cost storage for older documents',
        ],

        'archive' => [
            'storage_disk' => env('ARCHIVE_DEEP_STORAGE_DISK', 's3-deep-archive'),
            'storage_bucket' => env('ARCHIVE_DEEP_STORAGE_BUCKET'),
            'storage_class' => env('ARCHIVE_DEEP_STORAGE_CLASS', 'DEEP_ARCHIVE'),
            'restore_time_hours' => (int) env('ARCHIVE_DEEP_RESTORE_TIME', 12),
            'description' => 'Long-term archival storage for regulatory compliance',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | TSA Re-sealing Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the TSA (Time Stamp Authority) re-sealing process. Re-sealing
    | ensures that timestamps remain valid even after the original TSA
    | certificate expires.
    |
    */

    'reseal' => [
        // How often to check for chains needing re-seal (in days)
        'check_interval_days' => (int) env('ARCHIVE_RESEAL_CHECK_INTERVAL', 7),

        // Re-seal this many days before the certificate expires
        'reseal_before_expiry_days' => (int) env('ARCHIVE_RESEAL_BEFORE_EXPIRY', 90),

        // Default interval between re-seals (in days)
        'default_interval_days' => (int) env('ARCHIVE_RESEAL_INTERVAL', 365),

        // Maximum number of entries in a single chain
        'max_chain_length' => (int) env('ARCHIVE_MAX_CHAIN_LENGTH', 100),

        // Batch size for processing re-seals
        'batch_size' => (int) env('ARCHIVE_RESEAL_BATCH_SIZE', 100),

        // Queue name for reseal jobs
        'queue' => env('ARCHIVE_RESEAL_QUEUE', 'archive'),

        // Retry attempts for failed re-seals
        'retry_attempts' => (int) env('ARCHIVE_RESEAL_RETRY_ATTEMPTS', 3),

        // Retry delay in minutes
        'retry_delay_minutes' => (int) env('ARCHIVE_RESEAL_RETRY_DELAY', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | Retention Policy Defaults
    |--------------------------------------------------------------------------
    |
    | Default retention settings applied when no specific policy is defined.
    | These defaults comply with eIDAS requirements for minimum 5-year retention.
    |
    */

    'retention' => [
        // Default retention period in years
        'default_years' => (int) env('ARCHIVE_RETENTION_YEARS', 5),

        // Minimum allowed retention period
        'min_years' => (int) env('ARCHIVE_RETENTION_MIN_YEARS', 1),

        // Maximum allowed retention period
        'max_years' => (int) env('ARCHIVE_RETENTION_MAX_YEARS', 50),

        // Grace period after expiry before taking action (days)
        'grace_period_days' => (int) env('ARCHIVE_GRACE_PERIOD', 30),

        // Default action when retention expires
        'default_expiry_action' => env('ARCHIVE_DEFAULT_EXPIRY_ACTION', 'notify'),

        // Allowed expiry actions
        'allowed_expiry_actions' => ['archive', 'delete', 'notify', 'extend'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Tier Migration Configuration
    |--------------------------------------------------------------------------
    |
    | Configure automatic migration of documents between storage tiers.
    |
    */

    'tier_migration' => [
        // Enable automatic tier migration
        'enabled' => env('ARCHIVE_TIER_MIGRATION_ENABLED', true),

        // Batch size for tier migration jobs
        'batch_size' => (int) env('ARCHIVE_TIER_MIGRATION_BATCH_SIZE', 50),

        // Queue name for migration jobs
        'queue' => env('ARCHIVE_TIER_MIGRATION_QUEUE', 'archive'),

        // Days after creation to move from hot to cold
        'hot_to_cold_days' => (int) env('ARCHIVE_HOT_TO_COLD_DAYS', 365),

        // Days after creation to move from cold to archive
        'cold_to_archive_days' => (int) env('ARCHIVE_COLD_TO_ARCHIVE_DAYS', 3650),
    ],

    /*
    |--------------------------------------------------------------------------
    | Format Preservation Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for format migration and PDF/A conversion to ensure
    | long-term readability of archived documents.
    |
    */

    'format' => [
        // Automatically convert to PDF/A on archive
        'auto_convert_pdfa' => env('ARCHIVE_AUTO_CONVERT_PDFA', true),

        // Target PDF/A version for conversion
        'target_pdfa_version' => env('ARCHIVE_PDFA_VERSION', 'PDF/A-3b'),

        // Preserve original file alongside PDF/A conversion
        'preserve_original' => env('ARCHIVE_PRESERVE_ORIGINAL', true),

        // Supported PDF/A versions for validation
        'supported_pdfa_versions' => ['PDF/A-1a', 'PDF/A-1b', 'PDF/A-2a', 'PDF/A-2b', 'PDF/A-3a', 'PDF/A-3b'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Integrity Verification Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for periodic integrity verification of archived documents.
    |
    */

    'verification' => [
        // Enable periodic integrity verification
        'enabled' => env('ARCHIVE_VERIFICATION_ENABLED', true),

        // Days between verification checks
        'interval_days' => (int) env('ARCHIVE_VERIFICATION_INTERVAL', 30),

        // Batch size for verification jobs
        'batch_size' => (int) env('ARCHIVE_VERIFICATION_BATCH_SIZE', 100),

        // Queue name for verification jobs
        'queue' => env('ARCHIVE_VERIFICATION_QUEUE', 'archive'),

        // Alert on verification failures
        'alert_on_failure' => env('ARCHIVE_ALERT_ON_FAILURE', true),

        // Email addresses for failure alerts
        'alert_emails' => array_filter(explode(',', env('ARCHIVE_ALERT_EMAILS', ''))),
    ],

    /*
    |--------------------------------------------------------------------------
    | Storage Configuration
    |--------------------------------------------------------------------------
    |
    | General storage settings for the archive system.
    |
    */

    'storage' => [
        // Path prefix for archived documents
        'prefix' => env('ARCHIVE_STORAGE_PREFIX', 'archive'),

        // Encryption algorithm for stored documents
        'encryption' => env('ARCHIVE_ENCRYPTION', 'AES-256'),

        // Enable versioning on storage (requires S3 or compatible)
        'versioning' => env('ARCHIVE_VERSIONING_ENABLED', true),

        // Enable cross-region replication (requires S3 or compatible)
        'replication' => env('ARCHIVE_REPLICATION_ENABLED', false),

        // Replication destination region
        'replication_region' => env('ARCHIVE_REPLICATION_REGION'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cleanup Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for the cleanup process of expired documents.
    |
    */

    'cleanup' => [
        // Enable automatic cleanup of expired documents
        'enabled' => env('ARCHIVE_CLEANUP_ENABLED', false),

        // Require manual confirmation for cleanup operations
        'require_confirmation' => env('ARCHIVE_CLEANUP_REQUIRE_CONFIRMATION', true),

        // Create backup before deletion
        'backup_before_delete' => env('ARCHIVE_BACKUP_BEFORE_DELETE', true),

        // Days to retain cleanup audit logs
        'audit_retention_days' => (int) env('ARCHIVE_CLEANUP_AUDIT_RETENTION', 3650),
    ],

    /*
    |--------------------------------------------------------------------------
    | Notifications Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for archive-related notifications and alerts.
    |
    */

    'notifications' => [
        // Days before retention expiry to send notification
        'expiry_warning_days' => (int) env('ARCHIVE_EXPIRY_WARNING_DAYS', 90),

        // Days before reseal due to send notification
        'reseal_warning_days' => (int) env('ARCHIVE_RESEAL_WARNING_DAYS', 30),

        // Enable email notifications
        'email_enabled' => env('ARCHIVE_EMAIL_NOTIFICATIONS', true),

        // Notification channels
        'channels' => array_filter(explode(',', env('ARCHIVE_NOTIFICATION_CHANNELS', 'mail,database'))),
    ],

];
