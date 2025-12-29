<?php

/**
 * Evidence System Configuration
 *
 * Configuration for ANCLA's legal evidence system including:
 * - TSA (Time Stamping Authority) providers
 * - Hashing algorithms
 * - Audit trail settings
 * - Evidence package generation
 * - Device fingerprinting (Sprint 2)
 * - Geolocation capture (Sprint 2)
 * - IP resolution (Sprint 2)
 * - Consent capture (Sprint 2)
 * - Evidence dossier generation (Sprint 2)
 *
 * @see ADR-005 in docs/architecture/decisions.md
 * @see ADR-006 for Sprint 2 additions
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Time Stamping Authority (TSA) Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for RFC 3161 compliant timestamp providers. ANCLA uses qualified
    | timestamps to provide legal proof of when events occurred (eIDAS compliant).
    |
    */
    'tsa' => [
        // Primary TSA provider (Firmaprofesional - Spanish qualified TSA)
        'primary' => env('TSA_PRIMARY_PROVIDER', 'firmaprofesional'),

        // Fallback TSA provider if primary fails
        'fallback' => env('TSA_FALLBACK_PROVIDER', 'digicert'),

        // Available TSA providers configuration
        'providers' => [
            'firmaprofesional' => [
                'url' => env('TSA_FIRMAPROFESIONAL_URL', 'https://tsa.firmaprofesional.com/tsa'),
                'policy_oid' => '1.3.6.1.4.1.30439.1.1.1',
                'enabled' => env('TSA_FIRMAPROFESIONAL_ENABLED', true),
            ],
            'digicert' => [
                'url' => env('TSA_DIGICERT_URL', 'https://timestamp.digicert.com'),
                'policy_oid' => '2.16.840.1.114412.7.1',
                'enabled' => env('TSA_DIGICERT_ENABLED', true),
            ],
            'sectigo' => [
                'url' => env('TSA_SECTIGO_URL', 'http://timestamp.sectigo.com'),
                'policy_oid' => '1.3.6.1.4.1.6449.2.1.1',
                'enabled' => env('TSA_SECTIGO_ENABLED', false),
            ],
        ],

        // Request timeout in seconds
        'timeout' => env('TSA_TIMEOUT', 30),

        // Number of retry attempts before using fallback
        'retry_attempts' => env('TSA_RETRY_ATTEMPTS', 3),

        // Mock mode for testing (no real TSA calls)
        'mock' => env('TSA_MOCK_ENABLED', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Hashing Configuration
    |--------------------------------------------------------------------------
    |
    | Document hashing settings for integrity verification.
    | SHA-256 is used as per eIDAS requirements.
    |
    */
    'hashing' => [
        // Hashing algorithm (SHA-256 is eIDAS compliant)
        'algorithm' => 'sha256',

        // Chunk size for hashing large files (8KB)
        'chunk_size' => 8192,
    ],

    /*
    |--------------------------------------------------------------------------
    | Audit Trail Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for the immutable audit trail with chained hashes.
    | Each entry includes a hash of the previous entry, creating
    | a blockchain-like structure for tamper detection.
    |
    */
    'audit' => [
        // Events that require mandatory TSA timestamp
        'tsa_required_events' => [
            'document.uploaded',
            'document.signed',
            'document.certified',
            'signature_process.created',
            'signature_process.completed',
            'signer.signed',
            'evidence.consent_captured',
        ],

        // Retention period in days (default: 5 years = 1825 days)
        'retention_days' => env('AUDIT_RETENTION_DAYS', 1825),

        // Maximum entries per page when listing
        'per_page' => 50,
    ],

    /*
    |--------------------------------------------------------------------------
    | Evidence Packages Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for generating evidence packages (ZIP files containing
    | document, audit trail, and TSA tokens for legal verification).
    |
    */
    'packages' => [
        // Storage disk for evidence packages
        'storage_disk' => env('EVIDENCE_STORAGE_DISK', 'local'),

        // Path prefix within the storage disk
        'path_prefix' => 'evidence-packages',

        // Default expiry in days (null = never expires)
        'default_expiry_days' => env('EVIDENCE_PACKAGE_EXPIRY_DAYS', null),

        // Include options defaults
        'defaults' => [
            'include_document' => true,
            'include_signatures' => true,
            'include_audit_trail' => true,
            'include_tsa_tokens' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Verification Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for integrity verification operations.
    |
    */
    'verification' => [
        // Enable periodic background verification
        'background_enabled' => env('EVIDENCE_BACKGROUND_VERIFICATION', true),

        // Interval between verifications in hours
        'interval_hours' => env('EVIDENCE_VERIFICATION_INTERVAL', 24),

        // Log verification results
        'log_results' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Device Fingerprint Configuration (Sprint 2)
    |--------------------------------------------------------------------------
    |
    | Settings for capturing device fingerprints during signing sessions.
    | Used to identify the device used for signing for non-repudiation.
    |
    */
    'fingerprint' => [
        // Fingerprint version identifier
        'version' => 'v1',

        // Enable canvas fingerprinting
        'collect_canvas' => true,

        // Enable audio fingerprinting
        'collect_audio' => true,

        // Enable font detection
        'collect_fonts' => true,

        // Enable WebGL fingerprinting
        'collect_webgl' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Geolocation Configuration (Sprint 2)
    |--------------------------------------------------------------------------
    |
    | Settings for capturing signer geolocation.
    | GPS is requested first, with IP-based fallback.
    |
    */
    'geolocation' => [
        // IP geolocation provider
        'ip_provider' => env('GEOLOCATION_IP_PROVIDER', 'ipapi'),

        // IPInfo.io API token (for enhanced accuracy)
        'ipinfo_token' => env('IPINFO_TOKEN'),

        // Request GPS permission from browser
        'request_gps' => true,

        // GPS request timeout in milliseconds
        'gps_timeout' => 10000,

        // Request high accuracy GPS (may drain battery)
        'high_accuracy' => true,

        // Available IP geolocation providers
        'providers' => [
            'ipapi' => [
                'url' => 'https://ipapi.co/{ip}/json/',
                'rate_limit' => 1000, // requests per day
            ],
            'ipinfo' => [
                'url' => 'https://ipinfo.io/{ip}/json',
                'rate_limit' => 50000, // requests per month
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | IP Resolution Configuration (Sprint 2)
    |--------------------------------------------------------------------------
    |
    | Settings for IP address resolution and VPN/proxy detection.
    | Informational only - does not block signing.
    |
    */
    'ip_info' => [
        // IP info provider
        'provider' => env('IP_INFO_PROVIDER', 'ipapi'),

        // Proxycheck.io API key for VPN detection
        'proxycheck_key' => env('PROXYCHECK_API_KEY'),

        // Enable VPN detection
        'detect_vpn' => true,

        // Enable proxy detection
        'detect_proxy' => true,

        // Enable Tor detection
        'detect_tor' => true,

        // Cache TTL for IP lookups in seconds
        'cache_ttl' => 3600,
    ],

    /*
    |--------------------------------------------------------------------------
    | Consent Capture Configuration (Sprint 2)
    |--------------------------------------------------------------------------
    |
    | Settings for capturing explicit consent during signing.
    | Includes optional screenshot capture and TSA timestamping.
    |
    */
    'consent' => [
        // Enable screenshot capture of consent
        'capture_screenshot' => true,

        // Screenshot format (png, jpeg, webp)
        'screenshot_format' => 'png',

        // Screenshot quality (0.0 - 1.0)
        'screenshot_quality' => 0.9,

        // Require user to scroll to bottom before accepting
        'require_scroll' => false,

        // Require TSA timestamp on consent
        'tsa_required' => true,

        // Storage disk for screenshots
        'storage_disk' => env('CONSENT_STORAGE_DISK', 'local'),

        // Path prefix for screenshots
        'path_prefix' => 'consent-screenshots',
    ],

    /*
    |--------------------------------------------------------------------------
    | Consent Texts Configuration (Sprint 2)
    |--------------------------------------------------------------------------
    |
    | Legal texts displayed for different types of consent.
    | Multi-language support included.
    |
    */
    'consent_texts' => [
        'signature' => [
            'es' => 'Al hacer click en "Acepto", usted confirma que ha leído y comprende el documento presentado, acepta firmarlo electrónicamente, reconoce la validez legal de su firma electrónica según el Reglamento eIDAS (UE) 910/2014, y autoriza el registro de evidencias de este proceso.',
            'en' => 'By clicking "Accept", you confirm that you have read and understand the presented document, agree to sign it electronically, acknowledge the legal validity of your electronic signature under eIDAS Regulation (EU) 910/2014, and authorize the recording of evidence of this process.',
        ],
        'terms' => [
            'es' => 'He leído y acepto los Términos y Condiciones del servicio.',
            'en' => 'I have read and accept the Terms and Conditions of the service.',
        ],
        'privacy' => [
            'es' => 'He leído y acepto la Política de Privacidad.',
            'en' => 'I have read and accept the Privacy Policy.',
        ],
        'biometric' => [
            'es' => 'Autorizo el procesamiento de mis datos biométricos (firma manuscrita) para verificación de identidad.',
            'en' => 'I authorize the processing of my biometric data (handwritten signature) for identity verification.',
        ],
        'communication' => [
            'es' => 'Acepto recibir comunicaciones electrónicas relacionadas con el proceso de firma.',
            'en' => 'I agree to receive electronic communications related to the signing process.',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Consent Versions Configuration (Sprint 2)
    |--------------------------------------------------------------------------
    |
    | Version identifiers for each consent type.
    | Increment when legal text changes.
    |
    */
    'consent_versions' => [
        'signature' => '1.0',
        'terms' => '1.0',
        'privacy' => '1.0',
        'biometric' => '1.0',
        'communication' => '1.0',
    ],

    /*
    |--------------------------------------------------------------------------
    | Evidence Dossier Configuration (Sprint 2)
    |--------------------------------------------------------------------------
    |
    | Settings for generating PDF dossiers with all evidence.
    |
    */
    'dossier' => [
        // Storage disk for dossiers
        'storage_disk' => env('DOSSIER_STORAGE_DISK', 'local'),

        // Path prefix for dossiers
        'path_prefix' => 'evidence-dossiers',

        // Platform signing key for dossier signature
        'platform_signing_key' => env('EVIDENCE_PLATFORM_SIGNING_KEY'),

        // Include QR code for verification
        'include_qr' => true,

        // QR code size in pixels
        'qr_size' => 200,

        // Base URL for verification
        'verification_base_url' => env('DOSSIER_VERIFICATION_URL'),

        // Default paper size
        'paper_size' => 'A4',

        // Default orientation
        'orientation' => 'portrait',

        // Include tenant logo
        'include_tenant_logo' => true,

        // Include ANCLA logo
        'include_ancla_logo' => true,

        // Default dossier type
        'default_type' => 'full_evidence',
    ],
];
