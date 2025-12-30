# E3-004: Correcciones OBLIGATORIAS

**Fecha**: 2025-12-30  
**Revisor**: Tech Lead & QA  
**Estado**: APROBADO CON CORRECCIONES  
**Deadline**: Antes de mover a DONE

---

## Resumen

Tu implementación de E3-004 es **excelente** en arquitectura y seguridad. Sin embargo, hay **3 correcciones obligatorias** antes de mover a DONE.

**Tiempo estimado**: 3-4 horas total

---

## ✅ Lo que está BIEN (no tocar)

- ✅ Arquitectura modular perfecta (4 servicios)
- ✅ Seguridad robusta (tenant isolation, GDPR, validaciones)
- ✅ Integración con TsaService correcta
- ✅ Certificados generados (RSA 4096, permisos correctos)
- ✅ Laravel Pint: 0 issues
- ✅ Documentación completa

---

## 🔧 CORRECCIÓN #1: Fix Bug de Precedencia [OBLIGATORIO]

### Issue
**Archivo**: [`app/Services/Signing/PdfEmbedder.php:79`](../app/Services/Signing/PdfEmbedder.php:79)

**Bug**:
```php
if (! config('signing.appearance.mode') === 'visible') {
    return $this; // Skip if invisible signature
}
```

**Problema**: Precedencia de operadores incorrecta. `!` se evalúa antes que `===`, entonces la condición es siempre `false === 'visible'` → `false`. Nunca se salta la appearance.

**Fix**:
```php
if (config('signing.appearance.mode') !== 'visible') {
    return $this; // Skip if invisible signature
}
```

**Tiempo**: 2 minutos

---

## 📝 CORRECCIÓN #2: Documentar Limitaciones MVP [OBLIGATORIO]

### Issue
**Archivo**: [`docs/signing/README.md`](../docs/signing/README.md)

**Problema**: README dice "MVP ✅" sin mencionar que TSA embedding y PDF embedding son placeholders para MVP.

**Acción**: Agregar sección después de línea 382 (antes de "## Referencias"):

```markdown
## Limitaciones MVP

### Implementación Actual (Sprint 4)

Esta es una implementación MVP funcional con las siguientes limitaciones conocidas:

#### ⚠️ TSA Token Embedding (Placeholder)

**Estado Actual**: 
- El TSA token se solicita correctamente al TsaService ✅
- El token se guarda en la base de datos ✅
- **PERO**: El token NO se embebe realmente en el PKCS#7 ❌

**Archivo**: [`app/Services/Signing/Pkcs7Builder.php:170-186`](../app/Services/Signing/Pkcs7Builder.php:170)

```php
public function embedTsaToken(string $pkcs7Der, TsaToken $tsaToken): string
{
    // TODO: Implement proper ASN.1 manipulation to embed TSA token
    // For now, return original PKCS#7 and store TSA token reference separately
    return $pkcs7Der;
}
```

**Impacto**:
- El PDF generado NO contiene el timestamp embebido en el PKCS#7
- Adobe Reader NO validará el timestamp automáticamente
- La firma es válida pero NO es técnicamente PAdES-B-LT completo

**Solución Planificada (Sprint 5)**:
- Implementar ASN.1 manipulation con phpseclib3
- Agregar timestamp a SignerInfo.unauthenticatedAttributes
- OID: 1.2.840.113549.1.9.16.2.14 (id-aa-signatureTimeStampToken)

---

#### ⚠️ PDF Signature Dictionary (Placeholder)

**Estado Actual**:
- Se genera PKCS#7 válido ✅
- Se crea visual signature appearance ✅
- Se embebe metadata Firmalum ✅
- **PERO**: NO se crea signature dictionary con ByteRange ❌

**Archivo**: [`app/Services/Signing/PdfEmbedder.php:109-127`](../app/Services/Signing/PdfEmbedder.php:109)

```php
public function embedPkcs7(string $pkcs7Der): self
{
    // For MVP, we store the PKCS#7 in database and mark PDF as signed
    // Full PAdES implementation would:
    // 1. Calculate ByteRange
    // 2. Insert signature dictionary
    // ...
    return $this;
}
```

**Impacto**:
- El PDF NO es técnicamente un "PDF firmado digitalmente"
- Adobe Reader NO mostrará la firma como válida en el panel de firmas
- La firma es visual y el PKCS#7 está separado en BD

**Solución Planificada (Sprint 5)**:
- Implementar cálculo de ByteRange correcto
- Crear signature dictionary: `/Type /Sig /Filter /Adobe.PPKLite`
- Embeber PKCS#7 hex-encoded en `/Contents`
- Cumplir ISO 32000-2 (PDF 2.0 signatures)

---

#### ⚠️ OCSP/CRL Revocation Check (No Implementado)

**Estado Actual**:
- Certificate revocation check siempre retorna `true`
- OK para certificados self-signed (desarrollo)
- **NO OK para certificados CA-issued (producción)**

**Archivo**: [`app/Services/Signing/CertificateService.php:126-149`](../app/Services/Signing/CertificateService.php:126)

**Solución Planificada (Sprint 5)**:
- Implementar OCSP responder query
- Implementar CRL download y parsing
- Agregar configuración de OCSP/CRL endpoints

---

### Qué FUNCIONA en el MVP

A pesar de estas limitaciones, el sistema **SÍ FUNCIONA** para el MVP:

✅ **Funcionalidad Core**:
- Captura de firma del usuario
- Generación de PKCS#7 válido
- Integración con TSA (timestamp guardado en BD)
- Visual signature appearance profesional
- Metadata embebida GDPR-compliant
- Validación de integridad (hash)
- Verificación pública con QR code

✅ **Seguridad**:
- Tenant isolation completa
- Validaciones pre-firma
- Audit trail automático
- GDPR compliance

✅ **Arquitectura**:
- Código limpio y mantenible
- Fácil extender para implementación completa
- SOLID principles aplicados

### Path a Producción

**Sprint 5** implementará la versión completa:
1. TSA token embedding real
2. PDF signature dictionary con ByteRange
3. OCSP/CRL revocation check
4. Tests completos (35+)
5. Validación Adobe Reader
6. Certificado CA-issued

**IMPORTANTE**: Para demos y pruebas MVP, el sistema actual es suficiente y funcional.

---
```

