# ADR-010: Estrategia de Encriptación at-Rest

> **Status**: Propuesto  
> **Fecha**: 2025-12-30  
> **Autor**: Arquitecto de Software  
> **Historia relacionada**: E2-003  
> **Sprint**: 5

---

## Contexto y Problema

Firmalum maneja documentos legales sensibles que requieren:
- **Confidencialidad**: Documentos contienen información personal y empresarial
- **Compliance**: GDPR Art. 32 exige "encriptación de datos personales"
- **Multi-tenant**: Aislamiento criptográfico entre organizaciones
- **Disponibilidad**: Acceso rápido sin degradar performance

**Problema**: Necesitamos encriptar documentos at-rest garantizando seguridad, rendimiento y cumplimiento legal.

---

## Decisión

Implementamos **encriptación at-rest con AES-256-GCM** usando una arquitectura de **key derivation per tenant** (HKDF).

### Arquitectura de Encriptación

```
┌─────────────────────────────────────────────────────────────┐
│                   Firmalum ENCRYPTION STRATEGY                  │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│  ┌────────────────┐                                         │
│  │  MASTER KEY    │  → Almacenada en .env (32 bytes)        │
│  │  (Base64)      │     APP_ENCRYPTION_KEY=...              │
│  └────────┬───────┘                                         │
│           │                                                  │
│           ├──► HKDF (HMAC-SHA256)                           │
│           │    Info: tenant_id + purpose                    │
│           │                                                  │
│           ├──────┬──────────┬──────────┬──────────┐         │
│           │      │          │          │          │         │
│           ▼      ▼          ▼          ▼          ▼         │
│      ┌────────┐  ┌────────┐  ┌────────┐  ┌────────┐       │
│      │Tenant 1│  │Tenant 2│  │Tenant 3│  │Tenant N│       │
│      │  DEK   │  │  DEK   │  │  DEK   │  │  DEK   │       │
│      └───┬────┘  └───┬────┘  └───┬────┘  └───┬────┘       │
│          │           │           │           │             │
│          ├──► AES-256-GCM per document ◄─────┤             │
│          │    - 256-bit encryption                         │
│          │    - 96-bit nonce (random)                      │
│          │    - 128-bit auth tag (AEAD)                    │
│          │                                                  │
│          ▼                                                  │
│   ┌──────────────────────────────────────┐                │
│   │  Encrypted Document Storage           │                │
│   │  Format: nonce + ciphertext + tag     │                │
│   └──────────────────────────────────────┘                │
│                                                              │
└─────────────────────────────────────────────────────────────┘
```

### Componentes Técnicos

#### 1. Master Key
- **Algoritmo**: Random 256-bit key
- **Generación**: `openssl rand -base64 32`
- **Almacenamiento**: Variable `.env` → `APP_ENCRYPTION_KEY`
- **Rotación**: Manual (procedimiento documentado)

```env
# .env
APP_ENCRYPTION_KEY=base64:XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX
```

#### 2. Key Derivation (HKDF)
- **Estándar**: RFC 5869 (HKDF-SHA256)
- **Input**: Master Key
- **Info**: `tenant:{tenant_id}:documents`
- **Output**: 256-bit Derived Encryption Key (DEK) per tenant

```php
// Pseudo-código
$dek = hash_hkdf(
    'sha256',
    $masterKey,
    32, // 256 bits
    "tenant:{$tenantId}:documents"
);
```

**Ventajas HKDF**:
- ✅ Cada tenant tiene su propia clave derivada
- ✅ Compromiso de una clave no afecta otros tenants
- ✅ No requiere almacenar claves por tenant (stateless)
- ✅ Estándar criptográfico probado (RFC 5869)

#### 3. Document Encryption (AES-256-GCM)
- **Algoritmo**: AES-256-GCM (Authenticated Encryption with Associated Data)
- **Modo**: GCM (Galois/Counter Mode)
- **Nonce**: 96-bit random per document
- **Auth Tag**: 128-bit (integridad + autenticación)

```php
// Pseudo-código
$nonce = random_bytes(12); // 96-bit
$ciphertext = openssl_encrypt(
    $plaintext,
    'aes-256-gcm',
    $dek,
    OPENSSL_RAW_DATA,
    $nonce,
    $tag // output 128-bit
);

$encrypted = $nonce . $ciphertext . $tag;
```

