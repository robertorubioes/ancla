# Sprint 4 - Plan de Ejecución: Sistema de Firma Electrónica

**Fecha creación**: 2025-12-29  
**Product Owner**: Firmalum Team  
**Duración estimada**: 4 semanas  
**Estado**: Planificado

---

## 🎯 SPRINT GOAL

**"Habilitar el flujo end-to-end de firma electrónica avanzada con notificaciones por email"**

Al finalizar Sprint 4, un promotor podrá:
1. Crear un proceso de firma con firmantes
2. Enviar solicitudes por email con enlace único
3. El firmante accederá con verificación OTP
4. El firmante dibujará su firma
5. El sistema aplicará firma PAdES al PDF
6. El promotor monitoreará el estado en tiempo real

**Milestone**: 🎯 **MVP FUNCIONAL** - Demo completa de firma electrónica

---

## 📊 ANÁLISIS ESTRATÉGICO

### Contexto del Sprint

#### Sprint 3 COMPLETADO ✅
- E2-001: Upload de documentos PDF
- E1-009: Verificación de integridad pública
- E1-008: Conservación de evidencias 5+ años
- **Total DONE acumulado**: 18 tareas (13 funcionales + 5 security)

#### Recomendación Business Strategist
Según [`docs/reviews/sprint3-roi-analysis.md`](../reviews/sprint3-roi-analysis.md):

**Opción D - Estrategia Híbrida Optimizada** ✅
- Sprint 4: Sistema de Firma → **MVP Funcional** (demo completa)
- Sprint 5: Entrega + Multi-tenant → **MVP Comercial** (primera venta)
- Sprint 6: Pulido + Marca Blanca → **MVP Completo** (launch)

**Time-to-MVP Comercial**: 8 semanas desde ahora

#### Por qué Sprint 4 es CRÍTICO
- ✅ **Core del producto**: Firma electrónica es la propuesta de valor central
- ✅ **Primera demo viable**: End-to-end funcional para mostrar a clientes
- ✅ **Desbloqueador de revenue**: Sin firma, no hay producto que vender
- ✅ **Ventaja competitiva**: Combinado con verificación pública (Sprint 3)

---

## 🔍 ANÁLISIS DE DEPENDENCIAS

### Grafo de Dependencias E3-xxx

```
E2-001 (Upload PDF) ✅ DONE
    ↓
E3-001 (Crear proceso) ← BLOQUEANTE
    ↓
    ├→ E4-001 (Email) ← Enviar solicitud
    ├→ E3-005 (Ver estado) ← Monitoring
    └→ E3-002 (Enlace único)
           ↓
       E4-003 (OTP) ← Verificación
           ↓
       E3-003 (Dibujar firma)
           ↓
       E3-004 (Firma PAdES) ← CRÍTICO
           ↓
       E5-001 (Doc final) → Sprint 5
```

### Ruta Crítica (Secuencia obligatoria)
1. **E3-001** → Base de todo el flujo
2. **E4-001** → Necesario para notificar firmantes
3. **E3-002** → Acceso del firmante
4. **E4-003** → Identificación eIDAS-compliant
5. **E3-003** → Captura de firma
6. **E3-004** → Sellado PAdES (más compleja)

### Tareas Independientes (Parallelizable)
- **E3-005** (Ver estado) → Solo depende de E3-001
- **E2-002** (Zonas firma) → Solo depende de E2-001 ✅

---

## 📋 HISTORIAS SELECCIONADAS

### Capacidad del Sprint
- **Velocidad histórica**: 4-5 tareas/sprint
- **Complejidad Sprint 4**: ALTA (E3-004 es 2x compleja)
- **Capacidad ajustada**: 5 tareas críticas + 2 altas = ~7 tareas

### Selección Final

| ID | Historia | Prioridad | ICE Score | Esfuerzo | Estado |
|----|----------|-----------|-----------|----------|--------|
| **E3-001** | Crear proceso de firma | 🔴 MUST | 8.7 | 3d | TO DO |
| **E3-002** | Acceso por enlace único | 🔴 MUST | 8.0 | 2d | TO DO |
| **E3-003** | Dibujar/seleccionar firma | 🔴 MUST | 7.7 | 3d | TO DO |
| **E3-004** | Aplicar firma PAdES al PDF | 🔴 MUST | 7.0 | 5d ⚠️ | TO DO |
| **E4-001** | Enviar solicitudes por email | 🔴 MUST | 8.7 | 2d | TO DO |
| **E3-005** | Ver estado de procesos | 🟡 SHOULD | 7.5 | 2d | TO DO |
| **E4-003** | Enviar códigos OTP | 🟡 SHOULD | 8.0 | 2d | TO DO |

**Total**: 7 tareas (5 MUST + 2 SHOULD) = ~19 días estimados

### Tareas NO Incluidas (Justificación)

| ID | Tarea | Razón de Exclusión | Sprint sugerido |
|----|-------|-------------------|-----------------|
| E2-002 | Zonas de firma | MVP con posición fija. Editor visual complejo | Sprint 5 |
| SEC-005 | Policies autorización | Ya tenemos middleware. Refinamiento | Sprint 5 |
| SEC-006 | Sanitizar PDF | Ya validamos en upload. Mejora incremental | Sprint 5 |
| SEC-008 | Rate limit APIs | LOW priority. No bloqueante | Sprint 6 |
| SEC-009 | GDPR minimización | LOW priority. Auditoría futura | Sprint 6 |
| SEC-010 | SRI scripts | LOW priority. Mejora seguridad | Sprint 6 |

---

## ✅ CRITERIOS DE ACEPTACIÓN REFINADOS

