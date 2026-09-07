# ADR-009: Estrategia de Firma Electrónica PAdES

- **Estado**: Aceptado
- **Fecha**: 2025-12-29
- **Backlog Items**: E3-004 (Sprint 4 - CRÍTICO)
- **Autor**: Arquitecto de Software
- **Prioridad**: CRÍTICA - BLOQUEANTE
- **Dependencias**: ADR-008 (TSA Strategy), ADR-006 (Evidence Capture)

## Contexto

El Sprint 4 requiere implementar la funcionalidad CORE del producto: **firma electrónica avanzada sobre PDF** conforme al estándar **PAdES (PDF Advanced Electronic Signatures)** según eIDAS.

### Requerimientos Legales (eIDAS)

| Requisito | Normativa | Implementación |
|-----------|-----------|----------------|
| Firma electrónica avanzada | eIDAS Art. 26 | Certificado X.509 + PKCS#7 |
| Identificación del firmante | eIDAS Art. 24 | OTP por email/SMS |
| Fecha cierta oponible | eIDAS Art. 41 | TSA Qualified (RFC 3161) |
| Integridad a largo plazo | ETSI EN 319 122-1 | PAdES-LTV con re-sellado |
| Validación independiente | eIDAS Art. 32 | Adobe Reader compatible |
| Metadata de evidencias | Ley 59/2003 Art. 6 | Embedded en PDF |

### Problemática

La tarea E3-004 está **BLOQUEADA** esperando decisiones arquitectónicas sobre:

1. **Librería PHP**: ¿Qué librería usar para manipular PDFs y aplicar firmas digitales?
2. **Nivel PAdES**: ¿PAdES-B-B (básico), PAdES-B-LT (long-term), o PAdES-B-LTA (archive)?
3. **Certificado X.509**: ¿Self-signed para dev? ¿CA para producción? ¿Quién firma?
4. **Estructura PKCS#7**: ¿Cómo construir el CMS signature container?
5. **Posición de firma**: ¿Firma visible o invisible? ¿Dónde embeber?
6. **Integración TSA**: ¿Cómo integrar el TsaService existente con la firma?
7. **Metadata**: ¿Cómo embeber evidencias (IP, geo, device) en el PDF?

### Deadline

**Semana 1 del Sprint 4** - Este ADR debe estar completo para que el Developer pueda implementar E3-004 en Semana 3.

---

## Análisis de Alternativas

### 1. Librerías PHP para Firma Digital

#### Opción A: TCPDF + phpseclib/phpseclib
```php
Características:
✅ PDF generation y manipulation
✅ Soporte PKCS#7/CMS con phpseclib
✅ Firma digital básica integrada
❌ PAdES compliance limitado
❌ No soporta LTV out-of-the-box
❌ Firma visible simple pero no PAdES-compliant
```

**Evaluación**: ⚠️ Bueno para MVPs pero NO cumple PAdES estricto.

---

#### Opción B: setasign/Fpdi + setasign/fpdf_signature
```php
Características:
✅ Import PDFs existentes (crítico para nosotros)
✅ Librería fpdf_signature específica para firma
✅ Soporte PKCS#7 completo
⚠️ Requiere OpenSSL extension
❌ Documentación escasa para PAdES
❌ No incluye TSA integration
```

**Evaluación**: ⚠️ Flexible pero requiere mucho código custom.

---

#### Opción C: digitalsignature/digitalsignature
```php
Características:
✅ Especializada en firma digital PDF
✅ PAdES-B-B support nativo
✅ Adobe Reader compatible
❌ Librería pequeña, poca comunidad
❌ Sin actualizaciones recientes
❌ No soporta LTV directamente
```

**Evaluación**: ❌ Riesgoso para producción (mantenimiento).

---

#### Opción D: setasign/SetaPDF-Signer (Comercial)
```php
Características:
✅✅ PAdES-B-B, B-T, B-LT, B-LTA completo
✅✅ Adobe Reader validación perfecta
✅✅ TSA integration built-in
✅✅ Documentación excelente
✅ Firma visible/invisible
✅ Metadata embedding
❌ Licencia comercial (~€500/año)
❌ Vendor lock-in
```

**Evaluación**: ✅ **MEJOR opción técnica** pero costo.

---

#### Opción E: Enfoque Híbrido (RECOMENDADO)
```php
Librerías:
- setasign/fpdi: Import PDFs existentes
- phpseclib/phpseclib: Cryptografía PKCS#7
- Custom PdfSignatureService: Orquestación
- OpenSSL PHP extension: Operaciones crypto

Flujo:
1. FPDI importa PDF original
2. Posicionar firma imagen + metadata
3. phpseclib crea PKCS#7 signature
4. TsaService obtiene timestamp
5. Embeber PKCS#7 + TSA en PDF structure
6. Generar PDF firmado
```

**Evaluación**: ✅✅ **RECOMENDADO** - Balance costo/control/compliance.

---

### 2. Nivel PAdES: Comparativa

#### PAdES-B-B (Basic)
```
Estructura:
- Signature dictionary (/Sig)
- PKCS#7 signature
- Certificado X.509 embebido
- Hash SHA-256 del PDF

Validez: Mientras certificado sea válido
Costo TSA: $0 (no requiere timestamp)
Compliance: ⚠️ Básico eIDAS

Ventajas:
✅ Simple de implementar
✅ Sin costos operativos
✅ Suficiente para identificación

Desventajas:
❌ No long-term validity
❌ Depende de certificado activo
❌ Sin "fecha cierta" legal
```

**Evaluación**: ⚠️ Insuficiente para cumplimiento eIDAS completo.

---

#### PAdES-B-LT (Long-Term)
```
Estructura:
- Todo de B-B +
- TSA timestamp (RFC 3161)
- Validation data (OCSP, CRL)
- DSS dictionary embebido

Validez: Extendida más allá del certificado
Costo TSA: ~$0.15 por firma (Qualified)
Compliance: ✅ eIDAS completo

Ventajas:
✅✅ "Fecha cierta" legal
✅✅ Validación long-term
✅ Adobe Reader full support
✅ Inversión carga prueba

Desventajas:
⚠️ Requiere TSA Qualified
⚠️ Costo por firma
⚠️ Más complejo implementar
```

**Evaluación**: ✅✅ **RECOMENDADO** - Balance perfecto para MVP Comercial.

---

#### PAdES-B-LTA (Archive)
```
Estructura:
- Todo de B-LT +
- Document Timestamp (re-sellado periódico)
- Archive timestamp chain
- Gestión long-term (>5 años)

Validez: Indefinida con re-sellado
Costo TSA: $0.15 inicial + re-sellados anuales
Compliance: ✅✅ eIDAS + Archivo

Ventajas:
✅✅ Validez indefinida
✅✅ Cumplimiento archivado 5+ años
✅ Protección contra obsolescencia

Desventajas:
❌ Muy complejo implementar
❌ Requiere re-sellado automático
❌ Overkill para MVP
```

**Evaluación**: 🔮 Futuro (Sprint 6+) - Aprovechar LongTermArchiveService ya implementado.

---

## Decisiones

### Decisión 1: Librería Principal

**DECISIÓN**: Enfoque Híbrido con Open Source

```php
// composer.json
{
    "require": {
        "setasign/fpdi": "^2.6",           // Import PDFs
        "phpseclib/phpseclib": "^3.0",     // Crypto PKCS#7
        "smalot/pdfparser": "^2.7"         // Metadata extraction
    },
    "require-dev": {
        "phpunit/phpunit": "^10.5"
    }
}
```

**Justificación**:
- ✅ Sin vendor lock-in
- ✅ Control total sobre implementación
- ✅ Sin costos de licencia
- ✅ Comunidad activa en todas las librerías
- ✅ Fácil migración a SetaPDF-Signer en futuro si necesario

---

### Decisión 2: Nivel PAdES

**DECISIÓN**: PAdES-B-LT (Long-Term Validation)

**Roadmap por fases**:

```
MVP (Sprint 4):     PAdES-B-B  (desarrollo, demo)
Production Launch:  PAdES-B-LT (TSA Qualified integrado)
Long-term (Sprint 6+): PAdES-B-LTA (re-sellado automático)
```

**Configuración por entorno**:

```php
// config/signature.php
return [
    'pades_level' => env('SIGNATURE_PADES_LEVEL', 'B-LT'),
    
    'levels' => [
        'B-B' => [
            'requires_tsa' => false,
            'requires_validation_data' => false,
            'long_term' => false,
        ],
        'B-LT' => [
            'requires_tsa' => true,
            'requires_validation_data' => true,
            'long_term' => true,
        ],
        'B-LTA' => [
            'requires_tsa' => true,
            'requires_validation_data' => true,
            'requires_document_timestamp' => true,
            'long_term' => true,
        ],
    ],
];
```

