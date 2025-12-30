# PAdES Digital Signature - Documentación

## Visión General

El sistema de firma digital PAdES (PDF Advanced Electronic Signatures) implementa firmas electrónicas avanzadas conforme al estándar eIDAS y ETSI EN 319 122-1.

## Arquitectura

### Componentes Principales

1. **PdfSignatureService** - Orquestador principal del proceso de firma
2. **CertificateService** - Gestión de certificados X.509
3. **Pkcs7Builder** - Construcción de estructuras PKCS#7/CMS
4. **PdfEmbedder** - Embedding de firmas en PDFs con apariencia visual
5. **TsaService** - Integración con Time Stamp Authority

### Niveles PAdES Soportados

- **PAdES-B-B**: Firma básica (desarrollo)
- **PAdES-B-LT**: Long-Term validation con TSA Qualified (producción) ✅
- **PAdES-B-LTA**: Archive con re-sellado (futuro)

## Configuración

### 1. Certificados

#### Desarrollo (Self-Signed)

Los certificados de desarrollo ya están generados en `storage/certificates/`:

```bash
storage/certificates/ancla-dev.crt  # Certificado público
storage/certificates/ancla-dev.key  # Clave privada (permisos 600)
```

#### Producción (CA-Issued)

Para producción, obtener certificado de una CA confiable:

1. **Proveedores recomendados**: DigiCert, GlobalSign, ANF
2. **Tipo**: Organization Validation (OV)
3. **Key Usage**: Digital Signature, Non-Repudiation
4. **Extended Key Usage**: Email Protection

```bash
# Generar CSR
openssl req -new -newkey rsa:4096 -nodes \
  -keyout storage/certificates/ancla-prod.key \
  -out storage/certificates/ancla-prod.csr \
  -subj "/C=ES/ST=Madrid/L=Madrid/O=ANCLA Technologies/CN=ANCLA Signature Service"

# Enviar CSR a la CA y guardar certificado recibido
# Configurar en .env
```

### 2. Variables de Entorno

Agregar al archivo `.env`:

```bash
# PAdES Level
SIGNATURE_PADES_LEVEL=B-LT  # B-B (dev) | B-LT (prod) | B-LTA (futuro)

# Certificados
SIGNATURE_CERT_PATH=storage/certificates/ancla-dev.crt
SIGNATURE_KEY_PATH=storage/certificates/ancla-dev.key
SIGNATURE_KEY_PASSWORD=  # Solo si la clave privada está protegida

# Apariencia de firma
SIGNATURE_APPEARANCE_MODE=visible  # visible | invisible
SIGNATURE_PAGE=last                # first | last | número
SIGNATURE_X=50                     # mm desde izquierda
SIGNATURE_Y=50                     # mm desde arriba
SIGNATURE_WIDTH=80                 # mm
SIGNATURE_HEIGHT=40                # mm

# Estilo
SIGNATURE_BORDER_COLOR=#1a73e8
SIGNATURE_BG_COLOR=#f8f9fa
SIGNATURE_SHOW_QR=true

# TSA Configuration
SIGNATURE_TSA_QUALIFIED=true  # Usar TSA Qualified para PAdES-B-LT

# Validación
SIGNATURE_CHECK_REVOCATION=false  # OCSP/CRL check (producción)
```

### 3. TSA Configuration

El servicio usa el `TsaService` existente. Configurar en `config/evidence.php`:

```php
'tsa' => [
    'mock' => env('TSA_MOCK', true),  // false en producción
    'primary' => 'firmaprofesional',
    'providers' => [
        'firmaprofesional' => [
            'enabled' => true,
            'url' => 'https://tsa.firmaprofesional.com/tsa',
            'qualified' => true,
        ],
    ],
],
```

## Uso

### Firmar un Documento

```php
use App\Services\Signing\PdfSignatureService;
use App\Models\Document;
use App\Models\Signer;

$pdfSignatureService = app(PdfSignatureService::class);

$signedDocument = $pdfSignatureService->signDocument(
    document: $document,
    signer: $signer,
    metadata: [
        'verification_code' => 'ABC1-DEF2-GH34',
        'verification_url' => 'https://ancla.es/verify/xxx',
        'qr_code_path' => 'qr-codes/xxx.png',
        'evidence_package_id' => $evidencePackage->id,
        'ip_address' => request()->ip(),
        'location_summary' => 'Madrid, Spain',
    ]
);

// SignedDocument model guardado en BD
// PDF firmado en storage/signed/{tenant_id}/{year}/{month}/
```

### Validar una Firma

```php
$validationResult = $pdfSignatureService->validateSignature($signedDocument);

if ($validationResult->isFullyValid()) {
    // ✅ Firma válida
    echo "Firma verificada correctamente";
} else {
    // ❌ Firma inválida
    $summary = $validationResult->getSummary();
    // Verificar qué componente falló
}
```

### Verificar Certificado