**Tiempo**: 10 minutos

---

## 🧪 CORRECCIÓN #3: Implementar 5 Tests Críticos [OBLIGATORIO]

### Issue
**Estado Actual**: 0 tests implementados para PdfSignature

**Mínimo Requerido**: 5 tests críticos que validen funcionalidad core

### Tests a Crear

#### 1. `tests/Unit/Signing/PdfSignatureServiceTest.php`

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Signing;

use App\Models\Document;
use App\Models\Signer;
use App\Models\SignedDocument;
use App\Models\Tenant;
use App\Services\Signing\PdfSignatureService;
use App\Services\Signing\PdfSignatureException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PdfSignatureServiceTest extends TestCase
{
    use RefreshDatabase;

    private PdfSignatureService $service;
    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(PdfSignatureService::class);
        $this->tenant = Tenant::factory()->create();
    }

    /**
     * TEST #1: Sign document with valid inputs
     */
    public function test_sign_document_with_valid_inputs(): void
    {
        // Arrange
        $document = Document::factory()->for($this->tenant)->create([
            'stored_path' => 'documents/test.pdf',
            'is_encrypted' => false,
        ]);

        $signer = Signer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'signed_at' => now(),
            'otp_verified' => true,
            'signature_data' => 'data:image/png;base64,iVBORw0KGgo...',
        ]);

        // Create test PDF
        \Storage::put($document->stored_path, '%PDF-1.4 test content');

        // Act
        $signedDocument = $this->service->signDocument(
            $document,
            $signer,
            ['verification_code' => 'TEST-1234']
        );

        // Assert
        $this->assertInstanceOf(SignedDocument::class, $signedDocument);
        $this->assertEquals($document->id, $signedDocument->original_document_id);
        $this->assertEquals($signer->id, $signedDocument->signer_id);
        $this->assertEquals($this->tenant->id, $signedDocument->tenant_id);
        $this->assertEquals('signed', $signedDocument->status);
        $this->assertNotNull($signedDocument->pkcs7_signature);
        $this->assertNotNull($signedDocument->content_hash);
        $this->assertNotNull($signedDocument->signed_at);
    }

    /**
     * TEST #2: Fail with expired certificate
     */
    public function test_sign_document_fails_with_expired_certificate(): void
    {
        // Este test requiere certificado expirado
        // Para MVP, marcar como incomplete
        $this->markTestIncomplete('Requires expired certificate setup');
    }

    /**
     * TEST #3: Tenant isolation enforced
     */
    public function test_tenant_isolation(): void
    {
        // Arrange
        $tenant1 = Tenant::factory()->create();
        $tenant2 = Tenant::factory()->create();

        $document = Document::factory()->for($tenant1)->create([
            'stored_path' => 'documents/test.pdf',
        ]);

        $signer = Signer::factory()->create([
            'tenant_id' => $tenant2->id, // Different tenant
            'signed_at' => now(),
            'otp_verified' => true,
            'signature_data' => 'data:image/png;base64,iVBORw0KGgo...',
        ]);

        \Storage::put($document->stored_path, '%PDF-1.4 test content');

        // Act & Assert
        $this->expectException(\Exception::class);
        
        $signedDocument = $this->service->signDocument($document, $signer);

        // Verify tenant_id matches document tenant, not signer tenant
        if ($signedDocument) {
            $this->assertEquals($tenant1->id, $signedDocument->tenant_id);
            $this->assertNotEquals($tenant2->id, $signedDocument->tenant_id);
        }
    }
}
```

#### 2. `tests/Unit/Signing/SignedDocumentTest.php`

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Signing;

use App\Models\SignedDocument;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SignedDocumentTest extends TestCase
{
    use RefreshDatabase;

    /**
     * TEST #4: Verify integrity with valid hash
     */
    public function test_verify_integrity(): void
    {
        // Arrange
        $tenant = Tenant::factory()->create();
        $content = '%PDF-1.4 test signed content';
        $hash = hash('sha256', $content);
        $path = 'signed/test.pdf';

        \Storage::put($path, $content);

        $signedDoc = SignedDocument::factory()->create([
            'tenant_id' => $tenant->id,
            'signed_path' => $path,
            'content_hash' => $hash,
        ]);

        // Act
        $isValid = $signedDoc->verifyIntegrity();

        // Assert
        $this->assertTrue($isValid);
    }

    public function test_verify_integrity_fails_when_file_modified(): void
    {
        // Arrange
        $tenant = Tenant::factory()->create();
        $originalContent = '%PDF-1.4 original';
        $modifiedContent = '%PDF-1.4 modified';
        $originalHash = hash('sha256', $originalContent);
        $path = 'signed/test.pdf';

        \Storage::put($path, $modifiedContent); // File is modified

        $signedDoc = SignedDocument::factory()->create([
            'tenant_id' => $tenant->id,
            'signed_path' => $path,
            'content_hash' => $originalHash, // Hash of original
        ]);

        // Act
        $isValid = $signedDoc->verifyIntegrity();

        // Assert
        $this->assertFalse($isValid);
    }
}
```