**Justificación**:
- ✅ Cumple eIDAS completamente
- ✅ "Fecha cierta" legal (inversión carga prueba)
- ✅ Validable en Adobe Reader
- ✅ Integración con TsaService existente (ADR-008)
- ⚠️ Costo controlado: ~$0.15 por firma (TSA Qualified)

---

### Decisión 3: Certificados X.509

**DECISIÓN**: Estrategia Dual según Entorno

#### Development & Staging: Self-Signed Certificate

```bash
# bin/generate-dev-cert.sh
#!/bin/bash

openssl req -x509 -nodes -days 3650 -newkey rsa:4096 \
  -keyout storage/certificates/ancla-dev.key \
  -out storage/certificates/ancla-dev.crt \
  -subj "/C=ES/ST=Madrid/L=Madrid/O=Firmalum Dev/CN=firmalum.local" \
  -addext "keyUsage=digitalSignature" \
  -addext "extendedKeyUsage=emailProtection"

# Generar PKCS#12 (opcional)
openssl pkcs12 -export \
  -out storage/certificates/ancla-dev.p12 \
  -inkey storage/certificates/ancla-dev.key \
  -in storage/certificates/ancla-dev.crt \
  -password pass:ancla-dev-2025
```

**Características**:
- 🔐 RSA 4096 bits
- ⏰ Validez: 10 años
- 🎯 Key Usage: digitalSignature
- 💰 Costo: $0

---

#### Production: CA-Issued Certificate

**Proveedor recomendado**: DigiCert o GlobalSign

**Tipo**: Organization Validation (OV) Certificate

**Especificaciones**:
```
Subject: CN=Firmalum Signature Service, O=Firmalum Technologies, C=ES
Key Type: RSA 4096 bits
Validity: 2 años (renovación manual)
Extended Key Usage: Email Protection, Code Signing
Key Usage: Digital Signature, Non-Repudiation
```

**Costo estimado**: €200-400/año

**Proceso obtención**:
1. Generar CSR (Certificate Signing Request)
2. Validación de organización (2-5 días)
3. Instalar certificado en servidor
4. Configurar auto-renewal alerts

**Almacenamiento seguro**:
```php
// .env
SIGNATURE_CERT_PATH=/run/secrets/firmalum-prod.crt
SIGNATURE_KEY_PATH=/run/secrets/firmalum-prod.key
SIGNATURE_KEY_PASSWORD=<strong-password>
SIGNATURE_PKCS12_PATH=/run/secrets/firmalum-prod.p12
```

---

### Decisión 4: Estructura PKCS#7 (CMS)

**DECISIÓN**: PKCS#7 SignedData con Detached Signature

```
PKCS#7 Structure:
┌─────────────────────────────────────────────────────┐
│ SignedData                                          │
├─────────────────────────────────────────────────────┤
│ Version: 1                                          │
│ DigestAlgorithms: SHA-256                           │
│ ContentInfo:                                        │
│   ContentType: id-data (1.2.840.113549.1.7.1)     │
│   Content: NULL (detached)                         │
│ Certificates:                                       │
│   [0] Signer Certificate (X.509)                   │
│   [1] CA Certificate (optional)                    │
│ SignerInfos:                                        │
│   SignerInfo:                                       │
│     Version: 1                                      │
│     IssuerAndSerialNumber                          │
│     DigestAlgorithm: SHA-256                       │
│     AuthenticatedAttributes:                       │
│       - contentType                                │
│       - signingTime (UTC)                          │
│       - messageDigest (SHA-256 hash del PDF)       │
│       - signingCertificateV2 (hash del cert)       │
│     SignatureAlgorithm: sha256WithRSAEncryption    │
│     Signature: <encrypted digest>                  │
│     UnauthenticatedAttributes:                     │
│       - TSA Token (RFC 3161) ← PAdES-B-LT          │
│       - Firmalum Metadata (custom OID)                │
└─────────────────────────────────────────────────────┘
```

**Formato**: ASN.1 DER encoded

**Embedding en PDF**:
```
PDF Structure:
/ByteRange [0 1234 5678 9999]  ← Rangos no firmados
/Contents <PKCS#7 hex-encoded>
/Type /Sig
/Filter /Adobe.PPKLite
/SubFilter /ETSI.CAdES.detached  ← PAdES compliance
/M (D:20250129123045+01'00')
/Reason (Firmado electrónicamente)
/Location (Firmalum Platform)
/ContactInfo (soporte@firmalum.com)
```

---

### Decisión 5: Posición y Aspecto de Firma

**DECISIÓN**: Firma Visible con Metadata Completa

#### Configuración por defecto (MVP)

```php
// config/signature.php
'appearance' => [
    'mode' => 'visible',  // 'visible' | 'invisible'
    
    'position' => [
        'page' => 'last',  // 'first' | 'last' | int
        'x' => 50,         // mm desde izquierda
        'y' => 50,         // mm desde arriba
        'width' => 80,     // mm
        'height' => 40,    // mm
    ],
    
    'layout' => [
        'show_signature_image' => true,
        'show_signer_name' => true,
        'show_timestamp' => true,
        'show_reason' => true,
        'show_logo' => true,
        'show_qr_code' => true,
    ],
    
    'style' => [
        'border_color' => '#1a73e8',
        'border_width' => 1,
        'background_color' => '#f8f9fa',
        'font_family' => 'Helvetica',
        'font_size' => 9,
        'logo_path' => 'signatures/logo-firmalum.png',
    ],
],
```

#### Layout de firma visible

```
┌────────────────────────────────────────────────────┐
│  [LOGO]              FIRMADO ELECTRÓNICAMENTE      │
│                                                    │
│  [SIGNATURE IMAGE]   Juan Pérez García            │
│                      juan.perez@example.com        │
│                                                    │
│                      📅 29/12/2025 10:30:15 UTC   │
│                      🔒 Certificado: Firmalum         │
│                      ⏱ TSA: FirmaProfesional      │
│                                                    │
│  [QR CODE]           Verificar: ABCD-EFGH-IJKL    │
│                      https://firmalum.com/v/xxx        │
└────────────────────────────────────────────────────┘
```

#### Sprint 5: Editor de posiciones (E2-002)

En Sprint 5 se implementará editor visual para posicionar firmas libremente.

---

### Decisión 6: Integración con TSA

**DECISIÓN**: Integración Nativa con TsaService Existente

```php
// Flujo de firma con TSA
namespace App\Services\Signature;

use App\Services\Evidence\TsaService;

class PdfSignatureService
{
    public function __construct(
        private readonly TsaService $tsaService
    ) {}
    
    public function sign(Document $document, Signature $signature): SignedDocument
    {
        // 1. Calcular hash del PDF
        $pdfHash = hash_file('sha256', $document->path);
        
        // 2. Crear PKCS#7 signature
        $pkcs7 = $this->createPkcs7Signature($pdfHash, $signature);
        
        // 3. Obtener TSA timestamp (QUALIFIED para PAdES-B-LT)
        $tsaLevel = config('signature.pades_level') === 'B-LT' 
            ? TsaLevel::QUALIFIED 
            : TsaLevel::STANDARD;
            
        $tsaToken = $this->tsaService->requestTimestamp(
            hash: $pdfHash,
            level: $tsaLevel
        );
        
        // 4. Embeber TSA token en PKCS#7 UnauthenticatedAttributes
        $pkcs7WithTsa = $this->embedTsaToken($pkcs7, $tsaToken);
        
        // 5. Insertar PKCS#7 en PDF
        $signedPdf = $this->embedSignatureInPdf($document, $pkcs7WithTsa);
        
        return $signedPdf;
    }
}
```

**Integración con ADR-008 (TSA Strategy)**:

| Evento | TSA Level | Proveedor | Costo |
|--------|-----------|-----------|-------|
| Firma PDF (E3-004) | **QUALIFIED** | DigiCert/ANF | ~$0.15 |
| Audit Trail eventos | STANDARD | Self-hosted | $0.00 |
| Re-sellado archivo | QUALIFIED | DigiCert/ANF | ~$0.15/año |

---

### Decisión 7: Metadata de Evidencias

**DECISIÓN**: Embedded Metadata + External Evidence Package

#### A) Metadata Embebida en PDF (Signature Dictionary)