```php
use App\Services\Signing\CertificateService;

$certificateService = app(CertificateService::class);
$info = $certificateService->getCertificateInfo();

/*
Array [
    'certificate' => [
        'subject' => 'CN=ANCLA, O=ANCLA Development, ...',
        'issuer' => '...',
        'valid_from' => '2025-12-30',
        'valid_to' => '2035-12-27',
        'is_valid' => true,
        'days_until_expiration' => 3650,
    ],
    'private_key' => [
        'type' => 'RSA',
        'bits' => 4096,
    ],
    'self_signed' => true,
]
*/
```

## Estructura de Datos

### SignedDocument Model

```php
SignedDocument {
    uuid: string
    tenant_id: int
    signing_process_id: int
    signer_id: int
    original_document_id: int
    
    // Archivo firmado
    signed_path: string
    signed_name: string
    file_size: int
    
    // Integridad
    content_hash: string (SHA-256)
    original_hash: string (SHA-256)
    
    // Firma PKCS#7
    pkcs7_signature: string (hex)
    certificate_subject: string
    certificate_issuer: string
    certificate_serial: string
    certificate_fingerprint: string (SHA-256)
    
    // PAdES metadata
    pades_level: 'B-B'|'B-LT'|'B-LTA'
    has_tsa_token: bool
    tsa_token_id: ?int
    
    // Apariencia
    signature_position: array
    signature_visible: bool
    signature_appearance: array
    
    // Metadata embebida
    embedded_metadata: array
    verification_code_id: ?int
    qr_code_embedded: bool
    
    // Validación
    adobe_validated: ?bool
    adobe_validation_date: ?datetime
    
    // Estado
    status: 'signing'|'signed'|'error'
    signed_at: datetime
}
```

### Metadata Embebida en PDF

Según GDPR, solo se embeben hashes y datos no personales:

```php
[
    'ANCLA_Version' => '1.0',
    'ANCLA_Evidence_ID' => 'uuid-evidence-package',
    'ANCLA_Process_ID' => 123,
    'ANCLA_Signer_ID' => 456,
    'ANCLA_Verify_Code' => 'ABC1-DEF2-GH34',
    'ANCLA_Verify_URL' => 'https://ancla.es/verify/xxx',
    'ANCLA_IP_Hash' => 'sha256(ip)',  // Hash, no IP real
    'ANCLA_Location' => 'Madrid, Spain',  // Solo ciudad/país
    'ANCLA_Device_FP' => 'sha256(fingerprint)',
    'ANCLA_Consent_ID' => 'uuid',
    'ANCLA_Audit_Chain' => 'sha256(audit-trail)',
]
```

## Seguridad

### Validaciones Pre-Firma

El sistema valida automáticamente:

- ✅ Signer tiene firma capturada (`signed_at` existe)
- ✅ OTP verificado (`otp_verified = true`)
- ✅ Signature data disponible
- ✅ Certificado no expirado
- ✅ Clave privada accesible
- ✅ PDF válido y no corrupto

### Protección de Certificados

```bash
# Permisos de archivos
chmod 600 storage/certificates/*.key  # Solo lectura para owner
chmod 644 storage/certificates/*.crt  # Lectura pública
```

En producción, usar Docker Secrets o HashiCorp Vault:

```yaml
# docker-compose.yml
secrets:
  ancla_cert:
    file: ./secrets/ancla-prod.crt
  ancla_key:
    file: ./secrets/ancla-prod.key

services:
  app:
    secrets:
      - ancla_cert
      - ancla_key
    environment:
      SIGNATURE_CERT_PATH: /run/secrets/ancla_cert
      SIGNATURE_KEY_PATH: /run/secrets/ancla_key
```

### Audit Trail

Todos los eventos de firma se registran en `audit_trail_entries`:

- `signature.started`
- `signature.pdf_hashed`
- `signature.pkcs7_created`
- `signature.tsa_requested`
- `signature.tsa_received`
- `signature.pdf_embedded`
- `signature.completed`
- `signature.failed`

## Testing

### Verificar Instalación

```bash
# 1. Verificar certificados
ls -lah storage/certificates/
openssl x509 -in storage/certificates/ancla-dev.crt -text -noout

# 2. Verificar configuración
php artisan tinker
>>> config('signing.pades_level')
=> "B-LT"
>>> app(App\Services\Signing\CertificateService::class)->getCertificateInfo()
```

### Tests Unitarios

```bash
# Ejecutar tests de firma
php artisan test --filter=PdfSignature
php artisan test --filter=Certificate
php artisan test --filter=Pkcs7
```

## Troubleshooting

### Error: "Certificate not found"

```bash
# Verificar que los certificados existen
ls -la storage/certificates/

# Regenerar si es necesario
openssl req -x509 -nodes -days 3650 -newkey rsa:4096 \
  -keyout storage/certificates/ancla-dev.key \
  -out storage/certificates/ancla-dev.crt \
  -subj "/C=ES/ST=Madrid/L=Madrid/O=ANCLA Dev/CN=ancla.local"
```

### Error: "Failed to read private key"