### E3-001: Crear proceso de firma

**Como** promotor  
**Quiero** crear un proceso de firma con uno o varios firmantes  
**Para** obtener sus firmas electrónicas de forma legal

#### Criterios de Aceptación

**AC1: Formulario de creación de proceso**
- [ ] Interfaz Livewire para crear proceso
- [ ] Campo: Documento a firmar (dropdown de PDFs subidos)
- [ ] Campo: Mensaje personalizado para firmantes (textarea, max 500 chars)
- [ ] Campo: Fecha límite de firma (datepicker, opcional)
- [ ] Botón "Añadir firmante" (dinámico)

**AC2: Gestión de firmantes**
- [ ] Cada firmante tiene: nombre, email, teléfono (opcional)
- [ ] Validación: email válido, nombre min 2 caracteres
- [ ] Orden de firma: secuencial o paralelo (radio button)
- [ ] Mínimo 1 firmante, máximo 10 firmantes
- [ ] Botón "Eliminar firmante" por cada uno

**AC3: Creación del proceso**
- [ ] Botón "Crear proceso" guarda en BD
- [ ] Tabla `signing_processes` con campos:
  - `id` (UUID)
  - `tenant_id` (FK)
  - `document_id` (FK a documents)
  - `created_by` (FK a users)
  - `status` (enum: draft, sent, in_progress, completed, expired, cancelled)
  - `signature_order` (enum: sequential, parallel)
  - `custom_message` (text)
  - `deadline_at` (timestamp nullable)
  - `completed_at` (timestamp nullable)
- [ ] Tabla `signers` con campos:
  - `id` (UUID)
  - `signing_process_id` (FK)
  - `name`, `email`, `phone`
  - `order` (int, para secuencial)
  - `status` (enum: pending, sent, viewed, signed, rejected)
  - `token` (string unique, para enlace)
  - `signed_at` (timestamp nullable)
- [ ] Estado inicial: `draft`
- [ ] Registro en audit trail

**AC4: Validaciones**
- [ ] No permitir duplicar emails en mismo proceso
- [ ] Fecha límite debe ser futura (min +1 día)
- [ ] Documento debe existir y pertenecer al tenant
- [ ] Usuario debe tener permiso `signature.create`

**AC5: Feedback usuario**
- [ ] Mensaje éxito: "Proceso creado. Ahora puedes enviarlo."
- [ ] Redirección a detalle del proceso
- [ ] Errores de validación en rojo bajo cada campo

#### Definition of Done
- [ ] Migración de `signing_processes` y `signers` ejecutada
- [ ] Modelo `SigningProcess` con relaciones
- [ ] Modelo `Signer` con scopes y métodos
- [ ] Componente Livewire `CreateSigningProcess`
- [ ] Vista Blade con Tailwind UI
- [ ] Tests: `CreateSigningProcessTest` (min 10 tests)
- [ ] Laravel Pint passed

---

### E3-002: Acceso por enlace único

**Como** firmante  
**Quiero** acceder al documento mediante un enlace único y seguro  
**Para** poder firmarlo sin necesidad de registro

#### Criterios de Aceptación

**AC1: Generación de token único**
- [ ] Al crear firmante, generar token aleatorio (32 chars)
- [ ] Token único global (índice unique en BD)
- [ ] Hash con `Str::random(32)` o similar
- [ ] Almacenar en campo `signers.token`

**AC2: URL pública de firma**
- [ ] Ruta pública: `/sign/{token}`
- [ ] Sin middleware de autenticación
- [ ] Resolver firmante por token
- [ ] Si token inválido → 404

**AC3: Validaciones de acceso**
- [ ] Token no expirado (deadline del proceso)
- [ ] Proceso no cancelado
- [ ] Firmante no ha firmado ya
- [ ] Si secuencial → firmantes anteriores han firmado
- [ ] Si falla validación → Página de error amigable

**AC4: Página de firma**
- [ ] Mostrar nombre del documento
- [ ] Preview del PDF (iframe o canvas)
- [ ] Mensaje personalizado del promotor
- [ ] Nombre del firmante (pre-rellenado)
- [ ] Botón "Continuar a verificación" (siguiente paso OTP)

**AC5: Registro de acceso**
- [ ] Al acceder, registrar en audit trail:
  - Evento: `signer.accessed`
  - IP, User-Agent, timestamp
  - Vincular a signer_id
- [ ] Cambiar estado firmante: `pending` → `viewed`

**AC6: Seguridad**
- [ ] Middleware rate limiting: 10 intentos/hora por IP
- [ ] Headers security: X-Frame-Options, CSP
- [ ] Sin exponer información sensible en error

#### Definition of Done
- [ ] Ruta `/sign/{token}` registrada en `routes/web.php`
- [ ] Controller `SignerAccessController` con método `show()`
- [ ] Componente Livewire `SignerPage` (gestiona el flujo)
- [ ] Vista Blade `signer-page.blade.php`
- [ ] Middleware `ValidateSignerToken`
- [ ] Tests: `SignerAccessTest` (min 12 tests)
- [ ] Laravel Pint passed

---

### E3-003: Dibujar/seleccionar firma

**Como** firmante  
**Quiero** dibujar mi firma manuscrita o seleccionar una tipográfica  
**Para** firmar el documento de forma personalizada

#### Criterios de Aceptación

**AC1: Opciones de firma**
- [ ] Tabs: "Dibujar" | "Tipográfica" | "Subir imagen"
- [ ] Por defecto: Tab "Dibujar" seleccionado