```php
PDF /Sig Dictionary Extensions:
/Firmalum_Version        (1.0)
/Firmalum_Evidence_ID    (uuid-of-evidence-package)
/Firmalum_Process_ID     (uuid-of-signing-process)
/Firmalum_Signer_ID      (uuid-of-signer)
/Firmalum_Verify_Code    (ABC1-DEF2-GH34)
/Firmalum_Verify_URL     (https://firmalum.com/verify/ABC1-DEF2-GH34)
/Firmalum_Verify_QR      (base64-encoded-qr-image)
/Firmalum_IP_Hash        (sha256-hashed-ip)
/Firmalum_Location       (Madrid, Spain)
/Firmalum_Device_FP      (sha256-device-fingerprint)
/Firmalum_Consent_ID     (uuid-of-consent-record)
/Firmalum_Audit_Chain    (sha256-hash-of-audit-trail)
```

**Ventajas**:
- ✅ Metadata viaja con el PDF
- ✅ Verificable offline (parcialmente)
- ✅ Auditoría independiente

**Limitaciones**:
- ⚠️ Metadata básica (no evidencias completas)
- ⚠️ Solo hashes por privacidad (RGPD)

---

#### B) Evidence Package Externo (Base de Datos)

```php
EvidencePackage {
    uuid: "evidence-xxx"
    signing_process_id: FK
    signer_id: FK
    document_id: FK
    
    // Evidencias capturadas
    device_fingerprint_id: FK → DeviceFingerprint
    geolocation_id: FK → GeolocationRecord
    ip_resolution_id: FK → IpResolutionRecord
    consent_record_id: FK → ConsentRecord
    signature_image_id: FK → Signature
    
    // Integridad
    audit_trail_hash: SHA-256 de toda la cadena
    tsa_token_id: FK → TsaToken (QUALIFIED)
    
    // PDF firmado
    signed_pdf_hash: SHA-256 del PDF final
    signed_pdf_path: storage path
    
    // Verificación pública
    verification_code_id: FK → VerificationCode
    qr_code_path: storage path
}
```

**Ventajas**:
- ✅ Evidencias completas disponibles
- ✅ Verificación pública online
- ✅ Cumplimiento RGPD (datos separados)

---

## Arquitectura de Firma

### Diagrama de Secuencia Completo

```
Usuario        SignerPage    PdfSignature   TsaService   EvidenceDossier   Database
Firmante       (Livewire)      Service                        Service
   │                │              │              │               │              │
   │  Dibuja firma  │              │              │               │              │
   ├───────────────>│              │              │               │              │
   │                │              │              │               │              │
   │                │  saveSignature()            │               │              │
   │                ├─────────────────────────────┼──────────────>│              │
   │                │              │              │               │    INSERT    │
   │                │              │              │               │  signatures  │
   │                │              │              │               ├─────────────>│
   │                │              │              │               │              │
   │  Click "Firmar"│              │              │               │              │
   ├───────────────>│              │              │               │              │
   │                │              │              │               │              │
   │                │  1. Capturar evidencias                     │              │
   │                ├─────────────────────────────────────────────>│              │
   │                │              │              │               │              │
   │                │              │              │  captureDevice()             │
   │                │              │              │               ├──────────────┤
   │                │              │              │  captureGeo()│              │
   │                │              │              │               ├──────────────┤
   │                │              │              │  captureIP() │              │
   │                │              │              │               ├──────────────┤
   │                │              │              │  captureConsent()            │
   │                │              │              │               ├──────────────┤
   │                │              │              │               │              │
   │                │  2. Firmar PDF              │               │              │
   │                ├─────────────>│              │               │              │
   │                │              │              │               │              │
   │                │              │ 2.1 Hash PDF│               │              │
   │                │              ├──────────────┤               │              │
   │                │              │              │               │              │
   │                │              │ 2.2 Create PKCS#7            │              │
   │                │              ├──────────────┤               │              │
   │                │              │              │               │              │
   │                │              │ 2.3 Request TSA (QUALIFIED)  │              │
   │                │              ├─────────────>│               │              │
   │                │              │              │               │              │
   │                │              │   RFC 3161   │               │              │
   │                │              │   Request    │               │              │
   │                │              │<─────────────┤               │              │
   │                │              │  TSA Token   │               │              │
   │                │              │              │               │              │
   │                │              │ 2.4 Embed TSA in PKCS#7      │              │
   │                │              ├──────────────┤               │              │
   │                │              │              │               │              │
   │                │              │ 2.5 Insert PKCS#7 in PDF     │              │
   │                │              ├──────────────┤               │              │
   │                │              │              │               │              │
   │                │              │ 2.6 Add Signature Appearance │              │
   │                │              ├──────────────┤               │              │
   │                │              │              │               │              │
   │                │   SignedPDF  │              │               │              │
   │                │<─────────────┤              │               │              │
   │                │              │              │               │              │
   │                │  3. Create Evidence Package │               │              │
   │                ├─────────────────────────────────────────────>│              │
   │                │              │              │               │              │
   │                │              │              │               │  createPackage()
   │                │              │              │               ├─────────────>│
   │                │              │              │               │    INSERT    │
   │                │              │              │               │  evidence_   │
   │                │              │              │               │  packages    │
   │                │              │              │               │              │
   │                │  4. Generate Verification Code              │              │
   │                ├─────────────────────────────────────────────>│              │
   │                │              │              │               │              │
   │                │              │              │               │  generateCode()
   │                │              │              │               ├─────────────>│
   │                │              │              │               │    INSERT    │
   │                │              │              │               │verification_ │
   │                │              │              │               │    codes     │
   │                │              │              │               │              │
   │                │  5. Update Signer Status    │               │              │
   │                ├─────────────────────────────┼───────────────┼─────────────>│
   │                │              │              │               │    UPDATE    │
   │                │              │              │               │   signers    │
   │                │              │              │               │status='signed'
   │                │              │              │               │              │
   │                │  6. Audit Log               │               │              │
   │                ├─────────────────────────────────────────────┼─────────────>│
   │                │              │              │               │    INSERT    │
   │                │              │              │               │ audit_trail_ │
   │                │              │              │               │   entries    │
   │                │              │              │               │              │
   │  ✅ Firmado    │              │              │               │              │
   │<───────────────┤              │              │               │              │
   │  + Ver código  │              │              │               │              │
   │                │              │              │               │              │
```

---

### Diagrama de Componentes

