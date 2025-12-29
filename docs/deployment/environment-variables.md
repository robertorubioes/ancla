# ANCLA Environment Variables

## Complete Reference

This document lists all environment variables required for ANCLA deployment.

---

## Table of Contents

1. [Core Laravel Variables](#core-laravel-variables)
2. [Database Configuration](#database-configuration)
3. [Cache & Sessions](#cache--sessions)
4. [Queue Configuration](#queue-configuration)
5. [Storage (S3/Spaces)](#storage-s3spaces)
6. [Email Configuration](#email-configuration)
7. [Multi-tenant Configuration](#multi-tenant-configuration)
8. [Evidence System (TSA)](#evidence-system-tsa)
9. [Geolocation Services](#geolocation-services)
10. [Security Settings](#security-settings)
11. [Monitoring & Logging](#monitoring--logging)
12. [Feature Flags](#feature-flags)

---

## Core Laravel Variables

```env
# Application Settings
APP_NAME="ANCLA"
APP_ENV=production
APP_KEY=base64:xxxxxxxxxxxxxxxxxxxxxxxxxxxxx
APP_DEBUG=false
APP_TIMEZONE=UTC
APP_URL=https://ancla.app

# Locale
APP_LOCALE=es
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=es_ES

# Logging
LOG_CHANNEL=stack
LOG_STACK=single
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=error
```

### Variable Details

| Variable | Required | Description | MVP Value |
|----------|----------|-------------|-----------|
| `APP_NAME` | Yes | Application name | `ANCLA` |
| `APP_ENV` | Yes | Environment (local/staging/production) | `production` |
| `APP_KEY` | Yes | Encryption key (generate with `php artisan key:generate`) | Auto-generated |
| `APP_DEBUG` | Yes | Show debug info (MUST be false in prod) | `false` |
| `APP_URL` | Yes | Full application URL | `https://ancla.app` |
| `APP_TIMEZONE` | Yes | Server timezone | `UTC` |

---

## Database Configuration

```env
# Primary Database
DB_CONNECTION=mysql
DB_HOST=db-mysql-fra1-xxxxx.db.ondigitalocean.com
DB_PORT=25060
DB_DATABASE=ancla_production
DB_USERNAME=ancla_app
DB_PASSWORD=your-secure-password

# SSL Mode (required for managed databases)
DB_SSL_MODE=require
# DB_SSL_CA=/path/to/ca-certificate.crt

# Connection Pool
DB_POOL_MIN=2
DB_POOL_MAX=10
```

### Variable Details

| Variable | Required | Description | MVP Value |
|----------|----------|-------------|-----------|
| `DB_CONNECTION` | Yes | Database driver | `mysql` |
| `DB_HOST` | Yes | Database host | Managed DB endpoint |
| `DB_PORT` | Yes | Database port | `25060` (DO Managed) |
| `DB_DATABASE` | Yes | Database name | `ancla_production` |
| `DB_USERNAME` | Yes | Database user | Create dedicated user |
| `DB_PASSWORD` | Yes | Database password | Strong unique password |
| `DB_SSL_MODE` | Recommended | Require SSL connection | `require` |

---

## Cache & Sessions

```env
# Cache Configuration
CACHE_DRIVER=redis
CACHE_PREFIX=ancla_cache_
CACHE_STORE=redis

# Session Configuration
SESSION_DRIVER=redis
SESSION_LIFETIME=120
SESSION_ENCRYPT=true
SESSION_PATH=/
SESSION_DOMAIN=.ancla.app
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=lax

# Redis Configuration
REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
REDIS_DB=0
REDIS_CACHE_DB=1
```

### Variable Details

| Variable | Required | Description | MVP Value |
|----------|----------|-------------|-----------|
| `CACHE_DRIVER` | Yes | Cache backend | `redis` |
| `SESSION_DRIVER` | Yes | Session backend | `redis` |
| `SESSION_LIFETIME` | Yes | Session duration (minutes) | `120` |
| `SESSION_ENCRYPT` | Yes | Encrypt session data | `true` |
| `SESSION_DOMAIN` | Yes | Cookie domain (include dot for subdomains) | `.ancla.app` |
| `SESSION_SECURE_COOKIE` | Yes | HTTPS-only cookies | `true` |
| `REDIS_HOST` | Yes | Redis server | `127.0.0.1` or managed |

---

## Queue Configuration

```env
# Queue Settings
QUEUE_CONNECTION=redis

# Redis Queue Specifics
REDIS_QUEUE=default
QUEUE_FAILED_DRIVER=database-uuids

# Horizon (if using)
HORIZON_PREFIX=ancla_horizon_

# Job Retry Settings
QUEUE_RETRY_AFTER=90
```

### Variable Details

| Variable | Required | Description | MVP Value |
|----------|----------|-------------|-----------|
| `QUEUE_CONNECTION` | Yes | Queue backend | `redis` |
| `QUEUE_FAILED_DRIVER` | Yes | Failed job storage | `database-uuids` |

---

## Storage (S3/Spaces)

```env
# Filesystem
FILESYSTEM_DISK=s3

# S3/Spaces Configuration
AWS_ACCESS_KEY_ID=DOxxxxxxxxxxxxxxxx
AWS_SECRET_ACCESS_KEY=your-secret-key
AWS_DEFAULT_REGION=fra1
AWS_BUCKET=ancla-storage
AWS_ENDPOINT=https://fra1.digitaloceanspaces.com
AWS_URL=https://ancla-storage.fra1.digitaloceanspaces.com
AWS_USE_PATH_STYLE_ENDPOINT=false

# Document Storage (separate bucket for security)
DOCUMENTS_DISK=s3
DOCUMENTS_BUCKET=ancla-documents

# Evidence Storage
EVIDENCE_STORAGE_DISK=s3
CONSENT_STORAGE_DISK=s3
DOSSIER_STORAGE_DISK=s3
```

### Variable Details

| Variable | Required | Description | MVP Value |
|----------|----------|-------------|-----------|
| `FILESYSTEM_DISK` | Yes | Default disk | `s3` |
| `AWS_ACCESS_KEY_ID` | Yes | Spaces access key | From DO console |
| `AWS_SECRET_ACCESS_KEY` | Yes | Spaces secret | From DO console |
| `AWS_DEFAULT_REGION` | Yes | Spaces region | `fra1` |
| `AWS_BUCKET` | Yes | Default bucket | `ancla-storage` |
| `AWS_ENDPOINT` | Yes | Spaces endpoint | `https://fra1.digitaloceanspaces.com` |

---

## Email Configuration

```env
# Mail Driver
MAIL_MAILER=smtp
MAIL_HOST=smtp.postmarkapp.com
MAIL_PORT=587
MAIL_USERNAME=your-postmark-api-key
MAIL_PASSWORD=your-postmark-api-key
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@ancla.app
MAIL_FROM_NAME="ANCLA"

# Postmark Configuration
POSTMARK_TOKEN=your-postmark-server-token
POSTMARK_MESSAGE_STREAM_ID=outbound
```

### Variable Details

| Variable | Required | Description | MVP Value |
|----------|----------|-------------|-----------|
| `MAIL_MAILER` | Yes | Mail driver | `smtp` |
| `MAIL_HOST` | Yes | SMTP server | `smtp.postmarkapp.com` |
| `MAIL_PORT` | Yes | SMTP port | `587` |
| `MAIL_FROM_ADDRESS` | Yes | Default sender email | `noreply@ancla.app` |
| `POSTMARK_TOKEN` | Yes | Postmark API token | From Postmark console |

---

## Multi-tenant Configuration

```env
# Tenant Resolution
APP_BASE_DOMAIN=ancla.app
TENANT_DEFAULT_SLUG=demo

# Subdomain Routing
TENANT_SUBDOMAIN_PATTERN={tenant}.ancla.app

# Admin Exclusions (subdomains that bypass tenant)
TENANT_EXCLUDED_DOMAINS=admin,api,www,app
```

### Variable Details

| Variable | Required | Description | MVP Value |
|----------|----------|-------------|-----------|
| `APP_BASE_DOMAIN` | Yes | Main domain for subdomains | `ancla.app` |
| `TENANT_EXCLUDED_DOMAINS` | Yes | Subdomains to exclude from tenant resolution | `admin,api,www` |

---

## Evidence System (TSA)

```env
# TSA Configuration
TSA_PRIMARY_PROVIDER=freetsa
TSA_FALLBACK_PROVIDER=digicert
TSA_TIMEOUT=30
TSA_RETRY_ATTEMPTS=3

# FreeTSA (Development/MVP)
TSA_FREETSA_URL=https://freetsa.org/tsr
TSA_FREETSA_ENABLED=true

# Firmaprofesional (Production - eIDAS qualified)
TSA_FIRMAPROFESIONAL_URL=https://tsa.firmaprofesional.com/tsa
TSA_FIRMAPROFESIONAL_ENABLED=false
TSA_FIRMAPROFESIONAL_USERNAME=
TSA_FIRMAPROFESIONAL_PASSWORD=

# DigiCert (Fallback)
TSA_DIGICERT_URL=https://timestamp.digicert.com
TSA_DIGICERT_ENABLED=true

# Mock Mode (for testing without real TSA)
TSA_MOCK_ENABLED=false

# Audit Trail
AUDIT_RETENTION_DAYS=1825

# Dossier Generation
EVIDENCE_PLATFORM_SIGNING_KEY=your-platform-signing-key
DOSSIER_VERIFICATION_URL=https://verify.ancla.app
```

### Variable Details

| Variable | Required | Description | MVP Value |
|----------|----------|-------------|-----------|
| `TSA_PRIMARY_PROVIDER` | Yes | Primary TSA | `freetsa` (MVP), `firmaprofesional` (prod) |
| `TSA_FALLBACK_PROVIDER` | Yes | Backup TSA | `digicert` |
| `TSA_TIMEOUT` | Yes | Request timeout (seconds) | `30` |
| `AUDIT_RETENTION_DAYS` | Yes | Audit log retention | `1825` (5 years) |

### TSA Provider Notes

**MVP Phase (FreeTSA.org)**
- Free, no authentication required
- Not eIDAS qualified
- Good for testing and development

**Production Phase (Firmaprofesional)**
- Spanish eIDAS qualified TSA
- Requires contract and credentials
- Full legal validity in EU

---

## Geolocation Services

```env
# IP Geolocation
GEOLOCATION_IP_PROVIDER=ipapi

# ipapi.co (free tier)
IPAPI_BASE_URL=https://ipapi.co

# IPInfo.io (enhanced - optional)
IPINFO_TOKEN=your-ipinfo-token

# Proxy/VPN Detection (optional for MVP)
PROXYCHECK_API_KEY=your-proxycheck-key
```

### Variable Details

| Variable | Required | Description | MVP Value |
|----------|----------|-------------|-----------|
| `GEOLOCATION_IP_PROVIDER` | Yes | IP geolocation service | `ipapi` |
| `IPINFO_TOKEN` | No | IPInfo.io API token | Optional |
| `PROXYCHECK_API_KEY` | No | ProxyCheck.io key | Optional for MVP |

---

## Security Settings

```env
# Encryption
ENCRYPTION_KEY=base64:xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx

# Rate Limiting
RATE_LIMIT_LOGIN=5
RATE_LIMIT_API=60

# CORS
CORS_ALLOWED_ORIGINS=https://ancla.app,https://*.ancla.app

# Security Headers
SECURITY_HEADERS_ENABLED=true

# 2FA
FORTIFY_FEATURES_TWO_FACTOR=true
FORTIFY_WINDOW=0

# Password Requirements
PASSWORD_MIN_LENGTH=8
PASSWORD_REQUIRE_MIXED_CASE=true
PASSWORD_REQUIRE_NUMBERS=true
PASSWORD_REQUIRE_SYMBOLS=true
```

### Variable Details

| Variable | Required | Description | MVP Value |
|----------|----------|-------------|-----------|
| `RATE_LIMIT_LOGIN` | Yes | Login attempts per minute | `5` |
| `RATE_LIMIT_API` | Yes | API requests per minute | `60` |
| `FORTIFY_FEATURES_TWO_FACTOR` | Yes | Enable 2FA | `true` |

---

## Monitoring & Logging

```env
# Sentry Error Tracking
SENTRY_LARAVEL_DSN=https://xxxx@o123.ingest.sentry.io/456
SENTRY_TRACES_SAMPLE_RATE=0.1
SENTRY_PROFILES_SAMPLE_RATE=0.1

# Log Configuration
LOG_CHANNEL=stack
LOG_STACK=daily,stderr
LOG_LEVEL=warning

# Security Log
LOG_SECURITY_CHANNEL=security

# Laravel Telescope (disable in production)
TELESCOPE_ENABLED=false
```

### Variable Details

| Variable | Required | Description | MVP Value |
|----------|----------|-------------|-----------|
| `SENTRY_LARAVEL_DSN` | Recommended | Sentry project DSN | From Sentry dashboard |
| `LOG_LEVEL` | Yes | Minimum log level | `warning` (prod) |
| `TELESCOPE_ENABLED` | Yes | Debug tool status | `false` (prod) |

---

## Feature Flags

```env
# Feature Toggles
FEATURE_REGISTRATION_ENABLED=true
FEATURE_PUBLIC_API_ENABLED=false
FEATURE_BIOMETRIC_SIGNATURE=false
FEATURE_ADVANCED_BRANDING=false
FEATURE_BATCH_OPERATIONS=false

# MVP Specific
MVP_MODE=true
```

### Variable Details

| Variable | Required | Description | MVP Value |
|----------|----------|-------------|-----------|
| `FEATURE_REGISTRATION_ENABLED` | Yes | Allow new signups | `true` |
| `FEATURE_PUBLIC_API_ENABLED` | No | Public verification API | `false` (post-MVP) |
| `MVP_MODE` | No | Enable MVP restrictions | `true` |

---

## Complete .env.example

```env
###############################################################################
# ANCLA ENVIRONMENT CONFIGURATION
# ================================
# Copy this file to .env and configure for your environment
###############################################################################

#------------------------------------------------------------------------------
# CORE APPLICATION
#------------------------------------------------------------------------------
APP_NAME=ANCLA
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_TIMEZONE=UTC
APP_URL=http://localhost

APP_LOCALE=es
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=es_ES

#------------------------------------------------------------------------------
# LOGGING
#------------------------------------------------------------------------------
LOG_CHANNEL=stack
LOG_STACK=single,daily
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=debug

#------------------------------------------------------------------------------
# DATABASE
#------------------------------------------------------------------------------
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=ancla
DB_USERNAME=root
DB_PASSWORD=

#------------------------------------------------------------------------------
# CACHE & SESSIONS
#------------------------------------------------------------------------------
CACHE_DRIVER=redis
CACHE_PREFIX=ancla_cache_
SESSION_DRIVER=redis
SESSION_LIFETIME=120
SESSION_ENCRYPT=true
SESSION_PATH=/
SESSION_DOMAIN=null
SESSION_SECURE_COOKIE=false

#------------------------------------------------------------------------------
# REDIS
#------------------------------------------------------------------------------
REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
REDIS_DB=0
REDIS_CACHE_DB=1

#------------------------------------------------------------------------------
# QUEUE
#------------------------------------------------------------------------------
QUEUE_CONNECTION=redis

#------------------------------------------------------------------------------
# STORAGE (S3/SPACES)
#------------------------------------------------------------------------------
FILESYSTEM_DISK=local
AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=
AWS_ENDPOINT=
AWS_USE_PATH_STYLE_ENDPOINT=false

EVIDENCE_STORAGE_DISK=local
CONSENT_STORAGE_DISK=local
DOSSIER_STORAGE_DISK=local

#------------------------------------------------------------------------------
# MAIL
#------------------------------------------------------------------------------
MAIL_MAILER=log
MAIL_HOST=127.0.0.1
MAIL_PORT=2525
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS="noreply@ancla.local"
MAIL_FROM_NAME="${APP_NAME}"

#------------------------------------------------------------------------------
# MULTI-TENANT
#------------------------------------------------------------------------------
APP_BASE_DOMAIN=ancla.local
TENANT_EXCLUDED_DOMAINS=admin,api,www

#------------------------------------------------------------------------------
# TSA (TIME STAMPING AUTHORITY)
#------------------------------------------------------------------------------
TSA_PRIMARY_PROVIDER=freetsa
TSA_FALLBACK_PROVIDER=digicert
TSA_TIMEOUT=30
TSA_RETRY_ATTEMPTS=3
TSA_MOCK_ENABLED=false

# Audit
AUDIT_RETENTION_DAYS=1825

# Dossier
EVIDENCE_PLATFORM_SIGNING_KEY=
DOSSIER_VERIFICATION_URL=

#------------------------------------------------------------------------------
# GEOLOCATION
#------------------------------------------------------------------------------
GEOLOCATION_IP_PROVIDER=ipapi
IPINFO_TOKEN=
PROXYCHECK_API_KEY=

#------------------------------------------------------------------------------
# MONITORING
#------------------------------------------------------------------------------
SENTRY_LARAVEL_DSN=
SENTRY_TRACES_SAMPLE_RATE=1.0

#------------------------------------------------------------------------------
# SECURITY
#------------------------------------------------------------------------------
RATE_LIMIT_LOGIN=5
RATE_LIMIT_API=60

#------------------------------------------------------------------------------
# FEATURES
#------------------------------------------------------------------------------
FEATURE_REGISTRATION_ENABLED=true
FEATURE_PUBLIC_API_ENABLED=false
MVP_MODE=true

#------------------------------------------------------------------------------
# DEVELOPMENT TOOLS
#------------------------------------------------------------------------------
TELESCOPE_ENABLED=true
DEBUGBAR_ENABLED=true
```

---

## Environment-Specific Configurations

### Local Development

```env
APP_ENV=local
APP_DEBUG=true
LOG_LEVEL=debug
CACHE_DRIVER=array
SESSION_DRIVER=file
QUEUE_CONNECTION=sync
MAIL_MAILER=log
TELESCOPE_ENABLED=true
TSA_MOCK_ENABLED=true
```

### Staging

```env
APP_ENV=staging
APP_DEBUG=true
LOG_LEVEL=debug
CACHE_DRIVER=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis
MAIL_MAILER=smtp
TELESCOPE_ENABLED=true
TSA_MOCK_ENABLED=false
```

### Production

```env
APP_ENV=production
APP_DEBUG=false
LOG_LEVEL=warning
CACHE_DRIVER=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis
MAIL_MAILER=smtp
TELESCOPE_ENABLED=false
TSA_MOCK_ENABLED=false
```

---

## Secret Management Best Practices

### DO NOT:
- ❌ Commit `.env` to version control
- ❌ Share secrets in plain text
- ❌ Use same secrets across environments
- ❌ Log sensitive values

### DO:
- ✅ Use `.env.example` as template
- ✅ Store production secrets in Forge/vault
- ✅ Rotate credentials regularly
- ✅ Use strong random passwords
- ✅ Audit secret access

### Laravel Forge Secret Management

1. Go to Forge → Site → Environment
2. Add/edit variables securely
3. Click "Save" to apply changes
4. Forge automatically restarts PHP-FPM

---

## Validation Script

Add this to verify environment configuration:

```php
// app/Console/Commands/ValidateEnv.php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ValidateEnv extends Command
{
    protected $signature = 'env:validate';
    protected $description = 'Validate required environment variables';

    private array $required = [
        'APP_KEY',
        'APP_URL',
        'DB_HOST',
        'DB_DATABASE',
        'DB_USERNAME',
        'DB_PASSWORD',
        'REDIS_HOST',
        'MAIL_FROM_ADDRESS',
        'APP_BASE_DOMAIN',
    ];

    private array $production = [
        'AWS_ACCESS_KEY_ID',
        'AWS_SECRET_ACCESS_KEY',
        'AWS_BUCKET',
        'SENTRY_LARAVEL_DSN',
    ];

    public function handle(): int
    {
        $missing = [];

        foreach ($this->required as $var) {
            if (empty(env($var))) {
                $missing[] = $var;
            }
        }

        if (app()->environment('production')) {
            foreach ($this->production as $var) {
                if (empty(env($var))) {
                    $missing[] = $var;
                }
            }
        }

        if (!empty($missing)) {
            $this->error('Missing environment variables:');
            foreach ($missing as $var) {
                $this->line("  - {$var}");
            }
            return 1;
        }

        $this->info('✅ All required environment variables are set.');
        return 0;
    }
}
```

---

**Document Version**: 1.0
**Last Updated**: 2025-12-28
**Author**: ANCLA Architecture Team