**AC2: Tab "Dibujar" (Manuscrita)**
- [ ] Canvas HTML5 (800x200px, responsive)
- [ ] Captura de trazos con mouse/touch
- [ ] Botón "Limpiar" (borrar canvas)
- [ ] Preview en tiempo real
- [ ] Exportar a PNG base64

**AC3: Tab "Tipográfica"**
- [ ] Input text: nombre del firmante (pre-rellenado)
- [ ] Selector de fuente: 3-4 fuentes cursivas (ej: Dancing Script, Pacifico)
- [ ] Preview en tiempo real con fuente seleccionada
- [ ] Generar imagen PNG con GD o Imagick

**AC4: Tab "Subir imagen" (Opcional para MVP)**
- [ ] Input file: PNG, JPG (max 1MB)
- [ ] Validación: dimensiones max 800x200
- [ ] Preview antes de confirmar
- [ ] Recortar/ajustar automáticamente

**AC5: Almacenamiento de firma**
- [ ] Tabla `signatures` con campos:
  - `id` (UUID)
  - `signer_id` (FK)
  - `type` (enum: drawn, typed, uploaded)
  - `image_path` (string, storage privado)
  - `font_name` (string nullable)
  - `created_at`
- [ ] Guardar imagen en `storage/app/signatures/{tenant_id}/{signer_id}.png`
- [ ] Encriptar archivo con Laravel encrypt()

**AC6: Validaciones**
- [ ] Canvas no vacío (min 10 puntos dibujados)
- [ ] Tipográfica: min 2 caracteres
- [ ] Imagen: MIME válido, tamaño correcto
- [ ] Firmante solo puede tener 1 firma activa

**AC7: Botón "Continuar"**
- [ ] Deshabilitado hasta que firma esté lista
- [ ] Al hacer click → Guardar firma y avanzar a consentimiento
- [ ] Feedback visual: spinner mientras guarda

**AC8: Opción "Guardar para futuros usos"**
- [ ] Checkbox: "Recordar mi firma"
- [ ] Si marcado → vincular a user (si existe) o email
- [ ] Próximas firmas → autocompletar

#### Definition of Done
- [ ] Migración `signatures` ejecutada
- [ ] Modelo `Signature` con accessors
- [ ] Componente Livewire `SignatureCapture`
- [ ] JavaScript: canvas drawing (Alpine.js o vanilla)
- [ ] Servicio `SignatureGenerationService` (typed signature)
- [ ] Vista Blade con tabs y canvas
- [ ] Tests: `SignatureCaptureTest` (min 10 tests)
- [ ] Laravel Pint passed

---

### E3-004: Aplicar firma PAdES al PDF

**Como** sistema  
**Quiero** aplicar la firma electrónica avanzada al PDF según estándar PAdES  
**Para** generar un documento legalmente válido conforme a eIDAS

#### Criterios de Aceptación

**AC1: Arquitectura de firma (Requiere ADR-009)**
- [ ] **ADR-009 debe ser creado por Arquitecto antes de implementar**
- [ ] Decisiones requeridas:
  - Librería PHP: tcpdf, setasign/fpdi, phpseclib
  - Certificado: self-signed (dev) vs CA (prod)
  - Nivel PAdES: PAdES-B-B (básico) vs PAdES-LTV (long-term)
  - Embedding: firma visible vs invisible
  - Metadata: cómo embeber evidencias en PDF

**AC2: Generación de certificado de plataforma**
- [ ] Script para generar certificado X.509 para Firmalum
- [ ] Almacenar cert + private key en storage seguro
- [ ] Configuración en `.env`:
  - `SIGNATURE_CERT_PATH`
  - `SIGNATURE_KEY_PATH`
  - `SIGNATURE_KEY_PASSWORD`
- [ ] Documentar renovación de certificado

**AC3: Proceso de firma**
- [ ] Input: PDF original + firma imagen + metadata evidencias
- [ ] Embedding de firma en zona designada (posición fija en MVP)
- [ ] Crear estructura PKCS#7/CMS con:
  - Hash SHA-256 del PDF
  - Certificado de plataforma
  - Timestamp TSA (Qualified según ADR-008)
  - Metadata de evidencias (device, IP, geo, consent)
- [ ] Incrustar PKCS#7 en PDF según ISO 32000-2
- [ ] Output: PDF firmado con extensión PAdES

**AC4: Metadata embebida en PDF**
- [ ] Campo PDF: `/Firmalum_EvidencePackage_ID` → UUID del evidence package
- [ ] Campo PDF: `/Firmalum_Verification_URL` → URL pública de verificación
- [ ] Campo PDF: `/Firmalum_QR_Code` → Embedded QR como imagen
- [ ] Campo PDF: `/SignatureTime` → ISO 8601 timestamp
- [ ] Campo PDF: `/SignerInfo` → Nombre, email (hasheado)

**AC5: Validación de firma**
- [ ] Verificar que PDF firmado pasa validación Adobe Reader
- [ ] Verificar que hash coincide con original
- [ ] Verificar que TSA token es válido
- [ ] Logs de errores si falla cualquier paso

**AC6: Almacenamiento**
- [ ] Guardar PDF firmado en `storage/app/signed/{tenant_id}/{process_id}/{signer_id}.pdf`
- [ ] Actualizar `documents.signed_version_path`
- [ ] Cambiar estado firmante: `viewed` → `signed`
- [ ] Cambiar estado proceso si todos firmaron: → `completed`
- [ ] Registrar en audit trail con hash del PDF firmado

**AC7: Integración con sistema de evidencias**
- [ ] Crear `EvidencePackage` completo:
  - Document hash (original)
  - Signature image
  - Device fingerprint
  - Geolocation
  - IP resolution
  - Consent record
  - TSA token (Qualified)
  - Audit trail entries