```
┌───────────────────────────────────────────────────────────────────────────────┐
│                           CAPA DE PRESENTACIÓN                                │
├───────────────────────────────────────────────────────────────────────────────┤
│                                                                               │
│  ┌─────────────────────┐    ┌─────────────────────┐    ┌──────────────────┐  │
│  │ SignerPage          │    │ SignatureCapture    │    │ ProcessDetail    │  │
│  │ (Livewire)          │    │ (Livewire)          │    │ (Livewire)       │  │
│  │                     │    │                     │    │                  │  │
│  │ - Acceso por token  │    │ - Canvas drawing    │    │ - Ver estado     │  │
│  │ - OTP verification  │    │ - Typed signature   │    │ - Timeline       │  │
│  │ - PDF preview       │    │ - Upload image      │    │ - Firmantes      │  │
│  │ - Firmar button     │    │ - Save signature    │    │ - Descargar PDF  │  │
│  └─────────────────────┘    └─────────────────────┘    └──────────────────┘  │
│            │                          │                          │            │
└────────────┼──────────────────────────┼──────────────────────────┼────────────┘
             │                          │                          │
             ▼                          ▼                          ▼
┌───────────────────────────────────────────────────────────────────────────────┐
│                           CAPA DE SERVICIOS                                   │
├───────────────────────────────────────────────────────────────────────────────┤
│                                                                               │
│  ┌──────────────────────────────────────────────────────────────────────────┐ │
│  │                         PdfSignatureService                              │ │
│  │                                                                          │ │
│  │  + sign(Document, Signature, array $metadata): SignedDocument           │ │
│  │  + createPkcs7Signature(string $hash, Signature): string                │ │
│  │  + embedTsaToken(string $pkcs7, TsaToken): string                       │ │
│  │  + embedSignatureInPdf(Document, string $pkcs7): SignedDocument         │ │
│  │  + addSignatureAppearance(PDF, Signature, array $position): void        │ │
│  │  + embedMetadata(PDF, array $metadata): void                            │ │
│  │  + validateSignature(SignedDocument): ValidationResult                  │ │
│  │                                                                          │ │
│  └────────────┬──────────────────────┬──────────────────────┬───────────────┘ │
│               │                      │                      │                 │
│               ▼                      ▼                      ▼                 │
│  ┌─────────────────────┐  ┌─────────────────────┐  ┌─────────────────────┐  │
│  │ CertificateService  │  │ Pkcs7Builder        │  │ PdfEmbedder         │  │
│  │                     │  │                     │  │                     │  │
│  │ - loadCertificate() │  │ - createSignedData()│  │ - importPdf()       │  │
│  │ - getPrivateKey()   │  │ - addCertificate()  │  │ - addSignatureField()│ │
│  │ - validateCert()    │  │ - addSignerInfo()   │  │ - addAppearance()   │  │
│  │                     │  │ - sign()            │  │ - embedPkcs7()      │  │
│  └─────────────────────┘  └─────────────────────┘  └─────────────────────┘  │
│               │                      │                      │                 │
│               ▼                      ▼                      ▼                 │
│  ┌──────────────────────────────────────────────────────────────────────────┐ │
│  │                         TsaService (EXISTING)                            │ │
│  │                                                                          │ │
│  │  + requestTimestamp(string $hash, TsaLevel): TsaToken                   │ │
│  │  + verifyTimestamp(TsaToken): bool                                      │ │
│  │                                                                          │ │
│  └────────────────────────────────────────────────────────────────┬─────────┘ │
│                                                                   │           │
│  ┌──────────────────────────────────────────────────────────────────────────┐ │
│  │                    EvidenceDossierService (EXISTING)                     │ │
│  │                                                                          │ │
│  │  + captureAllEvidences(Signer): array                                   │ │
│  │  + createPackage(SigningProcess, array $evidences): EvidencePackage     │ │
│  │  + generateVerificationCode(EvidencePackage): VerificationCode          │ │
│  │                                                                          │ │
│  └──────────────────────────────────────────────────────────────────────────┘ │
│                                                                               │
└───────────────────────────────────────────────────────────────────────────────┘
                                     │
                                     ▼
┌───────────────────────────────────────────────────────────────────────────────┐
│                           CAPA DE PERSISTENCIA                                │
├───────────────────────────────────────────────────────────────────────────────┤
│                                                                               │
│  signing_processes  ←─┐                                                       │
│  signers            ←─┼─┐                                                     │
│  signatures         ←─┼─┼─┐                                                   │
│  signed_documents   ←─┼─┼─┼─┐                                                 │
│  evidence_packages  ←─┴─┼─┼─┼─┐                                               │
│  tsa_tokens             └─┼─┼─┼─┐                                             │
│  verification_codes       └─┼─┼─┤                                             │
│  audit_trail_entries        └─┼─┤                                             │
│  device_fingerprints          └─┤                                             │
│  geolocation_records            │                                             │
│  ip_resolution_records          │                                             │
│  consent_records                │                                             │
│                                 │                                             │
└─────────────────────────────────┼─────────────────────────────────────────────┘
                                  │
                                  ▼
                            PostgreSQL 16
```

---

## Modelo de Datos

### Nueva Tabla: `signed_documents`

```sql
CREATE TABLE signed_documents (
    id                      BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    uuid                    CHAR(36) NOT NULL UNIQUE,
    tenant_id               BIGINT UNSIGNED NOT NULL,
    
    -- Referencia al proceso y firmante
    signing_process_id      BIGINT UNSIGNED NOT NULL,
    signer_id               BIGINT UNSIGNED NOT NULL,
    original_document_id    BIGINT UNSIGNED NOT NULL,
    
    -- Archivo firmado
    storage_disk            VARCHAR(50) NOT NULL DEFAULT 'local',
    signed_path             VARCHAR(500) NOT NULL,
    signed_name             VARCHAR(255) NOT NULL,
    file_size               BIGINT UNSIGNED NOT NULL,
    
    -- Integridad
    content_hash            CHAR(64) NOT NULL,              -- SHA-256 del PDF firmado
    original_hash           CHAR(64) NOT NULL,              -- SHA-256 del PDF original
    hash_algorithm          VARCHAR(20) DEFAULT 'SHA-256',
    
    -- Firma digital
    signature_id            BIGINT UNSIGNED NOT NULL,       -- FK a signatures
    pkcs7_signature         TEXT NOT NULL,                  -- PKCS#7 hex-encoded
    certificate_subject     VARCHAR(500) NOT NULL,          -- DN del certificado
    certificate_issuer      VARCHAR(500) NOT NULL,
    certificate_serial      VARCHAR(100) NOT NULL,
    certificate_fingerprint CHAR(64) NOT NULL,              -- SHA-256 del cert
    
    -- PAdES metadata
    pades_level             VARCHAR(20) NOT NULL,           -- 'B-B', 'B-LT', 'B-LTA'
    has_tsa_token           BOOLEAN DEFAULT FALSE,
    tsa_token_id            BIGINT UNSIGNED NULL,           -- FK a tsa_tokens (QUALIFIED)
    has_validation_data     BOOLEAN DEFAULT FALSE,
    
    -- Signature appearance
    signature_position      JSON NOT NULL,                  -- {page, x, y, width, height}
    signature_visible       BOOLEAN DEFAULT TRUE,
    signature_appearance    JSON NULL,                      -- Layout config
    
    -- Embedded metadata
    embedded_metadata       JSON NOT NULL,                  -- Firmalum custom fields
    verification_code_id    BIGINT UNSIGNED NULL,           -- FK a verification_codes
    qr_code_embedded        BOOLEAN DEFAULT TRUE,
    
    -- Evidence package
    evidence_package_id     BIGINT UNSIGNED NOT NULL,       -- FK a evidence_packages
    
    -- Validación
    adobe_validated         BOOLEAN NULL,                   -- NULL = no validado aún
    adobe_validation_date   TIMESTAMP NULL,
    validation_errors       JSON NULL,
    
    -- Estado
    status                  ENUM('signing', 'signed', 'error') DEFAULT 'signing',
    error_message           TEXT NULL,
    
    -- Timestamps
    signed_at               TIMESTAMP NOT NULL,
    created_at              TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at              TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_signed_tenant (tenant_id),
    INDEX idx_signed_process (signing_process_id),
    INDEX idx_signed_signer (signer_id),
    INDEX idx_signed_hash (content_hash),
    INDEX idx_signed_status (status),
    INDEX idx_signed_date (signed_at),
    
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (signing_process_id) REFERENCES signing_processes(id) ON DELETE CASCADE,
    FOREIGN KEY (signer_id) REFERENCES signers(id) ON DELETE CASCADE,
    FOREIGN KEY (original_document_id) REFERENCES documents(id),
    FOREIGN KEY (signature_id) REFERENCES signatures(id),
    FOREIGN KEY (tsa_token_id) REFERENCES tsa_tokens(id),
    FOREIGN KEY (verification_code_id) REFERENCES verification_codes(id),
    FOREIGN KEY (evidence_package_id) REFERENCES evidence_packages(id)
);
```

---

### Tabla Existente: `signatures` (Sin cambios)

Ya definida en E3-003. Almacena las imágenes de firma (drawn/typed/uploaded).

---

### Actualización Tabla: `documents`

```sql
ALTER TABLE documents ADD COLUMN signed_version_id BIGINT UNSIGNED NULL;
ALTER TABLE documents ADD FOREIGN KEY (signed_version_id) REFERENCES signed_documents(id);
```

---

## Servicios a Implementar

### 1. PdfSignatureService (Principal)

