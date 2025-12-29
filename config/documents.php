<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Document Upload Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for document upload limits, allowed file types, and
    | validation rules.
    |
    */

    'max_size' => env('DOCUMENT_MAX_SIZE', 50 * 1024 * 1024), // 50MB

    'max_pages' => env('DOCUMENT_MAX_PAGES', 500),

    'allowed_mimes' => ['application/pdf'],

    'allowed_extensions' => ['pdf'],

    /*
    |--------------------------------------------------------------------------
    | Storage Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for document storage including disk, encryption, and
    | file organization.
    |
    */

    'storage_disk' => env('DOCUMENT_STORAGE_DISK', 'local'),

    'storage_prefix' => 'documents',

    'encryption' => [
        'enabled' => env('DOCUMENT_ENCRYPTION_ENABLED', true),
        'cipher' => 'AES-256-GCM',
    ],

    /*
    |--------------------------------------------------------------------------
    | Thumbnail Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for generating PDF thumbnails including dimensions
    | and storage location.
    |
    */

    'thumbnail' => [
        'enabled' => env('DOCUMENT_THUMBNAILS_ENABLED', true),
        'width' => 200,
        'height' => 283, // A4 proportions
        'dpi' => 150,
        'prefix' => 'thumbnails',
        'format' => 'png',
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Settings
    |--------------------------------------------------------------------------
    |
    | Security-related configuration for document validation including
    | virus scanning and content restrictions.
    |
    */

    'security' => [
        'virus_scan_enabled' => env('DOCUMENT_VIRUS_SCAN', false),

        'reject_javascript' => env('DOCUMENT_REJECT_JAVASCRIPT', false),

        'reject_encrypted' => true,

        // Rate limiting for uploads per tenant/user
        'rate_limit' => [
            'enabled' => true,
            'max_per_minute' => env('DOCUMENT_RATE_LIMIT_PER_MINUTE', 10),
            'max_per_hour' => env('DOCUMENT_RATE_LIMIT_PER_HOUR', 100),
        ],

        // Maximum concurrent uploads
        'max_concurrent_uploads' => env('DOCUMENT_MAX_CONCURRENT', 5),
    ],

    /*
    |--------------------------------------------------------------------------
    | PDF Validation
    |--------------------------------------------------------------------------
    |
    | Settings for PDF structure validation and metadata extraction.
    |
    */

    'validation' => [
        // PDF magic bytes that must be present
        'magic_bytes' => '%PDF-',

        // Minimum PDF version supported
        'min_version' => '1.0',

        // Maximum PDF version supported
        'max_version' => '2.0',

        // Patterns to detect dangerous content
        'dangerous_patterns' => [
            '/\/JS\s/',
            '/\/JavaScript\s/',
            '/\/AA\s/', // Automatic Actions
            '/\/OpenAction\s/', // Actions on open
            '/\/Launch\s/', // Launch external applications
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Cleanup Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for automatic cleanup of temporary files and
    | failed uploads.
    |
    */

    'cleanup' => [
        // Delete error documents after this many days
        'error_retention_days' => env('DOCUMENT_ERROR_RETENTION_DAYS', 7),

        // Delete orphaned temporary files after this many hours
        'temp_file_retention_hours' => env('DOCUMENT_TEMP_RETENTION_HOURS', 24),
    ],
];