- [ ] Generar verification code
- [ ] Generar QR code

**AC8: Configuración**
- [ ] `config/signature.php` con:
  - TSA endpoint (qualified)
  - Certificado path
  - Posición firma (x, y, width, height)
  - Fuente para metadata
  - Nivel de compresión PDF

#### Definition of Done
- [ ] **ADR-009 aprobado por Arquitecto** ⚠️
- [ ] Migración `signed_documents` (si necesaria)
- [ ] Servicio `PdfSignatureService` con método `sign()`
- [ ] Integración con `TsaService` (nivel Qualified)
- [ ] Integración con `EvidenceDossierService`
- [ ] Script `bin/generate-cert.sh` para certificado
- [ ] Config `signature.php` completo
- [ ] Tests: `PdfSignatureServiceTest` (min 8 tests)
- [ ] Test de integración: firma PDF real y valida en Adobe Reader
- [ ] Laravel Pint passed

**⚠️ BLOQUEADOR**: Esta tarea NO puede empezar hasta que Arquitecto entregue ADR-009.

---

### E4-001: Enviar solicitudes por email

**Como** sistema  
**Quiero** enviar emails a los firmantes con su enlace único  
**Para** notificarles que tienen un documento pendiente de firma

#### Criterios de Aceptación

**AC1: Acción "Enviar proceso"**
- [ ] Botón "Enviar solicitudes" en detalle del proceso
- [ ] Solo disponible si estado = `draft`
- [ ] Confirmación: "¿Enviar a X firmantes?"
- [ ] Al confirmar → cambiar estado a `sent`

**AC2: Generación de emails**
- [ ] Por cada firmante pendiente:
  - Generar email con plantilla personalizable
  - Incluir enlace único: `{APP_URL}/sign/{token}`
  - Incluir mensaje personalizado del promotor
  - Incluir nombre del documento
  - Incluir fecha límite (si existe)
- [ ] Subject: "[{Nombre Tenant}] Documento pendiente de firma: {Nombre Doc}"

**AC3: Plantilla de email**
- [ ] Vista Blade: `emails/signature-request.blade.php`
- [ ] Soporte Markdown para mensaje personalizado
- [ ] Diseño responsive (mobile-friendly)
- [ ] Branding del tenant (logo, colores)
- [ ] Botón CTA: "Firmar Documento"
- [ ] Footer: info de contacto, unsubscribe (futuro)

**AC4: Envío asíncrono**
- [ ] Job: `SendSignatureRequestJob`
- [ ] Queue: `notifications`
- [ ] Por cada firmante: dispatch job individual
- [ ] Retry: 3 intentos con backoff [1min, 5min, 15min]
- [ ] Log de envíos exitosos/fallidos

**AC5: Tracking de emails**
- [ ] Tabla `email_logs` con campos:
  - `id`, `signer_id`, `sent_at`
  - `status` (enum: sent, delivered, opened, failed)
  - `provider_id` (ej: SES Message ID)
  - `error_message` (si falla)
- [ ] Webhook SES/SMTP para tracking (opcional MVP)

**AC6: Configuración SMTP/SES**
- [ ] `.env` configurado:
  - `MAIL_MAILER=smtp` o `ses`
  - `MAIL_FROM_ADDRESS`
  - `MAIL_FROM_NAME` (personalizable por tenant)
- [ ] Documentar setup de SES en [`docs/deployment/email-setup.md`](../deployment/email-setup.md)
- [ ] Fallback a log en desarrollo

**AC7: Testing de emails**
- [ ] Comando artisan: `php artisan signature:test-email {email}`
- [ ] Envía email de prueba a dirección especificada
- [ ] Útil para validar configuración

**AC8: Validaciones**
- [ ] Proceso debe estar en estado `draft` o `sent`
- [ ] No re-enviar a firmantes ya firmados
- [ ] Email válido (validar formato)
- [ ] Rate limiting: max 100 emails/hora por tenant

#### Definition of Done
- [ ] Migración `email_logs` ejecutada
- [ ] Modelo `EmailLog`
- [ ] Mailable: `SignatureRequest`
- [ ] Job: `SendSignatureRequestJob`
- [ ] Vista email: `signature-request.blade.php`
- [ ] Comando: `SignatureTestEmailCommand`
- [ ] Tests: `SignatureRequestEmailTest` (min 8 tests)
- [ ] Documentación: `email-setup.md`
- [ ] Laravel Pint passed

---

### E3-005: Ver estado de procesos

**Como** promotor  
**Quiero** ver el estado de cada proceso de firma en tiempo real  
**Para** hacer seguimiento y saber quién ha firmado

#### Criterios de Aceptación

**AC1: Listado de procesos**
- [ ] Ruta: `/signing-processes`
- [ ] Tabla con columnas:
  - Nombre documento
  - Firmantes (X de Y firmados)
  - Estado (badge con color)
  - Fecha creación
  - Fecha límite
  - Acciones (Ver detalle, Cancelar)
- [ ] Filtros:
  - Por estado (todos, borrador, en curso, completados)
  - Por fecha (última semana, último mes, custom)
  - Por documento
- [ ] Paginación: 20 por página
- [ ] Ordenar por fecha creación DESC

**AC2: Detalle de proceso**
- [ ] Ruta: `/signing-processes/{id}`
- [ ] Información del proceso:
  - Documento (nombre, preview)
  - Estado general (badge)
  - Mensaje personalizado
  - Fecha creación, fecha límite
  - Creado por (usuario)