```php
<?php
// app/Services/Signature/PdfSignatureService.php

namespace App\Services\Signature;

use App\Models\{Document, Signature, Signer, SignedDocument};
use App\Services\Evidence\{TsaService, EvidenceDossierService};
use Illuminate\Support\Facades\{Storage, Log};
use Illuminate\Support\Str;

class PdfSignatureService
{
    public function __construct(
        private readonly CertificateService $certificateService,
        private readonly Pkcs7Builder $pkcs7Builder,
        private readonly PdfEmbedder $pdfEmbedder,
        private readonly TsaService $tsaService,
        private readonly EvidenceDossierService $evidenceService
    ) {}

    /**
     * Sign a PDF document with PAdES signature.
     */
    public function signDocument(
        Document $document,
        Signature $signature,
        Signer $signer,
        array $metadata = []
    ): SignedDocument {
        
        Log::info('Starting PDF signature process', [
            'document_id' => $document->id,
            'signer_id' => $signer->id,
            'pades_level' => config('signature.pades_level'),
        ]);

        try {
            // 1. Obtener contenido del PDF original
            $pdfContent = $this->getOriginalPdfContent($document);
            $originalHash = hash('sha256', $pdfContent);

            // 2. Cargar certificado de plataforma
            $certificate = $this->certificateService->loadCertificate();
            $privateKey = $this->certificateService->getPrivateKey();

            // 3. Crear PKCS#7 SignedData
            $pkcs7 = $this->pkcs7Builder
                ->setCertificate($certificate)
                ->setPrivateKey($privateKey)
                ->setContentHash($originalHash)
                ->setSigningTime(now())
                ->setReason('Firmado electrónicamente')
                ->setLocation('Firmalum Platform')
                ->build();

            // 4. Obtener TSA timestamp si PAdES-B-LT
            $tsaToken = null;
            if ($this->requiresTsaToken()) {
                $tsaToken = $this->tsaService->requestTimestamp(
                    hash: $originalHash,
                    level: TsaLevel::QUALIFIED
                );

                $pkcs7 = $this->pkcs7Builder->embedTsaToken($pkcs7, $tsaToken);
            }

            // 5. Preparar appearance de firma
            $appearance = $this->prepareSignatureAppearance($signature, $signer, $metadata);

            // 6. Embeber firma en PDF
            $signedPdfContent = $this->pdfEmbedder
                ->importPdf($pdfContent)
                ->addSignatureField(config('signature.appearance.position'))
                ->addSignatureAppearance($appearance)
                ->embedPkcs7($pkcs7)
                ->embedMetadata($this->prepareEmbeddedMetadata($signer, $metadata))
                ->generate();

            // 7. Guardar PDF firmado
            $signedPath = $this->storeSignedPdf($signedPdfContent, $signer);
            $signedHash = hash('sha256', $signedPdfContent);

            // 8. Crear registro en BD
            $signedDocument = SignedDocument::create([
                'uuid' => Str::uuid(),
                'tenant_id' => $document->tenant_id,
                'signing_process_id' => $signer->signing_process_id,
                'signer_id' => $signer->id,
                'original_document_id' => $document->id,
                'storage_disk' => 'local',
                'signed_path' => $signedPath,
                'signed_name' => $this->generateSignedFilename($document, $signer),
                'file_size' => strlen($signedPdfContent),
                'content_hash' => $signedHash,
                'original_hash' => $originalHash,
                'signature_id' => $signature->id,
                'pkcs7_signature' => bin2hex($pkcs7),
                'certificate_subject' => $certificate->getSubject(),
                'certificate_issuer' => $certificate->getIssuer(),
                'certificate_serial' => $certificate->getSerialNumber(),
                'certificate_fingerprint' => $certificate->getFingerprint('sha256'),
                'pades_level' => config('signature.pades_level'),
                'has_tsa_token' => $tsaToken !== null,
                'tsa_token_id' => $tsaToken?->id,
                'signature_position' => config('signature.appearance.position'),
                'signature_visible' => config('signature.appearance.mode') === 'visible',
                'embedded_metadata' => $metadata,
                'status' => 'signed',
                'signed_at' => now(),
            ]);

            Log::info('PDF signature completed successfully', [
                'signed_document_id' => $signedDocument->id,
                'signed_hash' => $signedHash,
            ]);

            return $signedDocument;

        } catch (\Exception $e) {
            Log::error('PDF signature failed', [
                'document_id' => $document->id,
                'signer_id' => $signer->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new PdfSignatureException(
                'Failed to sign PDF: ' . $e->getMessage(),
                previous: $e
            );
        }
    }

    /**
     * Validate a signed PDF.
     */
    public function validateSignature(SignedDocument $signedDocument): SignatureValidationResult
    {
        // 1. Verificar hash del PDF
        $currentHash = hash_file('sha256', 
            Storage::disk($signedDocument->storage_disk)->path($signedDocument->signed_path)
        );
        $hashValid = hash_equals($signedDocument->content_hash, $currentHash);

        // 2. Verificar PKCS#7 signature
        $pkcs7Valid = $this->pkcs7Builder->verify(
            hex2bin($signedDocument->pkcs7_signature),
            $this->certificateService->loadCertificate()
        );

        // 3. Verificar TSA token
        $tsaValid = true;
        if ($signedDocument->has_tsa_token && $signedDocument->tsaToken) {
            $tsaValid = $this->tsaService->verifyTimestamp($signedDocument->tsaToken);
        }

        // 4. Verificar certificado no revocado (OCSP/CRL check)
        $certValid = $this->certificateService->checkRevocation(
            $signedDocument->certificate_serial
        );

        $isValid = $hashValid && $pkcs7Valid && $tsaValid && $certValid;

        return new SignatureValidationResult(
            isValid: $isValid,
            hashValid: $hashValid,
            pkcs7Valid: $pkcs7Valid,
            tsaValid: $tsaValid,
            certificateValid: $certValid,
            validatedAt: now(),
        );
    }

    private function requiresTsaToken(): bool
    {
        return in_array(config('signature.pades_level'), ['B-LT', 'B-LTA']);
    }

    private function getOriginalPdfContent(Document $document): string
    {
        // Obtener contenido desencriptado si está cifrado
        if ($document->is_encrypted) {
            $encrypted = Storage::disk($document->storage_disk)->get($document->stored_path);
            return decrypt($encrypted);
        }

        return Storage::disk($document->storage_disk)->get($document->stored_path);
    }

    private function storeSignedPdf(string $content, Signer $signer): string
    {
        $path = sprintf(
            'signed/%s/%s/%s_%s.pdf',
            $signer->tenant_id,
            now()->format('Y/m'),
            $signer->signing_process_id,
            $signer->id
        );

        Storage::disk('local')->put($path, $content);

        return $path;
    }

    private function generateSignedFilename(Document $document, Signer $signer): string
    {
        $basename = pathinfo($document->original_name, PATHINFO_FILENAME);
        return sprintf('%s_signed_%s.pdf', $basename, $signer->id);
    }

    private function prepareSignatureAppearance(
        Signature $signature,
        Signer $signer,
        array $metadata
    ): array {
        return [
            'signature_image_path' => $signature->image_path,
            'signer_name' => $signer->name,
            'signer_email' => $signer->email,
            'signing_time' => now()->toIso8601String(),
            'verification_code' => $metadata['verification_code'] ?? null,
            'verification_url' => $metadata['verification_url'] ?? null,
            'qr_code_path' => $metadata['qr_code_path'] ?? null,
            'logo_path' => config('signature.appearance.style.logo_path'),
            'layout' => config('signature.appearance.layout'),
            'style' => config('signature.appearance.style'),
        ];
    }

    private function prepareEmbeddedMetadata(Signer $signer, array $metadata): array
    {
        return [
            'Firmalum_Version' => '1.0',
            'Firmalum_Evidence_ID' => $metadata['evidence_package_uuid'] ?? null,
            'Firmalum_Process_ID' => $signer->signing_process_id,
            'Firmalum_Signer_ID' => $signer->id,
            'Firmalum_Verify_Code' => $metadata['verification_code'] ?? null,
            'Firmalum_Verify_URL' => $metadata['verification_url'] ?? null,
            'Firmalum_IP_Hash' => $metadata['ip_hash'] ?? null,
            'Firmalum_Location' => $metadata['location'] ?? null,
            'Firmalum_Device_FP' => $metadata['device_fingerprint_hash'] ?? null,
            'Firmalum_Consent_ID' => $metadata['consent_id'] ?? null,
            'Firmalum_Audit_Chain' => $metadata['audit_chain_hash'] ?? null,
        ];
    }
}
```

---

### 2. CertificateService

```php
<?php
// app/Services/Signature/CertificateService.php

namespace App\Services\Signature;

use RuntimeException;

class CertificateService
{
    private array $config;

    public function __construct()
    {
        $this->config = config('signature.certificate');
    }

    /**
     * Load the platform certificate.
     */
    public function loadCertificate(): X509Certificate
    {
        $certPath = $this->getCertificatePath();
        
        if (!file_exists($certPath)) {
            throw new RuntimeException("Certificate not found: {$certPath}");
        }

        $certContent = file_get_contents($certPath);
        $cert = openssl_x509_read($certContent);

        if (!$cert) {
            throw new RuntimeException('Failed to read certificate: ' . openssl_error_string());
        }

        return new X509Certificate($cert);
    }

    /**
     * Load the private key.
     */
    public function getPrivateKey(): PrivateKey
    {
        $keyPath = $this->getPrivateKeyPath();
        $password = $this->getPrivateKeyPassword();

        if (!file_exists($keyPath)) {
            throw new RuntimeException("Private key not found: {$keyPath}");
        }

        $keyContent = file_get_contents($keyPath);
        $key = openssl_pkey_get_private($keyContent, $password);

        if (!$key) {
            throw new RuntimeException('Failed to read private key: ' . openssl_error_string());
        }

        return new PrivateKey($key);
    }

    /**
     * Check if certificate is revoked (OCSP/CRL).
     */
    public function checkRevocation(string $serialNumber): bool
    {
        // Implementación simplificada para MVP
        // En producción: consultar OCSP responder o CRL

        return true; // Assume valid for self-signed
    }

    /**
     * Validate certificate chain.
     */
    public function validateChain(X509Certificate $cert): bool
    {
        // Verificar CA chain si existe
        if ($this->config['ca_bundle_path']) {
            // Implementar validación de cadena
        }

        return true;
    }

    private function getCertificatePath(): string
    {
        return base_path($this->config['cert_path']);
    }

    private function getPrivateKeyPath(): string
    {
        return base_path($this->config['key_path']);
    }

    private function getPrivateKeyPassword(): ?string
    {
        return $this->config['key_password'];
    }
}
```

