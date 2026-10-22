# Firmalum — vision del sistema

Plataforma SaaS de **firma electronica avanzada** conforme al reglamento
eIDAS, multi-tenant y de marca blanca. Su diferencial no es firmar: es
**generar, conservar y exportar evidencias legales** capaces de sostenerse
ante una auditoria o un procedimiento judicial.

## Flujo principal

```
Promotor                    Firmante                     Cualquiera
   |                            |                             |
   | 1. sube PDF                |                             |
   | 2. crea proceso de firma   |                             |
   |--------- email ----------->|                             |
   |                            | 3. abre /sign/{token}       |
   |                            | 4. verifica identidad (OTP) |
   |                            | 5. consiente y firma        |
   |                            |                             |
   |         6. firma PAdES-B-LT + sello TSA                  |
   |         7. dossier probatorio                            |
   |                            |                             |
   |<-------- notificacion -----|                             |
   |                            |<-- /download/{token} (30d)  |
   |                                                          |
   |                    8. verificacion publica --------------|
   |                       /verify/{codigo} o /v/{codigo}     |
```

## Capas

```
routes/web.php
  |
  +-- Livewire (app/Livewire/{Modulo}/)      UI y orquestacion
  +-- Controllers (app/Http/Controllers/)    descargas y API publica
  |
  +-- Services (app/Services/{Dominio}/)     toda la logica de negocio
  |     Signing/       PAdES: PKCS#7, X.509, embedding, TSA
  |     Evidence/      audit trail, consentimiento, IP, geo, huella, dossier
  |     Document/      subida, validacion, cifrado, documento final
  |     Verification/  verificacion publica, QR, integridad
  |     Archive/       retencion, re-sellado, archivo a largo plazo
  |     Otp/           codigos de un solo uso
  |     Notification/  avisos de firma y de finalizacion
  |
  +-- Models (app/Models/)                   Eloquent + scopes de tenant
```

Control de acceso en dos niveles: middleware de ruta (`identify.tenant`,
`EnsureSuperadmin`, `EnsureTenantAdmin`) **y** scope de tenant en el modelo
(`app/Models/Scopes/`, trait `BelongsToTenant`).

## Piezas clave

| Pieza | Donde | Que hace |
|---|---|---|
| Firma PAdES | `Services/Signing/PdfSignatureService` | Orquesta certificado, PKCS#7, embedding y sellado. Nivel B-LT en produccion. |
| Sellado de tiempo | `Services/Evidence/TsaService` | Firmaprofesional como primario, DigiCert de respaldo. `TSA_MOCK_ENABLED` en desarrollo. |
| Audit trail | `Services/Evidence/AuditTrailService` | Cadena de entradas encadenadas por hash. |
| Dossier probatorio | `Services/Evidence/EvidenceDossierService` | PDF exportable con todas las evidencias. |
| Verificacion publica | `Services/Verification/PublicVerificationService` | Sin registro, por web y por API REST. |
| Cifrado en reposo | `Services/Document/DocumentEncryptionService` | Ver [ADR-010](architecture/adr-010-encryption-at-rest.md). |

## Rutas publicas (sin autenticacion)

| Ruta | Para que |
|---|---|
| `/sign/{token}` | Firma. Requiere OTP. |
| `/verify`, `/verify/{codigo}`, `/v/{codigo}` | Verificacion publica. `/v/` es la version corta para QR. |
| `/download/{token}` | Copia del documento firmado. Caduca a los 30 dias. |
| `/invitation/{token}` | Aceptacion de invitacion. |
| `/api/v1/public/verify/...` | Verificacion programatica. |

Todas con limitacion de peticiones (`rate.limit.public`).

## Multi-tenancy

Un tenant por cliente, identificado por subdominio
(`{slug}.{APP_BASE_DOMAIN}`) o por cabecera `X-Tenant-ID`. El rol
`super_admin` atraviesa tenants; `tenant_admin` gobierna el suyo.

> `APP_BASE_DOMAIN` debe fijarse explicitamente en cada entorno. El valor por
> defecto del codigo es `firmalum.com`.

## Stack

Laravel 12 · Livewire 3 · PHP 8.2 · MySQL 8 · Redis · Vite · Tailwind.
Tests con PHPUnit, formato con Pint, analisis estatico con PHPStan/Larastan
nivel 6. Ver [REFACTORING_AND_TESTING.md](REFACTORING_AND_TESTING.md) para
el estado real de las puertas de calidad.