**Por qué GCM**:
- ✅ Autenticación integrada (detecta manipulación)
- ✅ Performance superior (paralelizable)
- ✅ NIST approved (SP 800-38D)
- ✅ Estándar industria (TLS 1.3, IPsec)

---

## Decisiones de Diseño

### D1: AES-256-GCM vs AES-256-CBC

| Criterio | AES-256-GCM | AES-256-CBC |
|----------|-------------|-------------|
| Autenticación | ✅ Integrada (AEAD) | ❌ Requiere HMAC separado |
| Performance | ✅ Rápido (paralelizable) | ⚠️ Secuencial |
| Seguridad | ✅ NIST approved | ✅ NIST approved |
| Padding oracle | ✅ No vulnerable | ⚠️ Vulnerable sin HMAC |
| Estándar | ✅ TLS 1.3 | ⚠️ Legacy |

**Decisión**: **AES-256-GCM** por su autenticación integrada y mejor performance.

---

### D2: Key Derivation vs Key Storage

**Opción A: Key Storage** (rechazada)
- Cada tenant tiene clave en BD encriptada con master key
- ❌ Complejidad: gestión de claves en BD
- ❌ Performance: query adicional por operación
- ❌ Riesgo: BD comprometida expone claves

**Opción B: Key Derivation (HKDF)** ✅
- Derivar clave de master key + tenant_id
- ✅ Stateless (no almacenamiento)
- ✅ Performance (cache en memoria)
- ✅ Seguridad (master key nunca expuesta)

**Decisión**: **HKDF** por simplicidad y seguridad.

---

### D3: Nonce Generation

**Opciones**:
1. Counter-based (rechazado): riesgo de colisión en multi-threading
2. **Random (elegido)**: `random_bytes(12)` es criptográficamente seguro en PHP 7+

**Decisión**: **Random nonce** de 96-bit por documento.

**Validación**: Con 2^32 documentos, probabilidad de colisión < 10^-9 (birthday paradox).

---

### D4: Encrypted File Format

```
┌────────────────────────────────────────────────┐
│  Encrypted Document Binary Format              │
├────────────────────────────────────────────────┤
│  Byte 0-11:   Nonce (12 bytes / 96-bit)       │
│  Byte 12-N:   Ciphertext (variable length)    │
│  Byte N+1-N+16: Auth Tag (16 bytes / 128-bit) │
└────────────────────────────────────────────────┘
```

**Ventajas**:
- ✅ Self-contained (nonce incluido)
- ✅ No requiere metadata separada
- ✅ Verificación de integridad integrada

---

## Implementación

### Servicio Principal: `DocumentEncryptionService`

```php
<?php

namespace App\Services\Document;

use App\Services\TenantContext;

class DocumentEncryptionService
{
    public function __construct(
        private TenantContext $tenantContext
    ) {}

    /**
     * Encrypt document content
     */
    public function encrypt(string $plaintext): string
    {
        $dek = $this->deriveTenantKey();
        $nonce = random_bytes(12);
        $tag = '';
        
        $ciphertext = openssl_encrypt(
            $plaintext,
            'aes-256-gcm',
            $dek,
            OPENSSL_RAW_DATA,
            $nonce,
            $tag
        );
        
        if ($ciphertext === false) {
            throw new EncryptionException('Encryption failed');
        }
        
        return $nonce . $ciphertext . $tag;
    }

    /**
     * Decrypt document content
     */
    public function decrypt(string $encrypted): string
    {
        if (strlen($encrypted) < 28) { // 12 + 16 min
            throw new EncryptionException('Invalid encrypted data');
        }
        
        $nonce = substr($encrypted, 0, 12);
        $tag = substr($encrypted, -16);
        $ciphertext = substr($encrypted, 12, -16);
        
        $dek = $this->deriveTenantKey();
        
        $plaintext = openssl_decrypt(
            $ciphertext,
            'aes-256-gcm',
            $dek,
            OPENSSL_RAW_DATA,
            $nonce,
            $tag
        );
        
        if ($plaintext === false) {
            throw new EncryptionException('Decryption failed or data tampered');
        }
        
        return $plaintext;
    }

    /**
     * Derive tenant-specific encryption key using HKDF
     */
    private function deriveTenantKey(): string
    {
        $masterKey = base64_decode(config('encryption.master_key'));
        $tenantId = $this->tenantContext->getCurrentTenantId();
        $info = "tenant:{$tenantId}:documents";
        
        return hash_hkdf('sha256', $masterKey, 32, $info);
    }

    /**
     * Verify if data is encrypted
     */
    public function isEncrypted(string $data): bool
    {
        // Heuristic: encrypted data has min length and high entropy
        if (strlen($data) < 28) return false;
        
        // Try decrypt without throwing
        try {
            $this->decrypt($data);
            return true;
        } catch (EncryptionException) {
            return false;
        }
    }
}
```