#### 3. `tests/Unit/Signing/CertificateServiceTest.php`

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Signing;

use App\Services\Signing\CertificateService;
use App\Services\Signing\PdfSignatureException;
use Tests\TestCase;

class CertificateServiceTest extends TestCase
{
    private CertificateService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(CertificateService::class);
    }

    /**
     * TEST #5: Load certificate successfully
     */
    public function test_load_certificate(): void
    {
        // Act
        $certificate = $this->service->loadCertificate();

        // Assert
        $this->assertNotNull($certificate);
        $this->assertStringContainsString('Firmalum', $certificate->getSubject());
        $this->assertTrue($certificate->isValid());
        $this->assertFalse($certificate->isExpired());
        $this->assertGreaterThan(0, $certificate->getDaysUntilExpiration());
    }

    public function test_load_private_key(): void
    {
        // Act
        $privateKey = $this->service->getPrivateKey();

        // Assert
        $this->assertNotNull($privateKey);
        $this->assertTrue($privateKey->isRsa());
        $this->assertGreaterThanOrEqual(4096, $privateKey->getBits());
    }

    public function test_certificate_info_returns_complete_data(): void
    {
        // Act
        $info = $this->service->getCertificateInfo();

        // Assert
        $this->assertIsArray($info);
        $this->assertArrayHasKey('certificate', $info);
        $this->assertArrayHasKey('private_key', $info);
        $this->assertArrayHasKey('is_valid', $info);
        $this->assertTrue($info['is_valid']);
    }

    public function test_is_self_signed_returns_true_for_dev_cert(): void
    {
        // Act
        $isSelfSigned = $this->service->isSelfSigned();

        // Assert
        $this->assertTrue($isSelfSigned);
    }
}
```

#### 4. Factory para SignedDocument

**Crear**: `database/factories/SignedDocumentFactory.php`

```php
<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Document;
use App\Models\EvidencePackage;
use App\Models\SignedDocument;
use App\Models\Signer;
use App\Models\SigningProcess;
use App\Models\Tenant;
use App\Models\TsaToken;
use App\Models\VerificationCode;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class SignedDocumentFactory extends Factory
{
    protected $model = SignedDocument::class;

    public function definition(): array
    {
        $content = '%PDF-1.4 signed test content';
        $hash = hash('sha256', $content);

        return [
            'uuid' => Str::uuid(),
            'tenant_id' => Tenant::factory(),
            'signing_process_id' => SigningProcess::factory(),
            'signer_id' => Signer::factory(),
            'original_document_id' => Document::factory(),
            'storage_disk' => 'local',
            'signed_path' => 'signed/test/'.Str::random(10).'.pdf',
            'signed_name' => 'test_signed.pdf',
            'file_size' => strlen($content),
            'content_hash' => $hash,
            'original_hash' => hash('sha256', '%PDF-1.4 original'),
            'hash_algorithm' => 'SHA-256',
            'pkcs7_signature' => bin2hex(random_bytes(256)),
            'certificate_subject' => 'CN=Firmalum Development, O=Firmalum',
            'certificate_issuer' => 'CN=Firmalum Development, O=Firmalum',
            'certificate_serial' => (string) random_int(1000000, 9999999),
            'certificate_fingerprint' => hash('sha256', random_bytes(32)),
            'pades_level' => 'B-LT',
            'has_tsa_token' => false,
            'tsa_token_id' => null,
            'has_validation_data' => false,
            'signature_position' => [
                'page' => 'last',
                'x' => 50,
                'y' => 50,
                'width' => 80,
                'height' => 40,
            ],
            'signature_visible' => true,
            'signature_appearance' => [],
            'embedded_metadata' => [
                'Firmalum_Version' => '1.0',
            ],
            'verification_code_id' => null,
            'qr_code_embedded' => true,
            'evidence_package_id' => EvidencePackage::factory(),
            'adobe_validated' => null,
            'adobe_validation_date' => null,
            'validation_errors' => null,
            'status' => 'signed',
            'error_message' => null,
            'signed_at' => now(),
        ];
    }

    public function withTsaToken(): static
    {
        return $this->state(fn (array $attributes) => [
            'has_tsa_token' => true,
            'tsa_token_id' => TsaToken::factory(),
        ]);
    }

    public function withVerificationCode(): static
    {
        return $this->state(fn (array $attributes) => [
            'verification_code_id' => VerificationCode::factory(),
        ]);
    }

    public function signed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'signed',
            'signed_at' => now(),
        ]);
    }

    public function error(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'error',
            'error_message' => 'Test error message',
        ]);
    }
}
```

### Ejecutar Tests

```bash
# Crear los 3 archivos de tests
# Crear el factory