---

### 3. Pkcs7Builder

```php
<?php
// app/Services/Signature/Pkcs7Builder.php

namespace App\Services\Signature;

use phpseclib3\File\ASN1;
use phpseclib3\File\X509;

class Pkcs7Builder
{
    private ?X509Certificate $certificate = null;
    private ?PrivateKey $privateKey = null;
    private ?string $contentHash = null;
    private ?\DateTimeInterface $signingTime = null;
    private ?string $reason = null;
    private ?string $location = null;

    public function setCertificate(X509Certificate $cert): self
    {
        $this->certificate = $cert;
        return $this;
    }

    public function setPrivateKey(PrivateKey $key): self
    {
        $this->privateKey = $key;
        return $this;
    }

    public function setContentHash(string $hash): self
    {
        $this->contentHash = $hash;
        return $this;
    }

    public function setSigningTime(\DateTimeInterface $time): self
    {
        $this->signingTime = $time;
        return $this;
    }

    public function setReason(string $reason): self
    {
        $this->reason = $reason;
        return $this;
    }

    public function setLocation(string $location): self
    {
        $this->location = $location;
        return $this;
    }

    /**
     * Build PKCS#7 SignedData structure.
     */
    public function build(): string
    {
        if (!$this->certificate || !$this->privateKey || !$this->contentHash) {
            throw new \RuntimeException('Certificate, private key, and content hash are required');
        }

        // Crear estructura SignedData ASN.1
        $signedData = [
            'version' => 1,
            'digestAlgorithms' => [
                ['algorithm' => '2.16.840.1.101.3.4.2.1'] // SHA-256
            ],
            'contentInfo' => [
                'contentType' => '1.2.840.113549.1.7.1', // id-data
                'content' => null // detached signature
            ],
            'certificates' => [
                $this->certificate->getDer()
            ],
            'signerInfos' => [
                $this->buildSignerInfo()
            ]
        ];

        // Encode to DER
        $asn1 = new ASN1();
        $pkcs7Der = $asn1->encodeDER($signedData, $this->getSignedDataMap());

        return $pkcs7Der;
    }

    /**
     * Build SignerInfo structure.
     */
    private function buildSignerInfo(): array
    {
        // Authenticated Attributes
        $authenticatedAttrs = [
            // contentType
            [
                'type' => '1.2.840.113549.1.9.3',
                'values' => ['1.2.840.113549.1.7.1']
            ],
            // signingTime
            [
                'type' => '1.2.840.113549.1.9.5',
                'values' => [$this->signingTime->format('YmdHis') . 'Z']
            ],
            // messageDigest
            [
                'type' => '1.2.840.113549.1.9.4',
                'values' => [hex2bin($this->contentHash)]
            ],
        ];

        // Sign authenticated attributes
        $attrsDer = $this->encodeAttributes($authenticatedAttrs);
        $signature = $this->sign($attrsDer);

        return [
            'version' => 1,
            'issuerAndSerialNumber' => [
                'issuer' => $this->certificate->getIssuerDN(),
                'serialNumber' => $this->certificate->getSerialNumber()
            ],
            'digestAlgorithm' => [
                'algorithm' => '2.16.840.1.101.3.4.2.1' // SHA-256
            ],
            'authenticatedAttributes' => $authenticatedAttrs,
            'digestEncryptionAlgorithm' => [
                'algorithm' => '1.2.840.113549.1.1.1' // RSA
            ],
            'encryptedDigest' => $signature,
        ];
    }

    /**
     * Sign data with private key.
     */
    private function sign(string $data): string
    {
        $signature = '';
        openssl_sign(
            $data,
            $signature,
            $this->privateKey->getResource(),
            OPENSSL_ALGO_SHA256
        );

        return $signature;
    }

    /**
     * Embed TSA token in UnauthenticatedAttributes.
     */
    public function embedTsaToken(string $pkcs7, TsaToken $tsaToken): string
    {
        // Parse PKCS#7
        $asn1 = new ASN1();
        $decoded = $asn1->decodeBER($pkcs7);

        // Add TSA token to UnauthenticatedAttributes
        $tsaAttr = [
            'type' => '1.2.840.113549.1.9.16.2.14', // id-aa-signatureTimeStampToken
            'values' => [base64_decode($tsaToken->token)]
        ];

        $decoded['signerInfos'][0]['unauthenticatedAttributes'][] = $tsaAttr;

        // Re-encode
        return $asn1->encodeDER($decoded, $this->getSignedDataMap());
    }

    /**
     * Verify PKCS#7 signature.
     */
    public function verify(string $pkcs7, X509Certificate $cert): bool
    {
        // Usar openssl_pkcs7_verify para validación
        $tempPkcs7 = tempnam(sys_get_temp_dir(), 'pkcs7_');
        $tempCert = tempnam(sys_get_temp_dir(), 'cert_');

        file_put_contents($tempPkcs7, $pkcs7);
        file_put_contents($tempCert, $cert->getPem());

        $result = openssl_pkcs7_verify(
            $tempPkcs7,
            PKCS7_DETACHED | PKCS7_NOVERIFY,
            $tempCert
        );

        unlink($tempPkcs7);
        unlink($tempCert);

        return $result === true;
    }

    private function getSignedDataMap(): array
    {
        // ASN.1 map for SignedData structure
        // Simplified - in production use complete PKCS#7 map
        return [
            'type' => ASN1::TYPE_SEQUENCE,
            'children' => [
                'version' => ['type' => ASN1::TYPE_INTEGER],
                'digestAlgorithms' => ['type' => ASN1::TYPE_SET],
                'contentInfo' => ['type' => ASN1::TYPE_SEQUENCE],
                'certificates' => ['type' => ASN1::TYPE_SET, 'optional' => true],
                'signerInfos' => ['type' => ASN1::TYPE_SET],
            ]
        ];
    }

    private function encodeAttributes(array $attrs): string
    {
        $asn1 = new ASN1();
        return $asn1->encodeDER($attrs);
    }
}
```

---

### 4. PdfEmbedder