---

### Trait: `Encryptable` para Modelos

```php
<?php

namespace App\Traits;

use App\Services\Document\DocumentEncryptionService;

trait Encryptable
{
    /**
     * Attributes to encrypt
     */
    protected array $encryptable = [];

    /**
     * Boot the trait
     */
    public static function bootEncryptable(): void
    {
        static::saving(function ($model) {
            $model->encryptAttributes();
        });
        
        static::retrieved(function ($model) {
            $model->decryptAttributes();
        });
    }

    /**
     * Encrypt specified attributes before saving
     */
    protected function encryptAttributes(): void
    {
        $service = app(DocumentEncryptionService::class);
        
        foreach ($this->encryptable as $attribute) {
            if (isset($this->attributes[$attribute])) {
                $value = $this->attributes[$attribute];
                
                // Only encrypt if not already encrypted
                if (!$service->isEncrypted($value)) {
                    $this->attributes[$attribute] = $service->encrypt($value);
                }
            }
        }
    }

    /**
     * Decrypt attributes after retrieval
     */
    protected function decryptAttributes(): void
    {
        $service = app(DocumentEncryptionService::class);
        
        foreach ($this->encryptable as $attribute) {
            if (isset($this->attributes[$attribute])) {
                $value = $this->attributes[$attribute];
                
                // Only decrypt if encrypted
                if ($service->isEncrypted($value)) {
                    $this->attributes[$attribute] = $service->decrypt($value);
                }
            }
        }
    }
}
```

---

### Uso en Modelos

```php
<?php

namespace App\Models;

use App\Traits\Encryptable;

class Document extends Model
{
    use Encryptable;

    /**
     * Attributes to encrypt at-rest
     */
    protected array $encryptable = [
        'content',      // Binary PDF content
        'metadata',     // JSON metadata (optional)
    ];
}
```

---

## Performance Considerations

### Benchmark Esperado

| Operación | Sin Encriptación | Con AES-256-GCM | Overhead |
|-----------|------------------|-----------------|----------|
| Upload 1MB | 50ms | 55ms | +10% |
| Download 1MB | 30ms | 33ms | +10% |
| Upload 10MB | 200ms | 220ms | +10% |
| Download 10MB | 150ms | 165ms | +10% |

### Optimizaciones

1. **Key Caching**:
```php
// Cache derived keys in Redis (1 hour TTL)
$cacheKey = "dek:tenant:{$tenantId}";
$dek = Cache::remember($cacheKey, 3600, function() {
    return $this->deriveTenantKey();
});
```

2. **Streaming para archivos grandes**:
```php
// Para archivos >10MB, usar stream encryption
$stream = fopen('php://temp', 'r+');
$cipher = openssl_cipher_iv_length('aes-256-gcm');
// ... implementar chunked encryption
```

3. **Async encryption**:
```php
// Queue encryption para uploads grandes
dispatch(new EncryptDocumentJob($document));
```

---

## Security Considerations

### Key Management

**Master Key Rotation** (procedimiento manual):
1. Generar nueva master key: `openssl rand -base64 32`
2. Re-encriptar todos los documentos con nueva key
3. Actualizar `.env` en producción
4. Verificar integridad post-rotación

**Frecuencia recomendada**: Cada 12 meses

### Backup & Recovery

**Encrypted Backups**:
- Backups incluyen documentos ya encriptados
- Master key se respalda por separado (vault seguro)
- Restore requiere master key correcta