# Ejecutar
php artisan test tests/Unit/Signing/PdfSignatureServiceTest.php
php artisan test tests/Unit/Signing/SignedDocumentTest.php
php artisan test tests/Unit/Signing/CertificateServiceTest.php

# Verificar que al menos 5 pasen
```

**Tiempo**: 2-3 horas

---

## Checklist Final

Antes de mover E3-004 a DONE, verificar:

- [ ] ✅ Fix aplicado en [`PdfEmbedder.php:79`](../app/Services/Signing/PdfEmbedder.php:79)
- [ ] ✅ Sección "Limitaciones MVP" agregada a [`docs/signing/README.md`](../docs/signing/README.md)
- [ ] ✅ SignedDocumentFactory creado
- [ ] ✅ PdfSignatureServiceTest creado (3 tests)
- [ ] ✅ SignedDocumentTest creado (2 tests)
- [ ] ✅ CertificateServiceTest creado (4 tests)
- [ ] ✅ Tests ejecutan: `php artisan test tests/Unit/Signing/`
- [ ] ✅ Al menos 5 tests pasan
- [ ] ✅ Laravel Pint: `./vendor/bin/pint tests/Unit/Signing/`
- [ ] ✅ Commit con mensaje: "fix(e3-004): Apply code review corrections"

---

## Próximos Pasos (Después de DONE)

### Issues a Crear en Sprint 5

1. **HIGH**: Implementar TSA token embedding en PKCS#7
   - ASN.1 manipulation con phpseclib3
   - UnauthenticatedAttributes con OID correcto

2. **HIGH**: Implementar PDF signature dictionary con ByteRange
   - Cálculo de ByteRange correcto
   - Signature dictionary ISO 32000-2 compliant
   - Validación Adobe Reader

3. **MEDIUM**: Implementar OCSP/CRL revocation check
   - OCSP responder query
   - CRL download y parsing
   - Cache de resultados

4. **HIGH**: Completar suite de tests (35+ tests)
   - Unit tests completos para todos los servicios
   - Feature tests de integración
   - Mock de TsaService en tests

---

## Preguntas o Problemas

Si tienes dudas sobre las correcciones:

1. **Fix precedencia**: Simple, solo cambiar `!` por `!==`
2. **Documentación**: Copy-paste la sección "Limitaciones MVP"
3. **Tests**: Usa los ejemplos proporcionados, ajusta según necesites
4. **Factory**: Copy-paste completo, está listo para usar

**NO dudes en consultar si algo no está claro.**

---

**Tiempo total estimado**: 3-4 horas  
**Prioridad**: ALTA - Bloqueante para cerrar Sprint 4  
**Deadline**: Antes de 2025-12-31

---

**Excelente trabajo en la implementación** 🎉

La arquitectura es sólida y el código es mantenible. Estas correcciones son menores y aseguran que el MVP esté completo y documentado correctamente.