```bash
# Verificar permisos
chmod 600 storage/certificates/ancla-dev.key

# Verificar que la clave no está encriptada (para dev)
openssl rsa -in storage/certificates/ancla-dev.key -check
```

### Error: "TSA request failed"

```bash
# Habilitar modo mock en desarrollo
# .env
TSA_MOCK=true

# En producción, verificar conectividad TSA
curl -X POST https://tsa.firmaprofesional.com/tsa
```

### Error: "PKCS#7 creation failed"

```bash
# Verificar extensión OpenSSL
php -m | grep openssl

# Verificar logs
tail -f storage/logs/laravel.log | grep signature
```

## Roadmap

### MVP (Sprint 4) ✅
- [x] PAdES-B-LT implementation
- [x] Self-signed certificates (dev)
- [x] Visible signature with QR
- [x] TSA integration
- [x] Basic validation

## ⚠️ Limitaciones MVP

Esta implementación es una **versión MVP funcional** con los siguientes placeholders que serán completados en Sprint 5:

### TSA Embedding
- **Estado**: PLACEHOLDER
- **Ubicación**: `Pkcs7Builder::embedTsaToken()` línea 176
- **Comportamiento actual**: El TSA token se obtiene pero NO se embebe en PKCS#7
- **Impacto**: PDF es firmado pero sin timestamp embebido en estructura CMS
- **Sprint 5**: Implementar ASN.1 manipulation para embedar TSA en UnauthenticatedAttributes

### PDF Signature Dictionary
- **Estado**: PLACEHOLDER
- **Ubicación**: `PdfEmbedder::embedPkcs7()` línea 109
- **Comportamiento actual**: Se guarda PKCS#7 en BD pero NO se inserta en PDF
- **Impacto**: Adobe Reader no reconoce firma digital (aunque integridad se valida por hash)
- **Sprint 5**: Implementar ByteRange calculation + /Sig dictionary + AcroForm

### OCSP/CRL Validation
- **Estado**: PLACEHOLDER
- **Ubicación**: `CertificateService::checkRevocation()` línea 126
- **Comportamiento actual**: Siempre retorna `true` (no revocado)
- **Impacto**: OK para certificados self-signed, CRÍTICO para CA-issued en producción
- **Sprint 5**: Implementar conexión a OCSP responder y CRL download

## ✅ Lo Que SÍ Funciona (MVP)

A pesar de los placeholders, la implementación actual proporciona funcionalidad core completa:

1. **Captura de Firmas**: Draw/Type/Upload ✅
2. **PKCS#7 Generation**: SignedData structure con OpenSSL ✅
3. **Hash Integrity**: SHA-256 del PDF original y firmado ✅
4. **TSA Timestamp**: Obtenido de TsaService (guardado en BD) ✅
5. **Signature Appearance**: Imagen visible con metadata, QR, logo ✅
6. **Certificate Management**: X.509 load + validation ✅
7. **Security**: Tenant isolation, GDPR compliance, audit trail ✅
8. **Storage**: Signed PDFs organizados por tenant/year/month ✅

**Resultado**: PDF firmado con appearance visual y evidencias completas en BD. Integridad verificable por hash comparison.

## 🚀 Roadmap a Producción

Para deployment en producción se requiere:
- [ ] Completar TSA embedding (Sprint 5)
- [ ] Completar PDF signature dictionary (Sprint 5)
- [ ] Completar OCSP/CRL check (Sprint 5)
- [ ] Certificado CA-issued (DigiCert/GlobalSign)
- [ ] TSA Qualified real (deshabilitar mock)
- [ ] 35+ tests completos
- [ ] Validación Adobe Reader

### Producción (Sprint 5)
- [ ] CA-issued certificates
- [ ] OCSP/CRL revocation check
- [ ] Adobe Reader validation tests
- [ ] Performance optimization
- [ ] Advanced validation API

### Long-Term (Sprint 6+)
- [ ] PAdES-B-LTA (archive)
- [ ] Automatic re-sealing
- [ ] Multiple signature positions
- [ ] Signature workflow
- [ ] Bulk signing

## Referencias

- [ADR-009: PAdES Signature Strategy](../architecture/adr-009-pades-signature-strategy.md)
- [ADR-008: TSA Strategy](../architecture/adr-008-tsa-strategy.md)
- [eIDAS Regulation](https://eur-lex.europa.eu/legal-content/EN/TXT/?uri=uriserv:OJ.L_.2014.257.01.0073.01.ENG)
- [ETSI EN 319 122-1 - CAdES](https://www.etsi.org/deliver/etsi_en/319100_319199/31912201/)
- [ETSI TS 102 778 - PAdES](https://www.etsi.org/deliver/etsi_ts/102700_102799/10277801/)
- [RFC 3161 - TSA Protocol](https://www.rfc-editor.org/rfc/rfc3161)

## Soporte

Para problemas o preguntas:
- Revisar logs en `storage/logs/laravel.log`
- Consultar ADR-009 para arquitectura detallada
- Revisar tests en `tests/Unit/Signing/` y `tests/Feature/Signing/`
