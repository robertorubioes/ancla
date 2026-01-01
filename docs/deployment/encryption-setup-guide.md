# Guía de Configuración de Encriptación

> **Documento**: Encryption Setup Guide  
> **Versión**: 1.0  
> **Última actualización**: 2025-12-30  
> **Relacionado**: E2-003, ADR-010  

---

## 📋 Índice

1. [Resumen Ejecutivo](#resumen-ejecutivo)
2. [Pre-requisitos](#pre-requisitos)
3. [Instalación Inicial](#instalación-inicial)
4. [Configuración de Producción](#configuración-de-producción)
5. [Migración de Documentos Existentes](#migración-de-documentos-existentes)
6. [Backup y Recuperación](#backup-y-recuperación)
7. [Key Rotation](#key-rotation)
8. [Troubleshooting](#troubleshooting)
9. [Monitoreo](#monitoreo)

---

## Resumen Ejecutivo

Firmalum implementa encriptación at-rest usando **AES-256-GCM** con **key derivation per-tenant** (HKDF-SHA256). Esto garantiza:

✅ Confidencialidad de documentos  
✅ Aislamiento criptográfico entre tenants  
✅ Detección de tampering (autenticación integrada)  
✅ Cumplimiento GDPR Art. 32  
✅ Performance < 10% overhead  

**Arquitectura**:
```
Master Key → HKDF → Tenant DEK → AES-256-GCM → Encrypted Document
```

---

## Pre-requisitos

### Software Necesario
- PHP 8.2+ con extensión OpenSSL habilitada
- Laravel 11.x
- Redis (opcional, para cache de claves)
- S3-compatible storage (producción)

### Verificar OpenSSL
```bash
php -m | grep openssl
# Output: openssl

php -r "echo 'AES-256-GCM available: ' . (in_array('aes-256-gcm', openssl_get_cipher_methods()) ? 'YES' : 'NO');"
# Output: AES-256-GCM available: YES
```

---

## Instalación Inicial

### Paso 1: Generar Master Key

```bash
# Generar master key (32 bytes = 256 bits)
openssl rand -base64 32

# Output ejemplo:
# abc123def456ghi789jkl012mno345pqr678stu901vwx234yz=
```

### Paso 2: Configurar .env

```env
# Master encryption key (OBLIGATORIO)
APP_ENCRYPTION_KEY=base64:abc123def456ghi789jkl012mno345pqr678stu901vwx234yz=

# Key version (incrementar al rotar)
ENCRYPTION_KEY_VERSION=v1

# Cache TTL (segundos) - 1 hora recomendado
ENCRYPTION_KEY_CACHE_TTL=3600

# Algorithm (no cambiar)
ENCRYPTION_ALGORITHM=aes-256-gcm

# Debug logging (solo development)
ENCRYPTION_DEBUG_LOGGING=false

# Batch processing
ENCRYPTION_BATCH_CHUNK_SIZE=100
ENCRYPTION_BATCH_DELAY=100000

# Security settings
ENCRYPTION_REQUIRE_HTTPS=true
ENCRYPTION_MIN_SIZE=1
ENCRYPTION_MAX_SIZE=104857600

# Backup settings
BACKUP_ENCRYPTION_ENABLED=true
BACKUP_SCHEDULE="0 2 * * *"
BACKUP_RETENTION_DAYS=30
BACKUP_DISK=s3
BACKUP_PATH=backups/encrypted
```

### Paso 3: Ejecutar Migraciones

```bash
php artisan migrate

# Expected output:
# Migrating: 2025_01_01_000069_add_encryption_metadata_to_documents
# Migrated:  2025_01_01_000069_add_encryption_metadata_to_documents
```

### Paso 4: Verificar Configuración

```bash
php artisan tinker

>>> config('app.encryption_key')
=> "base64:abc123..."

>>> config('encryption.key_version')
=> "v1"

>>> config('encryption.algorithm')
=> "aes-256-gcm"
```

---

## Configuración de Producción

### 1. Master Key Storage

**⚠️ CRÍTICO**: La master key NUNCA debe estar en control de versiones.

#### Opción A: AWS Secrets Manager (Recomendado)

```bash
# Almacenar en Secrets Manager
aws secretsmanager create-secret \
    --name firmalum/encryption/master-key \
    --secret-string "base64:abc123..." \
    --region us-east-1

# En .env (producción)
APP_ENCRYPTION_KEY=${AWS_SECRETS_MANAGER:firmalum/encryption/master-key}
```

#### Opción B: HashiCorp Vault

```bash
# Almacenar en Vault
vault kv put secret/firmalum/encryption master_key="base64:abc123..."

# En .env (producción)
APP_ENCRYPTION_KEY=${VAULT:secret/firmalum/encryption/master_key}
```

#### Opción C: Variables de Entorno Seguras

```bash
# En servidor producción (NO en .env file)
export APP_ENCRYPTION_KEY="base64:abc123..."

# Verificar
echo $APP_ENCRYPTION_KEY
```

### 2. S3 Backup Configuration

```env
# AWS S3 para backups
AWS_ACCESS_KEY_ID=AKIAXXXXXXXXXXXXXXXX
AWS_SECRET_ACCESS_KEY=xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
AWS_DEFAULT_REGION=eu-west-1
AWS_BUCKET=firmalum-encrypted-backups

BACKUP_DISK=s3
BACKUP_PATH=backups/encrypted
```

### 3. Laravel Scheduler

**Crontab** (obligatorio para backup automático):

```bash
# Editar crontab
crontab -e

# Agregar línea:
* * * * * cd /var/www/firmalum && php artisan schedule:run >> /dev/null 2>&1
```

### 4. HTTPS Enforcement

```env
# En producción SIEMPRE HTTPS
ENCRYPTION_REQUIRE_HTTPS=true
APP_URL=https://firmalum.com
```

---

## Migración de Documentos Existentes

### Escenario: Tienes documentos sin encriptar en producción

#### Paso 1: Backup Pre-migración

```bash
# Backup completo ANTES de encriptar
php artisan documents:backup

# Verify backup created
ls storage/app/backups/
```

#### Paso 2: Dry Run

```bash
# Simulación sin cambios
php artisan documents:encrypt-existing --dry-run

# Output ejemplo:
# Found 1,234 documents to process
# Found 567 signed documents to process
# ✅ Dry run completed. 1,801 documents would be encrypted.
```

#### Paso 3: Encriptar por Tenant (Recomendado)

```bash
# Encriptar tenant por tenant para control
php artisan documents:encrypt-existing --tenant=1 --batch=50

# Output:
# ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
#  Metric              | Documents | Signed  
# ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
#  Processed           | 500       | 250     
#  Encrypted           | 485       | 240     
#  Skipped             | 15        | 10      
#  Errors              | 0         | 0       
# ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
```

#### Paso 4: Encriptar Todo

```bash
# Una vez verificado, encriptar todo
php artisan documents:encrypt-existing --batch=100

# Monitoring en tiempo real
tail -f storage/logs/laravel.log | grep "Encrypted document"
```

#### Paso 5: Verificación

```bash
php artisan tinker

# Verificar documentos encriptados
>>> Document::where('is_encrypted', true)->count()
=> 1234

>>> Document::whereNull('is_encrypted')->count()
=> 0

# Verificar key version
>>> Document::where('encryption_key_version', 'v1')->count()
=> 1234
```

### Timeline Estimado

| Volumen | Batch Size | Tiempo Estimado |
|---------|------------|-----------------|
| 1,000 docs | 100 | ~5 minutos |
| 10,000 docs | 100 | ~50 minutos |
| 100,000 docs | 200 | ~8 horas |

**Recomendación**: Ejecutar en horario de baja actividad (2-6 AM).

---

## Backup y Recuperación

### Backup Manual

```bash
# Backup inmediato
php artisan documents:backup

# Solo un tenant
php artisan documents:backup --tenant=123

# Dry run (test)
php artisan documents:backup --dry-run
```

### Backup Automático

El backup automático corre diariamente a las 2 AM (configurable en `BACKUP_SCHEDULE`).

**Verificar schedule**:
```bash
php artisan schedule:list

# Output:
# 0 2 * * * documents:backup ......... Next Due: Tomorrow at 2:00 AM
```

### Backup Structure

```
s3://bucket/backups/encrypted/
├── 2025-12-30_020000/
│   ├── manifest.json
│   ├── documents/
│   │   ├── 1/  (tenant_id)
│   │   │   ├── 123.encrypted
│   │   │   └── 124.encrypted
│   │   └── 2/
│   │       └── 456.encrypted
│   └── signed_documents/
│       ├── 1/
│       │   └── 789.encrypted
│       └── 2/
│           └── 1011.encrypted
```

### Restore Procedure

```bash
# 1. Locate backup
aws s3 ls s3://bucket/backups/encrypted/

# 2. Download backup
aws s3 sync s3://bucket/backups/encrypted/2025-12-30_020000 ./restore/

# 3. Verify manifest
cat restore/manifest.json

# 4. Copy files back to storage
cp -r restore/documents/* storage/app/documents/
cp -r restore/signed_documents/* storage/app/signed/

# 5. Verify in DB
php artisan tinker
>>> Document::where('is_encrypted', true)->count()
```

---

## Key Rotation

### ¿Cuándo Rotar?

- **Obligatorio**: Cada 12 meses (compliance)
- **Recomendado**: Cada 6 meses (best practice)
- **Emergencia**: Si hay sospecha de compromiso

### Procedimiento de Rotación

#### Paso 1: Generar Nueva Key

```bash
# Generar nueva master key
NEW_KEY=$(openssl rand -base64 32)
echo "Nueva master key: base64:$NEW_KEY"

# GUARDAR EN LUGAR SEGURO (password manager, vault)
```

#### Paso 2: Backup Pre-Rotación

```bash
# Backup COMPLETO antes de cualquier cambio
php artisan documents:backup

# Backup de base de datos
php artisan db:backup

# Verificar backup OK
ls -lh storage/app/backups/
```

#### Paso 3: Configurar Nueva Key

```env
# .env (producción)
# Mantener OLD key temporalmente
APP_ENCRYPTION_KEY_OLD=base64:OLD_KEY_HERE

# Nueva key
APP_ENCRYPTION_KEY=base64:NEW_KEY_HERE

# Incrementar version
ENCRYPTION_KEY_VERSION=v2
```

#### Paso 4: Re-encriptar Documentos

```bash
# Dry run PRIMERO
php artisan documents:encrypt-existing --force --dry-run

# Si todo OK, ejecutar
php artisan documents:encrypt-existing --force --batch=50

# Monitoring
tail -f storage/logs/laravel.log
```

#### Paso 5: Verificación Post-Rotación

```bash
php artisan tinker

# Verificar todos documentos en v2
>>> Document::where('encryption_key_version', 'v2')->count()
=> 1234

>>> Document::where('encryption_key_version', 'v1')->count()
=> 0

# Test decrypt random document
>>> $doc = Document::inRandomOrder()->first()
>>> Storage::get($doc->file_path)
=> "..." (debe funcionar sin error)
```

#### Paso 6: Limpiar Old Key

```env
# .env - Remover old key después de verificar
# APP_ENCRYPTION_KEY_OLD=...  (ELIMINAR ESTA LÍNEA)

APP_ENCRYPTION_KEY=base64:NEW_KEY_HERE
ENCRYPTION_KEY_VERSION=v2
```

#### Paso 7: Documentar Rotación

```bash
# Crear log de rotación
cat >> docs/security/key-rotation-log.md << EOF
## Key Rotation $(date +%Y-%m-%d)
- Previous version: v1
- New version: v2
- Documents re-encrypted: 1,234
- Duration: 45 minutes
- Performed by: [Name]
- Verified by: [Name]
EOF
```

### Timeline de Rotación

| Fase | Duración | Downtime |
|------|----------|----------|
| Backup | 10-30 min | No |
| Re-encriptar | Variable | No* |
| Verificación | 5-10 min | No |
| Cleanup | 2 min | No |

*Nota: No hay downtime si se hace en horario de baja actividad.

---

## Troubleshooting

### Error: "Master encryption key not configured"

**Causa**: `APP_ENCRYPTION_KEY` falta en .env

**Solución**:
```bash
# Generar key
openssl rand -base64 32

# Agregar a .env
echo "APP_ENCRYPTION_KEY=base64:YOUR_KEY_HERE" >> .env

# Restart app
php artisan config:clear
```

### Error: "Tenant context required for encryption"

**Causa**: Operación de encriptación sin tenant context

**Solución**:
```php
// Asegurar tenant context
$tenantContext = app(\App\Services\TenantContext::class);
$tenantContext->set($tenant);

// Luego encriptar
$service->encrypt($content);
```

### Error: "Decryption failed or data tampered"

**Causa**: Datos corruptos o manipulados, o wrong tenant key

**Diagnóstico**:
```bash
php artisan tinker

>>> $service = app(\App\Services\Document\DocumentEncryptionService::class);
>>> $metadata = $service->getMetadata($encryptedData);
>>> print_r($metadata);

# Verificar:
# - encrypted: true/false
# - valid: true/false
# - algorithm: aes-256-gcm
```

**Soluciones**:
1. Verificar tenant context correcto
2. Verificar master key correcta
3. Restaurar desde backup si corrupto

### Error: "Invalid encrypted data format"

**Causa**: Datos demasiado cortos (< 28 bytes)

**Solución**:
```bash
# Verificar tamaño
php artisan tinker
>>> strlen($data)
=> 15  # Muy corto!

# Probablemente no está encriptado
>>> $service->isEncrypted($data)
=> false
```

### Performance Degradado

**Diagnóstico**:
```bash
# Verificar cache de claves
php artisan tinker
>>> Cache::get("encryption:dek:tenant:1")
=> "..." (debe retornar string de 32 bytes)

# Si null, cache no funciona
```

**Solución**:
```bash
# Verificar Redis/cache driver
php artisan cache:clear
php artisan config:clear

# Test cache
php artisan tinker
>>> Cache::put('test', 'value', 60)
>>> Cache::get('test')
=> "value"
```

---

## Monitoreo

### Métricas Clave

```sql
-- Documents encriptados
SELECT 
    is_encrypted,
    COUNT(*) as count,
    SUM(file_size) as total_size_bytes
FROM documents
GROUP BY is_encrypted;

-- Distribution por key version
SELECT 
    encryption_key_version,
    COUNT(*) as count
FROM documents
WHERE is_encrypted = 1
GROUP BY encryption_key_version;

-- Documentos encriptados recientemente
SELECT 
    id, uuid, encrypted_at
FROM documents
WHERE is_encrypted = 1
AND encrypted_at > NOW() - INTERVAL 7 DAY
ORDER BY encrypted_at DESC;

-- Backup status
SELECT 
    created_at,
    COUNT(*) as backups
FROM backups
WHERE type = 'encrypted'
GROUP BY DATE(created_at)
ORDER BY created_at DESC
LIMIT 7;
```

### Logs a Monitorear

```bash
# Fallos de decriptación (posible attack)
tail -f storage/logs/laravel.log | grep "Decryption failed"

# Encriptación exitosa
tail -f storage/logs/laravel.log | grep "Encrypted document"

# Backup status
tail -f storage/logs/laravel.log | grep "backup completed"
```

### Alerts Recomendados

1. **HIGH**: >10 decryption failures en 1 hora → Posible attack
2. **MEDIUM**: Backup failed 2 días consecutivos → DR risk
3. **LOW**: Cache miss rate >50% → Performance impact
4. **INFO**: Key rotation próxima (30 días antes)

---

## Security Checklist

### Pre-Production

- [ ] Master key almacenada en secrets manager (no .env file)
- [ ] HTTPS enforcement habilitado
- [ ] Backup automático configurado y probado
- [ ] Restore procedure documentado y probado
- [ ] Key rotation procedure documentado
- [ ] Logs de encriptación monitoreados
- [ ] Alerts configurados
- [ ] Tests pasando (37/37)
- [ ] Security audit completado

### Production Operations

- [ ] Master key acceso restringido (solo superadmin)
- [ ] Backup retention compliance (30+ días)
- [ ] Key rotation calendar (cada 12 meses)
- [ ] Logs reviewed mensualmente
- [ ] Incidents response plan documentado
- [ ] DR drill cada 6 meses

---

## Referencias

- [ADR-010: Encryption at-Rest Strategy](../architecture/adr-010-encryption-at-rest.md)
- [E2-003 Implementation Summary](../implementation/e2-003-encryption-at-rest-summary.md)
- [RFC 5869: HKDF](https://datatracker.ietf.org/doc/html/rfc5869)
- [NIST SP 800-38D: GCM](https://csrc.nist.gov/publications/detail/sp/800-38d/final)
- [GDPR Art. 32](https://gdpr-info.eu/art-32-gdpr/)

---

## Support

Para problemas de encriptación:
1. Revisar esta guía
2. Verificar logs en `storage/logs/laravel.log`
3. Contactar Tech Lead si persiste
4. Security Expert para incidentes críticos

**Última actualización**: 2025-12-30