- [ ] Timeline de firmantes:
  - Por cada firmante: nombre, email, estado, fecha firma
  - Estados con iconos: pendiente ⏳, enviado 📧, visto 👁️, firmado ✅
  - Si secuencial → numerar orden
  - Si paralelo → mostrar todos al mismo nivel
- [ ] Timeline de eventos (audit trail):
  - Creado, enviado, accedido, firmado
  - Timestamp, usuario/IP
  - Expandible/colapsable

**AC3: Badges de estado**
- [ ] `draft` → Gris "Borrador"
- [ ] `sent` → Azul "Enviado"
- [ ] `in_progress` → Amarillo "En progreso"
- [ ] `completed` → Verde "Completado"
- [ ] `expired` → Rojo "Expirado"
- [ ] `cancelled` → Rojo "Cancelado"

**AC4: Actualización automática**
- [ ] Polling cada 30 segundos (Livewire wire:poll)
- [ ] O usar WebSockets (Laravel Echo) si disponible
- [ ] Indicador visual: "Actualizado hace X segundos"

**AC5: Acciones disponibles**
- [ ] Si `draft` → "Enviar solicitudes" (va a E4-001)
- [ ] Si `sent` o `in_progress` → "Reenviar recordatorios" (futuro)
- [ ] Si cualquier estado → "Cancelar proceso" (con confirmación)
- [ ] Si `completed` → "Descargar documento firmado" (Sprint 5)
- [ ] Si `completed` → "Descargar dossier de evidencias" (Sprint 5)

**AC6: Permisos**
- [ ] Solo usuario del tenant puede ver sus procesos
- [ ] Middleware: `BelongsToTenant` en queries
- [ ] Admin puede ver todos del tenant
- [ ] Operator solo los creados por él

**AC7: Performance**
- [ ] Eager loading de relaciones (document, signers, creator)
- [ ] Cache de contadores (X de Y firmados)
- [ ] Índices en BD: `tenant_id`, `status`, `created_at`

#### Definition of Done
- [ ] Rutas `/signing-processes` y `/signing-processes/{id}`
- [ ] Controller: `SigningProcessController` (index, show)
- [ ] Componente Livewire: `SigningProcessList`
- [ ] Componente Livewire: `SigningProcessDetail`
- [ ] Vistas Blade con Tailwind
- [ ] Policies: `SigningProcessPolicy`
- [ ] Tests: `SigningProcessListTest`, `SigningProcessDetailTest` (min 12 tests)
- [ ] Laravel Pint passed

---

### E4-003: Enviar códigos OTP

**Como** sistema  
**Quiero** enviar códigos OTP por email para verificar la identidad del firmante  
**Para** cumplir con los requisitos de firma electrónica avanzada (eIDAS)

#### Criterios de Aceptación

**AC1: Generación de código OTP**
- [ ] Al acceder a `/sign/{token}` (después de E3-002):
  - Generar código numérico de 6 dígitos
  - Almacenar en tabla `verification_codes` existente
  - Expiración: 10 minutos
  - Máximo 3 intentos de verificación
- [ ] Hash del código en BD (no plain text)

**AC2: Envío de código por email**
- [ ] Email automático al acceder a firma
- [ ] Subject: "[{Tenant}] Código de verificación: {codigo}"
- [ ] Plantilla simple: solo código + expiración
- [ ] Envío síncrono (no job, es crítico)

**AC3: Pantalla de verificación**
- [ ] Después de E3-002, mostrar formulario OTP
- [ ] Input numérico: 6 dígitos (auto-focus)
- [ ] Botón "Verificar código"
- [ ] Link "Reenviar código" (cooldown 60 segundos)
- [ ] Timer visual: "Expira en 9:45"

**AC4: Validación de código**
- [ ] Verificar hash coincide
- [ ] Verificar no expirado
- [ ] Verificar intentos < 3
- [ ] Si válido:
  - Marcar código como `used`
  - Avanzar a E3-003 (dibujar firma)
  - Registrar en audit trail
- [ ] Si inválido:
  - Incrementar intentos
  - Mostrar error: "Código incorrecto (X de 3)"
  - Si 3 intentos → bloquear acceso 1 hora

**AC5: Reenvío de código**
- [ ] Botón "Reenviar código"
- [ ] Invalidar código anterior
- [ ] Generar nuevo código
- [ ] Enviar nuevo email
- [ ] Máximo 3 reenvíos por token
- [ ] Cooldown 60 segundos entre reenvíos

**AC6: Seguridad**
- [ ] Rate limiting: 5 intentos/minuto por IP
- [ ] Bloqueo temporal tras 3 fallos
- [ ] Códigos no reutilizables
- [ ] Logs de intentos fallidos

**AC7: Alternativa SMS (Futuro - NO MVP)**
- [ ] Preparar estructura para SMS en Sprint 5
- [ ] Campo `phone` en `signers` ya existe
- [ ] Config: `OTP_CHANNEL=email` (default)

**AC8: Testing sin email**
- [ ] En development: mostrar código en logs
- [ ] En testing: mock de envío
- [ ] Comando artisan para bypass OTP: `php artisan otp:bypass {token}`

#### Definition of Done
- [ ] Reutilización tabla `verification_codes` (ya existe de E1-009)
- [ ] Servicio: `OtpService` con `generate()`, `verify()`, `resend()`
- [ ] Mailable: `OtpVerification`
- [ ] Componente Livewire: `OtpVerification`
- [ ] Vista Blade: `otp-verification.blade.php`
- [ ] Middleware: `RequireOtpVerification`
- [ ] Tests: `OtpVerificationTest` (min 10 tests)
- [ ] Laravel Pint passed

---