```php
<?php
// app/Services/Signature/PdfEmbedder.php

namespace App\Services\Signature;

use setasign\Fpdi\Fpdi;

class PdfEmbedder
{
    private Fpdi $pdf;
    private string $originalContent;
    private array $signaturePosition;
    private array $appearance;
    private array $metadata = [];

    /**
     * Import existing PDF.
     */
    public function importPdf(string $content): self
    {
        $this->originalContent = $content;

        // Save to temp file for FPDI
        $tempPdf = tempnam(sys_get_temp_dir(), 'pdf_');
        file_put_contents($tempPdf, $content);

        $this->pdf = new Fpdi();
        $pageCount = $this->pdf->setSourceFile($tempPdf);

        // Import all pages
        for ($i = 1; $i <= $pageCount; $i++) {
            $this->pdf->AddPage();
            $tplId = $this->pdf->importPage($i);
            $this->pdf->useTemplate($tplId);
        }

        unlink($tempPdf);

        return $this;
    }

    /**
     * Add signature field to PDF.
     */
    public function addSignatureField(array $position): self
    {
        $this->signaturePosition = $position;

        // Determinar página
        $pageNumber = $this->resolvePageNumber($position['page']);
        $this->pdf->setPage($pageNumber);

        return $this;
    }

    /**
     * Add signature appearance (visible signature).
     */
    public function addSignatureAppearance(array $appearance): self
    {
        $this->appearance = $appearance;

        // Draw signature appearance on the page
        $this->drawSignatureBox();
        $this->drawSignatureImage();
        $this->drawSignerInfo();
        $this->drawTimestamp();
        $this->drawVerificationInfo();
        $this->drawQrCode();

        return $this;
    }

    /**
     * Embed PKCS#7 signature in PDF.
     */
    public function embedPkcs7(string $pkcs7): self
    {
        // Get PDF content
        $pdfContent = $this->pdf->Output('S');

        // Calculate byte ranges for signature
        // ByteRange format: [0 offset1 offset2 offset3]
        // Where signature placeholder is between offset1 and offset2

        $signaturePlaceholder = str_repeat('0', 8192); // Reserve space
        $signatureHex = bin2hex($pkcs7);

        if (strlen($signatureHex) > strlen($signaturePlaceholder)) {
            throw new \RuntimeException('PKCS#7 signature too large for placeholder');
        }

        // Pad signature to placeholder size
        $signatureHex = str_pad($signatureHex, strlen($signaturePlaceholder), '0');

        // Find insertion point in PDF
        $insertionPoint = $this->findSignatureInsertionPoint($pdfContent);

        // Build signature dictionary
        $sigDict = $this->buildSignatureDictionary($signatureHex);

        // Insert into PDF
        $newContent = substr($pdfContent, 0, $insertionPoint) 
            . $sigDict 
            . substr($pdfContent, $insertionPoint);

        // Update internal PDF content
        $this->originalContent = $newContent;

        return $this;
    }

    /**
     * Embed custom metadata in PDF.
     */
    public function embedMetadata(array $metadata): self
    {
        $this->metadata = $metadata;

        foreach ($metadata as $key => $value) {
            $this->pdf->SetKeywords($value);
            // Or use custom XMP metadata
        }

        return $this;
    }

    /**
     * Generate final signed PDF.
     */
    public function generate(): string
    {
        return $this->originalContent;
    }

    private function resolvePageNumber(string|int $page): int
    {
        if (is_int($page)) {
            return $page;
        }

        return match($page) {
            'first' => 1,
            'last' => $this->pdf->getNumPages(),
            default => 1,
        };
    }

    private function drawSignatureBox(): void
    {
        $pos = $this->signaturePosition;
        $style = $this->appearance['style'];

        // Draw border
        $this->pdf->SetDrawColor(...$this->hexToRgb($style['border_color']));
        $this->pdf->SetLineWidth($style['border_width']);
        $this->pdf->Rect($pos['x'], $pos['y'], $pos['width'], $pos['height']);

        // Background
        $this->pdf->SetFillColor(...$this->hexToRgb($style['background_color']));
        $this->pdf->Rect($pos['x'], $pos['y'], $pos['width'], $pos['height'], 'F');
    }

    private function drawSignatureImage(): void
    {
        $imagePath = storage_path('app/' . $this->appearance['signature_image_path']);
        
        if (file_exists($imagePath)) {
            $this->pdf->Image(
                $imagePath,
                $this->signaturePosition['x'] + 5,
                $this->signaturePosition['y'] + 5,
                20, // width
                15  // height
            );
        }
    }

    private function drawSignerInfo(): void
    {
        $this->pdf->SetFont('Helvetica', 'B', 10);
        $this->pdf->SetXY(
            $this->signaturePosition['x'] + 30,
            $this->signaturePosition['y'] + 5
        );
        $this->pdf->Write(0, $this->appearance['signer_name']);

        $this->pdf->SetFont('Helvetica', '', 8);
        $this->pdf->SetXY(
            $this->signaturePosition['x'] + 30,
            $this->signaturePosition['y'] + 10
        );
        $this->pdf->Write(0, $this->appearance['signer_email']);
    }

    private function drawTimestamp(): void
    {
        $this->pdf->SetFont('Helvetica', '', 8);
        $this->pdf->SetXY(
            $this->signaturePosition['x'] + 30,
            $this->signaturePosition['y'] + 15
        );
        $this->pdf->Write(0, 'Firmado: ' . $this->appearance['signing_time']);
    }

    private function drawVerificationInfo(): void
    {
        if ($this->appearance['verification_code']) {
            $this->pdf->SetFont('Helvetica', 'I', 7);
            $this->pdf->SetXY(
                $this->signaturePosition['x'] + 5,
                $this->signaturePosition['y'] + 25
            );
            $this->pdf->Write(0, 'Verificar: ' . $this->appearance['verification_code']);
        }
    }

    private function drawQrCode(): void
    {
        if ($this->appearance['qr_code_path']) {
            $qrPath = storage_path('app/' . $this->appearance['qr_code_path']);
            
            if (file_exists($qrPath)) {
                $this->pdf->Image(
                    $qrPath,
                    $this->signaturePosition['x'] + $this->signaturePosition['width'] - 20,
                    $this->signaturePosition['y'] + 5,
                    15, // width
                    15  // height
                );
            }
        }
    }

    private function buildSignatureDictionary(string $signatureHex): string
    {
        return sprintf(
            "<</Type/Sig/Filter/Adobe.PPKLite/SubFilter/ETSI.CAdES.detached" .
            "/M(D:%s)/Contents<%s>/ByteRange[0 0 0 0]>>",
            date('YmdHisO'),
            $signatureHex
        );
    }

    private function findSignatureInsertionPoint(string $content): int
    {
        // Find appropriate place in PDF structure to insert signature
        // Usually before the xref table
        $xrefPos = strrpos($content, 'xref');
        return $xrefPos ?: strlen($content);
    }

    private function hexToRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');
        return [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ];
    }
}
```

---

## Configuración

### config/signature.php

```php
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
            'logo_path' => 'signatures/logo-firmalum.png',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Settings
    |--------------------------------------------------------------------------
    */
    'security' => [
        'hash_algorithm' => 'sha256',
        'rsa_key_size' => 4096,
        'pkcs7_placeholder_size' => 8192, // bytes reserved for signature
        'max_pdf_size' => 52428800, // 50MB
    ],

    /*
    |--------------------------------------------------------------------------
    | Validation Settings
    |--------------------------------------------------------------------------
    */
    'validation' => [
        'check_revocation' => env('SIGNATURE_CHECK_REVOCATION', false),
        'ocsp_responder' => env('SIGNATURE_OCSP_RESPONDER', null),
        'crl_url' => env('SIGNATURE_CRL_URL', null),
        'adobe_validation' => env('SIGNATURE_ADOBE_VALIDATION', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Storage Settings
    |--------------------------------------------------------------------------
    */
    'storage' => [
        'disk' => env('SIGNATURE_STORAGE_DISK', 'local'),
        'path' => 'signed',
        'encrypt' => env('SIGNATURE_ENCRYPT_STORAGE', false),
    ],
];
```

---

## Decisiones de Seguridad

### 1. Protección de Certificado y Claves

**Decisión**: Almacenamiento Seguro con Secrets Management

```bash
# Development: File system
storage/certificates/ancla-dev.crt
storage/certificates/ancla-dev.key (permisos 600)

# Staging/Production: Docker Secrets
docker secret create firmalum-prod-cert firmalum-prod.crt
docker secret create firmalum-prod-key firmalum-prod.key

# O usar HashiCorp Vault
vault kv put secret/firmalum/signature \
  cert=@firmalum-prod.crt \
  key=@firmalum-prod.key \
  password="<strong-password>"
```

**Justificación**:
- ✅ Claves nunca en repositorio Git
- ✅ Acceso restringido por permisos OS
- ✅ Rotación de certificados simplificada
- ✅ Audit trail de accesos a secrets

---

### 2. Validación de PDFs antes de Firmar

**Decisión**: Validación Exhaustiva Pre-Firma

```php
// Validaciones obligatorias antes de firmar
- ✅ PDF no corrupto (parse completo)
- ✅ Sin JavaScript embebido
- ✅ Sin encryption activa
- ✅ Sin firmas previas conflictivas
- ✅ Tamaño < 50MB
- ✅ Páginas < 500
- ✅ Virus scan (si habilitado)
```

**Implementación**: Reutilizar [`PdfValidationService`](../../app/Services/Document/PdfValidationService.php) existente.

---

### 3. Aislamiento Multi-Tenant

**Decisión**: Tenant ID en TODOS los niveles

```php
// Signature metadata siempre incluye tenant_id
SignedDocument::create([
    'tenant_id' => $document->tenant_id, // ← CRÍTICO
    // ...
]);

// Middleware de validación
class ValidateSignatureAccess {
    public function handle(Request $request, Closure $next) {
        $signedDoc = SignedDocument::findOrFail($request->route('id'));
        
        if ($signedDoc->tenant_id !== auth()->user()->tenant_id) {
            abort(403, 'Unauthorized access to signature');
        }
        
        return $next($request);
    }
}
```

---

### 4. Rate Limiting en Firma

**Decisión**: Limitar Firmas por Tenant

```php
// config/signature.php
'rate_limits' => [
    'signatures_per_hour' => 100,    // Por tenant
    'signatures_per_day' => 1000,
    'concurrent_signings' => 5,      // Simultáneas
],

// Middleware
RateLimiter::for('signature', function (Request $request) {
    $tenant = auth()->user()->tenant;
    return Limit::perHour(100)->by($tenant->id);
});
```

**Justificación**:
- ✅ Prevenir abuso de TSA Qualified (costo)
- ✅ Proteger recursos de servidor
- ✅ Detectar comportamiento anómalo

