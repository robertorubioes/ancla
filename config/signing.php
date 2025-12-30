<?php

return [
    /*
    |--------------------------------------------------------------------------
    | PAdES Level
    |--------------------------------------------------------------------------
    |
    | Supported levels: 'B-B', 'B-LT', 'B-LTA'
    |
    | B-B: Basic, no TSA required
    | B-LT: Long-term validation, requires TSA Qualified
    | B-LTA: Archive, requires TSA + document timestamp
    |
    */
    'pades_level' => env('SIGNATURE_PADES_LEVEL', 'B-LT'),

    /*
    |--------------------------------------------------------------------------
    | Certificate Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for X.509 certificates used for signing.
    | In development, use self-signed certificates.
    | In production, use CA-issued certificates (DigiCert, GlobalSign, etc.)
    |
    */
    'certificate' => [
        'cert_path' => env('SIGNATURE_CERT_PATH', 'storage/certificates/ancla-dev.crt'),
        'key_path' => env('SIGNATURE_KEY_PATH', 'storage/certificates/ancla-dev.key'),
        'key_password' => env('SIGNATURE_KEY_PASSWORD', null),
        'pkcs12_path' => env('SIGNATURE_PKCS12_PATH', null),
        'ca_bundle_path' => env('SIGNATURE_CA_BUNDLE_PATH', null),
    ],

    /*
    |--------------------------------------------------------------------------
    | Signature Appearance
    |--------------------------------------------------------------------------
    |
    | Configuration for visible signature appearance on PDF documents.
    |
    */
    'appearance' => [
        'mode' => env('SIGNATURE_APPEARANCE_MODE', 'visible'), // 'visible' | 'invisible'

        'position' => [
            'page' => env('SIGNATURE_PAGE', 'last'), // 'first' | 'last' | int
            'x' => (int) env('SIGNATURE_X', 50),      // mm from left
            'y' => (int) env('SIGNATURE_Y', 50),      // mm from top
            'width' => (int) env('SIGNATURE_WIDTH', 80),
            'height' => (int) env('SIGNATURE_HEIGHT', 40),
        ],

        'layout' => [
            'show_signature_image' => (bool) env('SIGNATURE_SHOW_IMAGE', true),
            'show_signer_name' => (bool) env('SIGNATURE_SHOW_NAME', true),
            'show_timestamp' => (bool) env('SIGNATURE_SHOW_TIMESTAMP', true),
            'show_reason' => (bool) env('SIGNATURE_SHOW_REASON', true),
            'show_logo' => (bool) env('SIGNATURE_SHOW_LOGO', true),
            'show_qr_code' => (bool) env('SIGNATURE_SHOW_QR', true),
        ],

        'style' => [
            'border_color' => env('SIGNATURE_BORDER_COLOR', '#1a73e8'),
            'border_width' => (float) env('SIGNATURE_BORDER_WIDTH', 1),
            'background_color' => env('SIGNATURE_BG_COLOR', '#f8f9fa'),
            'font_family' => env('SIGNATURE_FONT', 'Helvetica'),
            'font_size' => (int) env('SIGNATURE_FONT_SIZE', 9),
            'logo_path' => 'signatures/logo-ancla.png',
        ],

        'text' => [
            'signed_label' => 'Firmado Electrónicamente',
            'date_label' => 'Fecha',
            'certificate_label' => 'Certificado',
            'tsa_label' => 'Sello de Tiempo',
            'verify_label' => 'Verificar en',
            'reason_default' => 'Firmado electrónicamente con ANCLA',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Settings
    |--------------------------------------------------------------------------
    |
    | Security-related configuration for signature generation.
    |
    */
    'security' => [
        'hash_algorithm' => 'sha256',
        'rsa_key_size' => 4096,
        'pkcs7_placeholder_size' => 8192, // bytes reserved for signature
        'max_pdf_size' => 52428800, // 50MB
        'allowed_digest_algorithms' => [
            'sha256',
            'sha384',
            'sha512',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Validation Settings
    |--------------------------------------------------------------------------
    |
    | Settings for signature validation and verification.
    |
    */
    'validation' => [
        'check_revocation' => env('SIGNATURE_CHECK_REVOCATION', false),
        'ocsp_responder' => env('SIGNATURE_OCSP_RESPONDER', null),
        'crl_url' => env('SIGNATURE_CRL_URL', null),
        'adobe_validation' => env('SIGNATURE_ADOBE_VALIDATION', true),
        'verify_timestamp' => true,
        'verify_certificate_chain' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Storage Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for storing signed PDF documents.
    |
    */
    'storage' => [
        'disk' => env('SIGNATURE_STORAGE_DISK', 'local'),
        'path' => 'signed',
        'encrypt' => env('SIGNATURE_ENCRYPT_STORAGE', false),
        'retention_days' => env('SIGNATURE_RETENTION_DAYS', null), // null = indefinido
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Rate limits for signature operations to prevent abuse.
    |
    */
    'rate_limits' => [
        'signatures_per_hour' => (int) env('SIGNATURE_RATE_HOUR', 100),
        'signatures_per_day' => (int) env('SIGNATURE_RATE_DAY', 1000),
        'concurrent_signings' => (int) env('SIGNATURE_CONCURRENT', 5),
    ],

    /*
    |--------------------------------------------------------------------------
    | Metadata Embedding
    |--------------------------------------------------------------------------
    |
    | Configuration for metadata embedded in PDF signatures.
    | Only non-personal data should be embedded (GDPR compliance).
    |
    */
    'metadata' => [
        'version' => '1.0',
        'embed_evidence_hash' => true,
        'embed_device_fingerprint_hash' => true,
        'embed_ip_hash' => true,
        'embed_geolocation_summary' => true, // Solo ciudad/país, no coordenadas exactas
        'embed_verification_code' => true,
        'embed_qr_code' => true,
        'embed_audit_chain_hash' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | TSA Integration
    |--------------------------------------------------------------------------
    |
    | Settings for TSA (Time Stamp Authority) integration.
    | This references the TSA service configured in config/evidence.php
    |
    */
    'tsa' => [
        'use_qualified' => env('SIGNATURE_TSA_QUALIFIED', true),
        'timeout' => 30, // seconds
        'retry_attempts' => 3,
        'retry_delay' => 1000, // milliseconds
    ],

    /*
    |--------------------------------------------------------------------------
    | PDF Processing
    |--------------------------------------------------------------------------
    |
    | Settings for PDF processing during signature.
    |
    */
    'pdf' => [
        'compression' => env('SIGNATURE_PDF_COMPRESSION', true),
        'optimize' => env('SIGNATURE_PDF_OPTIMIZE', true),
        'linearize' => env('SIGNATURE_PDF_LINEARIZE', false),
        'temp_dir' => storage_path('app/temp/signing'),
        'cleanup_temp' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Signature Reasons
    |--------------------------------------------------------------------------
    |
    | Predefined reasons for signing documents.
    |
    */
    'reasons' => [
        'approval' => 'Aprobado',
        'contract' => 'Contrato firmado',
        'agreement' => 'Acuerdo',
        'consent' => 'Consentimiento',
        'authorization' => 'Autorización',
        'custom' => 'Firmado electrónicamente',
    ],

    /*
    |--------------------------------------------------------------------------
    | Signature Locations
    |--------------------------------------------------------------------------
    |
    | Predefined locations for signature context.
    |
    */
    'locations' => [
        'default' => 'ANCLA Platform',
        'web' => 'ANCLA Web Application',
        'mobile' => 'ANCLA Mobile App',
        'api' => 'ANCLA API',
    ],

    /*
    |--------------------------------------------------------------------------
    | Contact Information
    |--------------------------------------------------------------------------
    |
    | Contact information embedded in signatures.
    |
    */
    'contact' => [
        'info' => env('SIGNATURE_CONTACT_INFO', 'soporte@ancla.es'),
        'url' => env('APP_URL', 'https://ancla.es'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Audit Trail
    |--------------------------------------------------------------------------
    |
    | Configuration for signature audit trail.
    |
    */
    'audit' => [
        'enabled' => true,
        'log_channel' => 'signature', // Can define custom log channel
        'events' => [
            'signature.started',
            'signature.pdf_hashed',
            'signature.pkcs7_created',
            'signature.tsa_requested',
            'signature.tsa_received',
            'signature.pdf_embedded',
            'signature.completed',
            'signature.failed',
            'signature.validated',
        ],
    ],
];