## 🚨 RIESGOS Y BLOQUEADORES

### Riesgos Identificados

| # | Riesgo | Probabilidad | Impacto | Mitigación |
|---|--------|--------------|---------|------------|
| **R1** | E3-004 (PAdES) más complejo de lo estimado | 🟡 MEDIA | 🔴 ALTO | ADR-009 obligatorio. Consultar con Arquitecto antes de empezar. Considerar librería externa probada |
| **R2** | Certificado CA para firma no disponible | 🟢 BAJA | 🟡 MEDIO | Usar self-signed en desarrollo. Documentar obtención de CA para producción |
| **R3** | Configuración SES/SMTP bloqueada | 🟡 MEDIA | 🟡 MEDIO | Usar Mailtrap para desarrollo. Documentar setup SES detallado |
| **R4** | Canvas signature no funciona en móvil | 🟡 MEDIA | 🟡 MEDIO | Testear en iOS/Android. Considerar librería signature_pad.js |
| **R5** | TSA Qualified muy lento (>5s) | 🟢 BAJA | 🟡 MEDIO | Timeout configurable. Fallback a Standard TSA si falla |
| **R6** | Dependencias circulares entre tareas | 🟢 BAJA | 🟡 MEDIO | Seguir orden del grafo de dependencias estricto |
| **R7** | Velocity menor a estimada (E3-004) | 🟡 MEDIA | 🔴 ALTO | E3-004 puede consumir Sprint completo. Plan B: mover E3-005 a Sprint 5 |

### Bloqueadores Externos

| Bloqueador | Responsable | Fecha límite | Estado |
|------------|-------------|--------------|--------|
| **ADR-009: Diseño firma PAdES** | Arquitecto | Semana 1 Sprint 4 | ⏳ Pendiente |
| **Certificado X.509 para firma** | DevOps | Semana 1 Sprint 4 | ⏳ Pendiente |
| **AWS SES configurado** | DevOps | Semana 2 Sprint 4 | ⏳ Pendiente |
| **TSA Qualified proveedor** | Product Owner | Semana 2 Sprint 4 | ⏳ Pendiente |

### Dependencias de Otros Sprints

| Tarea | Depende de (Sprint anterior) | Estado |
|-------|------------------------------|--------|
| E3-001 | E2-001 (Upload PDF) | ✅ DONE |
| E3-004 | E1-001 (TSA Service) | ✅ DONE |
| E3-004 | E1-006 (Audit Trail) | ✅ DONE |
| E4-003 | E0-003 (Auth System) | ✅ DONE |

---

## 📅 PLAN DE EJECUCIÓN

### Estrategia de Implementación

**Enfoque**: **Vertical Slice** - Implementar el flujo completo en incrementos funcionales

#### Fase 1: Fundación (Semana 1) - 5 días
**Objetivo**: Base de datos, modelos y arquitectura

**Semana 1 - Día 1-2**:
- [ ] **ADR-009**: Arquitecto diseña estrategia firma PAdES ⚠️ BLOQUEANTE
- [ ] Setup certificado X.509 (self-signed para dev)
- [ ] Migración `signing_processes` y `signers`
- [ ] Modelos `SigningProcess` y `Signer`

**Semana 1 - Día 3-4**:
- [ ] **E3-001**: Implementar creación de proceso (formulario + backend)
- [ ] Componente Livewire `CreateSigningProcess`
- [ ] Tests básicos de creación

**Semana 1 - Día 5**:
- [ ] **E4-001**: Setup email (SES/SMTP config)
- [ ] Plantilla email básica
- [ ] Job `SendSignatureRequestJob`

**Entregable Semana 1**: Proceso de firma creado + emails enviados

---

#### Fase 2: Flujo de Firmante (Semana 2) - 5 días
**Objetivo**: Firmante puede acceder, verificar y dibujar firma

**Semana 2 - Día 1-2**:
- [ ] **E3-002**: Implementar acceso por token
- [ ] Middleware `ValidateSignerToken`
- [ ] Página pública `/sign/{token}`

**Semana 2 - Día 3**:
- [ ] **E4-003**: Sistema OTP
- [ ] Generación y envío de códigos
- [ ] Pantalla de verificación

**Semana 2 - Día 4-5**:
- [ ] **E3-003**: Captura de firma
- [ ] Canvas drawing (JavaScript)
- [ ] Firma tipográfica
- [ ] Almacenamiento de imagen

**Entregable Semana 2**: Firmante puede acceder con OTP y dibujar firma

---

#### Fase 3: Firma PAdES (Semana 3) - 5 días
**Objetivo**: Aplicar firma electrónica al PDF

**Semana 3 - Día 1-2**:
- [ ] **E3-004** (Parte 1): Investigación de librería PAdES
- [ ] Proof of concept: firma simple en PDF
- [ ] Validar en Adobe Reader

**Semana 3 - Día 3-4**:
- [ ] **E3-004** (Parte 2): Implementar `PdfSignatureService`
- [ ] Integración con TSA Qualified
- [ ] Embedding de firma imagen
- [ ] Metadata de evidencias

**Semana 3 - Día 5**:
- [ ] **E3-004** (Parte 3): Integración con `EvidencePackage`
- [ ] Generación de verification code
- [ ] Tests de integración

**Entregable Semana 3**: PDF firmado con PAdES validable

---

#### Fase 4: Monitoring y Pulido (Semana 4) - 5 días
**Objetivo**: UI de seguimiento y refinamiento

**Semana 4 - Día 1-2**:
- [ ] **E3-005**: Listado de procesos
- [ ] Detalle de proceso con timeline
- [ ] Políticas de acceso