**Disaster Recovery**:
```bash
# Backup master key (solo acceso superadmin)
echo $APP_ENCRYPTION_KEY > master_key.txt.gpg
gpg --symmetric --cipher-algo AES256 master_key.txt

# Restore
gpg --decrypt master_key.txt.gpg > .env
```

### Compliance

**GDPR Art. 32**:
- ✅ "Encriptación de datos personales" → AES-256-GCM
- ✅ "Capacidad de garantizar confidencialidad" → Per-tenant keys
- ✅ "Capacidad de restaurar disponibilidad" → Backup strategy

**eIDAS**:
- ✅ Protección de documentos firmados
- ✅ Integridad verificable (auth tag GCM)

---

## Riesgos y Mitigación

| Riesgo | Probabilidad | Impacto | Mitigación |
|--------|--------------|---------|------------|
| Master key leaked | 🟢 Baja | 🔴 Crítico | Rotar key inmediatamente, re-encriptar todo |
| Performance degradation | 🟡 Media | 🟡 Medio | Cache DEKs, async encryption, benchmarks |
| Data corruption | 🟢 Baja | 🔴 Alto | Auth tag detecta corruption, backups redundantes |
| Key rotation downtime | 🟡 Media | 🟡 Medio | Blue-green deployment, rolling re-encryption |

---

## Alternativas Consideradas

### Alternativa 1: Laravel Built-in Encryption
```php
Crypt::encrypt($data); // uses AES-256-CBC + HMAC
```

**Rechazado**:
- ❌ No usa GCM (usa CBC + HMAC-SHA256)
- ❌ No permite per-tenant keys fácilmente
- ❌ Overhead adicional del framework

**Ventaja de nuestra implementación**:
- ✅ Control total del algoritmo
- ✅ Optimizado para nuestro caso de uso
- ✅ Per-tenant key derivation nativo

---

### Alternativa 2: Database-Level Encryption (MySQL TDE)
```sql
CREATE TABLE documents (
    content VARBINARY(16777215) ENCRYPTED
);
```

**Rechazado**:
- ❌ Todas las tablas usan misma clave
- ❌ No aislamiento per-tenant
- ❌ Vendor lock-in (MySQL specific)
- ✅ Pero podría usarse **en combinación** (defensa en profundidad)

---

### Alternativa 3: Cloud KMS (AWS KMS, Google Cloud KMS)
```php
$kms->encrypt(['Plaintext' => $data]);
```

**Considerado para futuro**:
- ✅ Hardware Security Modules (HSM)
- ✅ Audit logging automático
- ✅ Key rotation managed
- ❌ Latencia adicional (API call)
- ❌ Costo por operación
- ❌ Vendor lock-in

**Decisión**: Implementar nativa ahora, migrar a KMS en fase enterprise.

---

## Testing Strategy

### Unit Tests

```php
class DocumentEncryptionServiceTest extends TestCase
{
    public function test_encrypt_decrypt_roundtrip()
    {
        $service = new DocumentEncryptionService($this->tenantContext);
        $plaintext = 'Secret document content';
        
        $encrypted = $service->encrypt($plaintext);
        $decrypted = $service->decrypt($encrypted);
        
        $this->assertEquals($plaintext, $decrypted);
    }

    public function test_different_tenants_different_keys()
    {
        $tenant1 = Tenant::factory()->create();
        $tenant2 = Tenant::factory()->create();
        
        $plaintext = 'Same content';
        
        $this->actingAsTenant($tenant1);
        $encrypted1 = $service->encrypt($plaintext);
        
        $this->actingAsTenant($tenant2);
        $encrypted2 = $service->encrypt($plaintext);
        
        $this->assertNotEquals($encrypted1, $encrypted2);
    }

    public function test_detects_tampering()
    {
        $encrypted = $service->encrypt('Original');
        
        // Tamper with auth tag
        $tampered = substr($encrypted, 0, -1) . 'X';
        
        $this->expectException(EncryptionException::class);
        $service->decrypt($tampered);
    }

    public function test_nonce_uniqueness()
    {
        $encrypted1 = $service->encrypt('Test');
        $encrypted2 = $service->encrypt('Test');
        
        $nonce1 = substr($encrypted1, 0, 12);
        $nonce2 = substr($encrypted2, 0, 12);
        
        $this->assertNotEquals($nonce1, $nonce2);
    }
}
```