---

### 5. Audit Trail Completo

**Decisión**: Log de TODOS los Eventos de Firma

```php
// Eventos a registrar
- signature.started
- signature.pdf_hashed
- signature.pkcs7_created
- signature.tsa_requested
- signature.tsa_received
- signature.pdf_embedded
- signature.completed
- signature.failed

// Con metadata
[
    'document_id' => $document->id,
    'signer_id' => $signer->id,
    'ip_address' => $request->ip(),
    'user_agent' => $request->userAgent(),
    'pades_level' => 'B-LT',
    'tsa_provider' => 'digicert',
    'duration_ms' => 1234,
    'error' => null,
]
```

---

### 6. Metadata Privacy (RGPD)

**Decisión**: Solo Hashes en PDF Embebido

```php
// ❌ NO embeber datos personales en PDF
'Firmalum_Signer_Email' => 'juan@example.com'  // ❌ RGPD violation

// ✅ SÍ embeber hashes
'Firmalum_IP_Hash' => sha256($ipAddress)        // ✅ Privacy-preserving
'Firmalum_Device_FP' => sha256($fingerprint)    // ✅ No identificable
'Firmalum_Signer_ID' => $uuid                   // ✅ UUID (no personal data)

// Datos completos solo en BD con control de acceso
```

---

## Plan de Testing

### Unit Tests

```php
// tests/Unit/Signature/PdfSignatureServiceTest.php
- testSignDocumentWithPadesBB()
- testSignDocumentWithPadesBLT()
- testSignDocumentWithTsaToken()
- testSignatureAppearanceGeneration()
- testMetadataEmbedding()
- testSignatureValidation()
- testErrorHandling()
- testCertificateLoading()

// tests/Unit/Signature/Pkcs7BuilderTest.php
- testCreatePkcs7Signature()
- testEmbedTsaToken()
- testVerifySignature()
- testAuthenticatedAttributes()

// tests/Unit/Signature/PdfEmbedderTest.php
- testImportPdf()
- testAddSignatureField()
- testEmbedPkcs7()
- testSignatureAppearance()
```

### Integration Tests

```php
// tests/Feature/Signature/SigningFlowTest.php
- testCompleteSigningFlow()
- testSignatureWithMultipleSigners()
- testSequentialSigning()
- testParallelSigning()
- testSignatureVerification()

// tests/Feature/Signature/AdobeValidationTest.php
- testAdobeReaderValidation()
- testPdfIntegrity()
- testTsaTokenValidation()
```

### Manual Validation

```bash
# Validar PDF firmado en Adobe Reader
1. Abrir PDF en Adobe Acrobat Reader DC
2. Click en "Signature Panel"
3. Verificar: ✅ "Signed and all signatures are valid"
4. Verificar: ✅ "Document has not been modified"
5. Verificar: ✅ Timestamp presente (si PAdES-B-LT)

# Validar con pdfsig (poppler-utils)
pdfsig -dump signed.pdf

# Validar con OpenSSL
openssl cms -verify -in signature.p7s -inform DER -content original.pdf
```

---

## Plan de Implementación

### Fase 1: Setup (Día 1-2)

```bash
# 1. Instalar dependencias
composer require setasign/fpdi phpseclib/phpseclib smalot/pdfparser

# 2. Generar certificado desarrollo
bash bin/generate-dev-cert.sh

# 3. Crear migración
php artisan make:migration create_signed_documents_table

# 4. Crear configuración
cp config/signature.php.example config/signature.php

# 5. Seed de prueba
php artisan db:seed --class=SigningProcessSeeder
```

---

### Fase 2: Servicios Core (Día 3-5)

```php
1. CertificateService     (Día 3 - 4h)
2. Pkcs7Builder          (Día 3-4 - 8h) ← MÁS COMPLEJO
3. PdfEmbedder           (Día 4 - 6h)
4. PdfSignatureService   (Día 4-5 - 8h)
```

---

### Fase 3: Integración (Día 6-7)

```php
1. Integrar con TsaService (Día 6 - 2h)
2. Integrar con EvidenceDossierService (Día 6 - 3h)
3. Livewire SignerPage → sign() method (Día 6 - 3h)
4. Tests unitarios (Día 7 - 4h)
5. Tests integración (Día 7 - 4h)
```

---

### Fase 4: Validación (Día 8-9)

```php
1. Validación Adobe Reader (Día 8)
2. Fixing de issues encontrados (Día 8-9)
3. Documentación (Día 9)
4. Code review (Día 9)
```

---

## Consecuencias

### Positivas

- ✅✅ **Cumplimiento eIDAS completo**: PAdES-B-LT con TSA Qualified
- ✅✅ **Validación independiente**: Adobe Reader compatible
- ✅ **Control total**: No vendor lock-in
- ✅ **Escalable**: Preparado para PAdES-B-LTA (Sprint 6+)
- ✅ **Integrado**: Aprovecha TsaService y EvidencePackage existentes
- ✅ **Testeable**: Arquitectura modular con certificados dev/prod
- ✅ **Metadata rica**: Evidencias embebidas + verificación pública

### Negativas

- ⚠️ **Complejidad**: PKCS#7 + ASN.1 requiere expertise crypto
- ⚠️ **Costo TSA**: ~$0.15 por firma (Qualified timestamp)
- ⚠️ **Mantenimiento certificados**: Renovación cada 2 años
- ⚠️ **Testing manual**: Validación en Adobe Reader no automatizable
- ⚠️ **Performance**: Firma puede tomar 2-5 segundos (TSA latency)

### Riesgos

| Riesgo | Probabilidad | Mitigación |
|--------|--------------|------------|
| PKCS#7 mal formado | Media | Tests exhaustivos + validación con OpenSSL |
| Adobe Reader no valida | Baja | Seguir specs ETSI EN 319 122-1 estrictamente |
| TSA timeout | Media | Retry logic + fallback a Standard TSA |
| Certificado expira | Alta | Alertas 30 días antes + proceso renovación |
| PDF corrupto post-firma | Baja | Validación hash pre/post firma |

---

## Referencias

### Normativas
- [eIDAS Regulation (EU) 910/2014](https://eur-lex.europa.eu/legal-content/EN/TXT/?uri=uriserv:OJ.L_.2014.257.01.0073.01.ENG)
- [ETSI EN 319 122-1 - CAdES Digital Signatures](https://www.etsi.org/deliver/etsi_en/319100_319199/31912201/)
- [ETSI TS 102 778 - PAdES Standard](https://www.etsi.org/deliver/etsi_ts/102700_102799/10277801/)
- [ISO 32000-2 - PDF 2.0](https://www.iso.org/standard/63534.html)
- [RFC 3161 - Time-Stamp Protocol (TSP)](https://www.rfc-editor.org/rfc/rfc3161)
- [RFC 5652 - Cryptographic Message Syntax (CMS)](https://www.rfc-editor.org/rfc/rfc5652)

### Técnicas
- [Adobe PDF Signature Build Guide](https://www.adobe.com/devnet-docs/acrobatetk/tools/DigSig/Acrobat_DigSig_Security.pdf)
- [OpenSSL PKCS#7 Documentation](https://www.openssl.org/docs/man1.1.1/man1/smime.html)
- [phpseclib Documentation](https://phpseclib.com/)
- [FPDI Documentation](https://www.setasign.com/products/fpdi/manual/)

### ADRs Relacionados
- [ADR-006: Evidence Capture Sprint 2](./adr-006-evidence-capture-sprint2.md)
- [ADR-007: Sprint 3 Retention/Verification/Upload](./adr-007-sprint3-retention-verification-upload.md)
- [ADR-008: TSA Strategy](./adr-008-tsa-strategy.md)

---

## Decisión Final

**APROBADO** para implementación en Sprint 4

**Nivel PAdES**: B-LT (Long-Term Validation)  
**Librería**: Enfoque Híbrido (FPDI + phpseclib)  
**Certificado**: Self-signed (dev) → CA-issued (prod)  
**TSA**: Qualified (integración con TsaService existente)  
**Firma**: Visible con metadata completa  
**Estimación**: 8-9 días implementación + testing

---

**LISTO PARA DESARROLLO**

El Developer puede iniciar E3-004 siguiendo este ADR como blueprint completo.

---

**Próximos pasos**:
1. Developer implementa servicios según especificaciones
2. Tech Lead revisa PRs progresivamente
3. Security Expert valida integración TSA y PKCS#7
4. QA valida en Adobe Reader
5. Product Owner aprueba demo funcional

---

**Fecha de revisión**: Post-Sprint 4 Retrospective  
**Responsable actualización**: Arquitecto de Software