**Semana 4 - Día 3**:
- [ ] Tests de integración end-to-end
- [ ] Fixing de bugs encontrados
- [ ] Refinamiento UX

**Semana 4 - Día 4**:
- [ ] Documentación de usuario
- [ ] Guía de configuración (SES, certificado)
- [ ] ADR-009 review por Tech Lead

**Semana 4 - Día 5**:
- [ ] Demo Sprint 4
- [ ] Retrospectiva
- [ ] Planning Sprint 5

**Entregable Semana 4**: 🎯 **MVP FUNCIONAL** - Demo completa

---

### Orden de Implementación (Secuencial)

```
┌─────────────────────────────────────────────────────────┐
│ SEMANA 1: FUNDACIÓN                                     │
├─────────────────────────────────────────────────────────┤
│ ADR-009 → E3-001 → E4-001                               │
│ (Arquitecto) → (Beta) → (Beta)                          │
│ Entregable: Proceso creado + Email enviado              │
└─────────────────────────────────────────────────────────┘
                    ↓
┌─────────────────────────────────────────────────────────┐
│ SEMANA 2: ACCESO Y FIRMA                                │
├─────────────────────────────────────────────────────────┤
│ E3-002 → E4-003 → E3-003                                │
│ (Beta) → (Alpha) → (Beta)                               │
│ Entregable: Firmante accede + OTP + Dibuja firma        │
└─────────────────────────────────────────────────────────┘
                    ↓
┌─────────────────────────────────────────────────────────┐
│ SEMANA 3: FIRMA PADES (CRÍTICA)                         │
├─────────────────────────────────────────────────────────┤
│ E3-004 (POC → Implementación → Integración)             │
│ (Alpha - 5 días completos)                              │
│ Entregable: PDF firmado con PAdES                       │
└─────────────────────────────────────────────────────────┘
                    ↓
┌─────────────────────────────────────────────────────────┐
│ SEMANA 4: MONITORING Y PULIDO                           │
├─────────────────────────────────────────────────────────┤
│ E3-005 → Tests E2E → Documentación → Demo               │
│ (Beta) → (QA) → (Docs) → (PO)                           │
│ Entregable: 🎯 MVP FUNCIONAL                            │
└─────────────────────────────────────────────────────────┘
```

---

## ✅ DEFINITION OF DONE (Sprint 4)

Un Sprint 4 está **DONE** cuando:

### Funcionalidad
- [ ] Todas las 7 historias implementadas (5 MUST + 2 SHOULD)
- [ ] Demo end-to-end funcional:
  1. Promotor crea proceso con 2 firmantes
  2. Sistema envía emails automáticamente
  3. Firmante 1 accede, verifica OTP, dibuja firma, firma PDF
  4. Firmante 2 accede, verifica OTP, dibuja firma, firma PDF
  5. Promotor ve proceso completado en dashboard
  6. PDF descargable tiene firmas visibles y válidas

### Calidad de Código
- [ ] Tests: mínimo 60 tests (target >70)
- [ ] Cobertura: >85%
- [ ] Laravel Pint: 0 issues
- [ ] PHPStan: 0 errores (level 5)
- [ ] No security vulnerabilities (composer audit)

### Documentación
- [ ] **ADR-009**: Firma PAdES aprobado
- [ ] README actualizado con setup de Sprint 4
- [ ] Guía de configuración: [`docs/deployment/signature-setup.md`](../deployment/signature-setup.md)
- [ ] Guía de usuario: cómo crear y enviar proceso
- [ ] API docs (si hay endpoints REST)

### Integración
- [ ] Migración ejecutada en staging
- [ ] Seed data de ejemplo funciona
- [ ] Email delivery probado (Mailtrap/SES)
- [ ] PDF firmado valida en Adobe Reader
- [ ] TSA Qualified probado (o mock documentado)

### Code Review
- [ ] Tech Lead aprueba todos los PRs
- [ ] Security Expert revisa E3-004 (PAdES)
- [ ] No deuda técnica crítica introducida

### Despliegue
- [ ] Branch `sprint4` mergeado a `develop`
- [ ] Staging desplegado y funcional
- [ ] Certificado X.509 instalado
- [ ] Variables `.env` documentadas

---

## 📊 MÉTRICAS DE ÉXITO

### KPIs Técnicos

| Métrica | Baseline Sprint 3 | Target Sprint 4 |
|---------|-------------------|-----------------|
| **Tests escritos** | 64 | 130+ |
| **Cobertura** | >85% | >85% |
| **LOC añadidas** | ~8,500 | ~10,000 |
| **Archivos creados** | 40 | 50+ |
| **Velocidad** | 3 tareas | 7 tareas ⚠️ |

### KPIs de Producto

| Métrica | Sprint 3 | Target Sprint 4 |
|---------|----------|-----------------|
| **Flujos end-to-end completos** | 0 | 1 (firma) |
| **Features MVP completadas** | 13/21 (62%) | 20/21 (95%) |
| **APIs públicas** | 1 | 1 (sin cambios) |
| **Páginas UX** | 3 | 8 |

### KPIs de Negocio

| Métrica | Status Sprint 3 | Target Sprint 4 |
|---------|-----------------|-----------------|
| **MVP demo-able** | ❌ NO | ✅ SÍ |
| **Cumplimiento eIDAS** | ⚠️ Parcial | ✅ Completo |
| **Diferenciadores únicos** | 1 | 2 |
| **Time-to-first-sale** | N/A | Sprint 5 (4 semanas) |

---

## 🎬 PREPARACIÓN PRE-SPRINT

### Checklist antes de empezar Sprint 4