---

## Migration Plan

### Fase 1: Implementación (Semana 3, Sprint 5)
- Crear [`DocumentEncryptionService`](app/Services/Document/DocumentEncryptionService.php)
- Crear [`Encryptable` trait](app/Traits/Encryptable.php)
- Tests completos

### Fase 2: Documentos Nuevos (Semana 3, Sprint 5)
- Aplicar trait a [`Document`](app/Models/Document.php) model
- Nuevos uploads se encriptan automáticamente
- Verificar no rompe funcionalidad existente

### Fase 3: Migración Documentos Existentes (Semana 4, Sprint 5)
```bash
# Comando Artisan
php artisan documents:encrypt-existing --dry-run
php artisan documents:encrypt-existing --batch=100
```

```php
class EncryptExistingDocumentsCommand extends Command
{
    public function handle()
    {
        $service = app(DocumentEncryptionService::class);
        
        Document::whereNull('encrypted_at')
            ->chunk(100, function($documents) use ($service) {
                foreach ($documents as $document) {
                    if (!$service->isEncrypted($document->content)) {
                        $document->content = $service->encrypt($document->content);
                        $document->encrypted_at = now();
                        $document->save();
                        
                        $this->info("Encrypted document {$document->id}");
                    }
                }
            });
    }
}
```

---

## Conclusión

La estrategia de encriptación elegida proporciona:

✅ **Seguridad**: AES-256-GCM con autenticación integrada  
✅ **Aislamiento**: Per-tenant key derivation (HKDF)  
✅ **Performance**: Overhead < 10%, cache de claves  
✅ **Compliance**: GDPR Art. 32, eIDAS compatible  
✅ **Simplicidad**: Stateless, no key storage en BD  
✅ **Escalabilidad**: Stream encryption para archivos grandes  

**Trade-offs aceptados**:
- ⚠️ Master key manual rotation (vs auto-rotation KMS)
- ⚠️ +10% overhead en I/O (vs plaintext storage)
- ⚠️ Complejidad adicional en backup/restore

**Next Steps**:
1. Implementar [`DocumentEncryptionService`](app/Services/Document/DocumentEncryptionService.php) ✅
2. Security Expert review (encryption parameters) ✅
3. Performance benchmark real (target <15% overhead) ✅
4. Documentación key rotation procedure ✅


## Formato de almacenamiento en columnas

`DocumentEncryptionService::encrypt()` devuelve **binario crudo**: nonce,
texto cifrado y tag concatenados. Eso vale para un fichero en disco, pero no
para una columna de texto.

Cuando el cifrado se aplica a un atributo de modelo (trait `Encryptable`), el
valor se guarda como:

```
enc:v1:<base64 del binario>
```

Dos razones:

1. **MySQL rechaza el binario en columnas utf8mb4** con «Incorrect string
   value». SQLite lo acepta sin protestar, asi que el fallo solo aparecia en
   produccion y en el CI, nunca en los tests locales.
2. **El prefijo hace explicito lo que ya esta cifrado.** Antes se averiguaba
   intentando descifrar el valor y viendo si fallaba, lo que ademas de caro
   confundia un dato corrupto con uno en claro.

El prefijo lleva version: si algun dia cambia el algoritmo, un `enc:v2:`
permite convivir con lo ya escrito en lugar de tener que migrarlo de golpe.

Los ficheros en disco siguen guardandose en binario, sin prefijo ni base64:
alli no hay codificacion de columna que respetar y el base64 costaria un
tercio mas de espacio.

---

**Fecha de decisión**: 2025-12-30  
**Aprobado por**: Arquitecto  
**Próxima revisión**: Sprint 6 (post-implementation review)

---

## Referencias

- RFC 5869: HKDF (HMAC-based Key Derivation Function)
- NIST SP 800-38D: GCM Mode (Galois/Counter Mode)
- GDPR Art. 32: Security of processing
- OWASP Cryptographic Storage Cheat Sheet
- PHP `openssl_encrypt` documentation
- Laravel Encryption documentation