**Product Owner**:
- [ ] Historias refinadas y aceptadas
- [ ] Prioridad confirmada con Business Strategist
- [ ] Stakeholders informados del Sprint Goal

**Arquitecto**:
- [ ] **ADR-009** diseñado y aprobado (Semana 1, Día 1-2) ⚠️
- [ ] Revisión de ADR-008 (estrategia TSA)
- [ ] Arquitectura de firma validada

**Developer**:
- [ ] Branch `sprint4` creado desde `develop`
- [ ] Entorno local actualizado
- [ ] Dependencias instaladas
- [ ] Seed data de Sprint 3 funcional

**DevOps**:
- [ ] Certificado X.509 self-signed generado
- [ ] SMTP/SES configurado en staging
- [ ] TSA Qualified endpoint documentado
- [ ] Secrets en `.env.example` actualizados

**Security Expert**:
- [ ] Revisión de criterios de aceptación
- [ ] Plan de security review para E3-004
- [ ] Checklist de validaciones preparada

**Tech Lead**:
- [ ] Code review workflow definido
- [ ] CI/CD pipeline actualizado para Sprint 4
- [ ] Staging environment limpio

---

## 📞 COMUNICACIÓN Y CEREMONIAS

### Daily Standups (15 min)
- **Frecuencia**: Todos los días laborables
- **Formato**:
  1. ¿Qué hice ayer?
  2. ¿Qué haré hoy?
  3. ¿Tengo bloqueos?
- **Foco**: Riesgos de E3-004 (PAdES)

### Sprint Planning (2 horas)
- **Fecha**: Primer día del Sprint 4
- **Agenda**:
  1. Presentación Sprint Goal
  2. Revisión de historias y criterios
  3. Estimación por tarea
  4. Asignación de tareas
  5. Identificación de riesgos

### Mid-Sprint Review (30 min)
- **Fecha**: Final Semana 2
- **Objetivo**: Validar avance 50%
- **Checkpoint**: E3-001, E3-002, E4-001, E4-003, E3-003 implementadas

### Sprint Review/Demo (1 hora)
- **Fecha**: Último día del Sprint 4
- **Audiencia**: Product Owner, Business Strategist, Stakeholders
- **Demo**: Flujo completo end-to-end en staging

### Sprint Retrospective (1 hora)
- **Formato**: Start/Stop/Continue
- **Foco**: ¿Qué aprendimos de E3-004?
- **Output**: Mejoras para Sprint 5

---

## 🔄 PLAN B - CONTINGENCIA

### Si E3-004 (PAdES) se retrasa

**Escenario**: E3-004 consume toda la Semana 3 y parte de Semana 4

**Acciones**:
1. **Mover E3-005 a Sprint 5** (ver estado es nice-to-have)
2. **Simplificar PAdES**: Solo PAdES-B-B (sin LTV)
3. **Firma invisible**: Sin embedding de imagen (más simple)
4. **Mock de TSA Qualified**: Usar Standard TSA temporalmente

**Criterio de activación Plan B**: Final Semana 2, E3-004 no iniciada

---

## 🚀 SIGUIENTE PASO

### Acción Inmediata

**1. Solicitar ADR-009 al Arquitecto**
```markdown
Título: ADR-009 - Estrategia de Firma Electrónica PAdES
Contexto: Sprint 4 requiere implementar firma PAdES compliant con eIDAS
Deadline: Semana 1, Día 2 del Sprint 4
Decisiones requeridas:
- Librería PHP (tcpdf vs setasign/fpdi vs phpseclib)
- Nivel PAdES (B-B vs B-T vs LTV)
- Certificado (self-signed vs CA)
- Estructura PKCS#7
- Embedding de evidencias
```

**2. Preparar Infraestructura**
- Generar certificado X.509 self-signed
- Configurar SMTP/Mailtrap para testing
- Documentar TSA Qualified endpoint

**3. Comunicar a Equipo**
- Sprint Goal definido
- Historias priorizadas
- Riesgos identificados
- Plan de ejecución claro

---

## 📎 ANEXOS

### Estimación Detallada por Tarea

| Tarea | Complejidad | Esfuerzo (días) | Riesgo |
|-------|-------------|-----------------|--------|
| E3-001 | Media | 3 | Bajo |
| E3-002 | Media | 2 | Bajo |
| E3-003 | Media-Alta | 3 | Medio (canvas cross-browser) |
| E3-004 | **Alta** | **5** | **Alto** (librería, certificado) |
| E4-001 | Media | 2 | Medio (SES config) |
| E3-005 | Media | 2 | Bajo |
| E4-003 | Media | 2 | Bajo |
| **TOTAL** | - | **19 días** | - |

**Capacidad Sprint**: 20 días (4 semanas × 5 días)  
**Buffer**: 1 día (5%)  
**Feasible**: ✅ SÍ (con Plan B si E3-004 se alarga)

---

### Referencias

- [Backlog completo](../backlog.md)
- [Kanban actual](../kanban.md)
- [Análisis ROI Sprint 3](../reviews/sprint3-roi-analysis.md)
- [ADR-007: Sprint 3 Architecture](../architecture/adr-007-sprint3-retention-verification-upload.md)
- [ADR-008: TSA Strategy](../architecture/adr-008-tsa-strategy.md)
- [ADR-009: PAdES Signature](../architecture/adr-009-pades-signature.md) ⚠️ **Pendiente**

---

**Documento aprobado por**: Product Owner  
**Fecha de aprobación**: 2025-12-29  
**Próxima revisión**: Daily durante Sprint 4

---

**LISTO PARA ARQUITECTO** - ADR-009 requerido antes de iniciar implementación
