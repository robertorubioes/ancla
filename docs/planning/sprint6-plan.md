# Sprint 6 Plan - MVP 100% Completo + Multi-tenant Foundation

> 📅 **Fecha**: 2025-12-30  
> 🎯 **Sprint Goal**: "Habilitar operación multi-tenant y completar el MVP al 100% para producción"  
> 🚀 **Milestone**: MVP 100% COMPLETO - PRODUCTO LISTO PARA PRODUCCIÓN

---

## 📊 Contexto

### Sprint 5 - Resultados
- ✅ 4/7 tareas completadas (57% - Plan B activado exitosamente)
- ✅ **FLUJO COMPLETO END-TO-END**: Upload → Firma → Descarga funcional
- ✅ **Code Review**: 98/100 score (excelente)
- ✅ **203 tests totales** acumulados
- ✅ Historias completadas:
  - E5-001: Generar documento final firmado
  - E5-002: Enviar copia a firmantes
  - E5-003: Descargar documento y dossier
  - E3-006: Cancelar proceso de firma
- ⏭️ Historias movidas a Sprint 6: E0-001, E0-002, E2-003

### Gap Actual
El sistema tiene flujo completo funcional pero:
- ❌ No soporta múltiples organizaciones (single-tenant actual)
- ❌ No hay gestión de usuarios por tenant
- ❌ No hay panel de administración superadmin
- ❌ Documentos no están encriptados at-rest
- ❌ No hay backup automático configurado

### Valor de Sprint 6
Este sprint transforma Firmalum de single-tenant a **SaaS multi-tenant** y asegura la protección de datos con **encriptación enterprise**, completando el MVP al 100% para onboarding de clientes reales.

---

## 🎯 Sprint Goal Detallado

**Objetivo Principal:**  
Habilitar la operación multi-tenant con aislamiento completo y asegurar la protección de documentos con encriptación at-rest, completando el MVP al 100%.

**Entregables:**
1. Panel de administración superadmin para gestionar organizaciones
2. CRUD completo de tenants (organizaciones)
3. Sistema de invitaciones de usuarios con roles (admin, operator, viewer)
4. Gestión completa de usuarios por organización
5. Encriptación AES-256-GCM de documentos at-rest
6. Backup automático configurado
7. Tests de aislamiento multi-tenant

**Resultado esperado:**  
Producto 100% completo, multi-tenant operativo, con seguridad enterprise, listo para onboarding de múltiples clientes en producción.

---

## 📋 Historias Seleccionadas

### MUST (Prioridad 0 - Bloqueantes para MVP)

| ID | Historia | Squad | Estimación | Valor | Complejidad |
|----|----------|-------|------------|-------|-------------|
| E0-001 | Crear nuevas organizaciones (tenants) | Alpha | 5 días | 9 | Media |
| E0-002 | Gestionar usuarios de organización | Alpha | 3 días | 8 | Media |
| E2-003 | Almacenamiento seguro y encriptado | Alpha | 4 días | 8 | Media |

**Total Estimado:** 12 días de desarrollo  
**Capacidad Sprint (4 semanas):** 20 días  
**Buffer:** 40% (8 días) - Generoso para refinamiento y tests

---

## 📝 Historias Detalladas

### E0-001: Crear nuevas organizaciones (tenants)

**Como** superadmin,  
**Quiero** crear nuevas organizaciones (tenants),  
**Para** permitir que múltiples empresas usen la plataforma de forma aislada.

**Criterios de Aceptación:**

**AC1: Panel superadmin accesible** ✅
- [ ] Ruta `/admin/tenants` protegida con middleware superadmin
- [ ] Middleware `EnsureSuperadmin` creado
- [ ] Solo usuarios con `role=superadmin` pueden acceder
- [ ] Dashboard con estadísticas:
  - Total tenants
  - Active tenants
  - Trial tenants
  - Suspended tenants
- [ ] Tabla responsive con lista de organizaciones

**AC2: Formulario de alta de tenant** ✅
- [ ] Campos del formulario:
  - **Nombre de organización** (requerido, 3-100 chars)
  - **Slug** (requerido, único, lowercase, 3-50 chars, alfanumérico + guiones)
  - **Email de contacto** (requerido, válido)
  - **Plan** (dropdown: free, basic, pro, enterprise)
  - **Estado** (dropdown: trial, active, suspended)
  - **Límite de usuarios** (numérico, opcional, default según plan)
  - **Límite de documentos/mes** (numérico, opcional)
  - **Fecha de inicio de trial** (date picker, opcional)
  - **Notas internas** (textarea, opcional)
- [ ] Validaciones frontend (Livewire)
- [ ] Validaciones backend (FormRequest)

**AC3: Auto-generación de subdominio** ✅
- [ ] Subdominio generado: `{slug}.firmalum.com`
- [ ] Validación de slug único en BD
- [ ] Slug normalizado: lowercase, sin espacios, guiones solo
- [ ] Preview del subdominio en formulario

**AC4: Creación de usuario admin inicial** ✅
- [ ] Formulario incluye campos de admin inicial:
  - Nombre completo
  - Email
  - Password (auto-generado + envío por email)
- [ ] Usuario creado automáticamente con `role=admin`
- [ ] Email de bienvenida enviado con credenciales
- [ ] Link de activación de cuenta

**AC5: Seed de datos básicos del tenant** ✅
- [ ] RetentionPolicy default creado para el tenant
- [ ] Configuración inicial (settings JSON):
  - Branding básico (logo default, colores)
  - Timezone
  - Locale
  - Email settings
- [ ] Quotas configurados según plan

**AC6: Tabla de tenants optimizada** ✅
- [ ] Migración actualiza `tenants` table con campos:
  - `plan` enum (free, basic, pro, enterprise)
  - `status` enum (trial, active, suspended, cancelled)
  - `settings` JSON
  - `subdomain` string unique
  - `max_users` int nullable
  - `max_documents_per_month` int nullable
  - `trial_ends_at` timestamp nullable
  - `suspended_at` timestamp nullable
  - `suspended_reason` text nullable
  - `admin_notes` text nullable
- [ ] Índices: `status`, `plan`, `subdomain`, `trial_ends_at`

**AC7: Edición y suspensión de tenants** ✅
- [ ] Botón "Edit" en tabla (modal o página)
- [ ] Modificar plan, estado, límites
- [ ] Botón "Suspend" con input de motivo obligatorio
- [ ] Suspensión desactiva acceso de usuarios del tenant
- [ ] Notificación por email al admin del tenant
- [ ] Audit trail completo

**Componentes a crear:**
- Middleware: `EnsureSuperadmin.php`
- Livewire: `Admin/TenantManagement.php`
- Model: Actualizar `Tenant.php` (campos, casts, scopes)
- Migración: `add_plan_and_settings_to_tenants.php`
- Seeder: `SuperadminSeeder.php` (crear primer superadmin)
- Mail: `TenantWelcomeMail.php`, `TenantSuspendedMail.php`
- Views: `livewire/admin/tenant-management.blade.php`
- Tests: 20 tests (Feature + Unit)

**Dependencias técnicas:**
- ✅ E0-004 (Base de datos multi-tenant) - Ya implementado
- ✅ TenantScope ya existe
- ✅ BelongsToTenant trait disponible

**Bloqueos:** Ninguno

---

### E0-002: Gestionar usuarios de organización

**Como** administrador de tenant,  
**Quiero** gestionar usuarios de mi organización,  
**Para** controlar quién accede a mi cuenta y qué permisos tienen.

**Criterios de Aceptación:**

**AC1: Panel de usuarios del tenant** ✅
- [ ] Ruta `/settings/users` accesible por admin del tenant
- [ ] Middleware `EnsureTenantAdmin` (role=admin)
- [ ] Lista paginada de usuarios del tenant (10 por página)
- [ ] Tabla con columnas:
  - Nombre
  - Email
  - Role
  - Status (active, invited, inactive)
  - Last login
  - Acciones (Edit, Deactivate, Delete)
- [ ] Solo usuarios del tenant visible (aislamiento)

**AC2: Roles implementados** ✅
- [ ] Enum `UserRole` actualizado con:
  - **admin**: Acceso total al tenant, gestiona usuarios
  - **operator**: Crea procesos, gestiona documentos, no gestiona usuarios
  - **viewer**: Solo lectura, no puede crear/editar
- [ ] Permisos por role en `Permission` enum:
  - `manage_users` (admin)
  - `create_processes` (admin, operator)
  - `view_processes` (admin, operator, viewer)
  - `manage_documents` (admin, operator)
  - `view_documents` (admin, operator, viewer)
- [ ] Middleware `EnsureUserHasPermission` actualizado
- [ ] Role badges con colores en UI

**AC3: Invitaciones por email** ✅
- [ ] Botón "Invite User" abre modal
- [ ] Formulario de invitación:
  - Email (requerido, válido, no duplicado)
  - Role (dropdown: admin, operator, viewer)
  - Nombre completo (requerido)
  - Mensaje personalizado (opcional)
- [ ] Tabla `user_invitations` creada:
  - `tenant_id`, `email`, `role`, `invited_by`, `token`, `expires_at`, `accepted_at`, `created_at`
- [ ] Token único cryptographically secure (64 chars)
- [ ] Expiración: 7 días
- [ ] Email con link de registro: `/register/invitation/{token}`

**AC4: Aceptación de invitaciones** ✅
- [ ] Ruta pública `/register/invitation/{token}`
- [ ] Validar token válido y no expirado
- [ ] Formulario pre-rellenado con email
- [ ] Crear password (8+ chars, reglas seguridad)
- [ ] Crear usuario con role asignado
- [ ] Marcar invitación como accepted
- [ ] Redirect a dashboard del tenant
- [ ] Email de bienvenida enviado

**AC5: CRUD usuarios existentes** ✅
- [ ] **Editar usuario**:
  - Cambiar role (solo admin puede)
  - Cambiar nombre, email
  - Admin no puede cambiar su propio role a no-admin (protección)
- [ ] **Desactivar usuario**:
  - Status = inactive
  - No puede hacer login
  - No se elimina de BD (audit trail)
  - Puede reactivarse
- [ ] **Eliminar usuario**:
  - Confirmación modal con advertencia
  - Soft delete (deleted_at)
  - No se pueden eliminar usuarios con procesos activos (validación)
  - Admin no puede eliminarse a sí mismo

**AC6: Reenvío de invitaciones** ✅
- [ ] Botón "Resend Invitation" si no aceptada
- [ ] Genera nuevo token
- [ ] Extiende expiración +7 días
- [ ] Email reenviado
- [ ] Máximo 3 reenvíos

**AC7: Audit trail completo** ✅
- [ ] Eventos registrados:
  - `user.invited` (invitador, email, role)
  - `user.invitation_accepted` (email)
  - `user.invitation_resent` (invitador)
  - `user.role_changed` (by, from, to)
  - `user.deactivated` (by, reason)
  - `user.reactivated` (by)
  - `user.deleted` (by)

**Componentes a crear:**
- Migración: `create_user_invitations_table.php`, `add_role_to_users.php`
- Model: `UserInvitation.php`
- Enum: Actualizar `UserRole.php` y `Permission.php`
- Livewire: `Settings/UserManagement.php`
- Controller: `InvitationController.php` (aceptar invitación)
- Mail: `UserInvitationMail.php`, `UserWelcomeMail.php`
- Middleware: `EnsureTenantAdmin.php`
- Views: `livewire/settings/user-management.blade.php`, `invitation/accept.blade.php`
- Tests: 25 tests (Feature + Unit)

**Dependencias:**
- ⚠️ E0-001 (tenants creados) - **BLOQUEANTE**

---

### E2-003: Almacenamiento seguro y encriptado

**Como** sistema,  
**Quiero** almacenar documentos de forma segura y encriptada at-rest,  
**Para** proteger información sensible según GDPR y eIDAS.

**Criterios de Aceptación:**

**AC1: Encriptación AES-256-GCM at-rest** ✅
- [ ] Algoritmo: AES-256-GCM (Galois/Counter Mode)
- [ ] Master key en `.env`: `DOCUMENT_ENCRYPTION_KEY` (64 hex chars)
- [ ] IV (Initialization Vector) único por archivo (12 bytes random)
- [ ] Auth tag generado y verificado (16 bytes)
- [ ] Formato encriptado: `{iv}.{auth_tag}.{ciphertext}` (base64)

**AC2: Clave de encriptación por tenant** ✅
- [ ] Derivación de clave por tenant con HKDF (HMAC-based Key Derivation Function)
- [ ] Formula: `tenant_key = HKDF(master_key, tenant_id, 'ancla-tenant-encryption')`
- [ ] Cada tenant tiene clave única derivada
- [ ] Master key nunca se usa directamente
- [ ] Rotación de master key futura (preparada)

**AC3: Encriptación automática al guardar** ✅
- [ ] Trait `Encryptable` para modelos
- [ ] Aplicar trait a: `Document`, `SignedDocument`, `ArchivedDocument`
- [ ] Atributos encriptados automáticamente:
  - `content` (binario del PDF)
  - `metadata` (JSON sensible)
- [ ] Observer escucha `saving` event
- [ ] Encripta antes de escribir a storage

**AC4: Desencriptación automática al leer** ✅
- [ ] Observer escucha `retrieved` event
- [ ] Desencripta automáticamente al cargar modelo
- [ ] Verifica auth tag (integridad)
- [ ] Exception si integridad comprometida: `EncryptionIntegrityException`
- [ ] Logging de fallos de integridad

**AC5: Servicio de encriptación centralizado** ✅
- [ ] `DocumentEncryptionService.php` con métodos:
  - `encrypt(content, tenant_id)` → encrypted content
  - `decrypt(encrypted_content, tenant_id)` → plain content
  - `generateTenantKey(tenant_id)` → derived key
  - `verifyIntegrity(encrypted_content)` → bool
  - `reEncrypt(encrypted_content, old_tenant_id, new_tenant_id)` → re-encrypted
- [ ] Tests unitarios exhaustivos
- [ ] Benchmark de performance

**AC6: Comando de encriptación de documentos existentes** ✅
- [ ] Comando Artisan: `php artisan documents:encrypt-existing`
- [ ] Opciones:
  - `--dry-run`: Simula sin aplicar
  - `--batch-size=100`: Tamaño de lote
  - `--tenant=<id>`: Solo un tenant
- [ ] Progress bar
- [ ] Verificación de integridad post-encriptación
- [ ] Rollback automático si falla
- [ ] Logging detallado

**AC7: Backup automático diario** ✅
- [ ] Comando Artisan: `php artisan documents:backup`
- [ ] Schedule en `Kernel.php`: daily a las 02:00 AM
- [ ] Backup a S3 o storage redundante
- [ ] Backup incluye:
  - Documentos encriptados
  - Master key en vault separado (AWS Secrets Manager o similar)
  - Metadata de backup (fecha, tenant, count)
- [ ] Retención: 30 días
- [ ] Verificación de integridad post-backup
- [ ] Notificación email si falla

**AC8: Configuración de driver storage** ✅
- [ ] Actualizar `config/filesystems.php`
- [ ] Driver local (dev): `storage/app/documents/`
- [ ] Driver S3 (prod): `s3://ancla-documents-{env}/`
- [ ] Encriptación funciona con ambos drivers
- [ ] Tests con ambos drivers

**AC9: Testing exhaustivo** ✅
- [ ] Unit tests (DocumentEncryptionService): 15 tests mínimo
  - Encrypt/decrypt roundtrip
  - Integridad verificada
  - Tenant isolation
  - Key derivation
  - IV uniqueness
  - Auth tag validation
  - Performance benchmark
- [ ] Feature tests (Encryptable trait): 10 tests
  - Modelo guarda encriptado
  - Modelo carga desencriptado
  - Integridad falla si corrupto
  - Multi-tenant isolation
- [ ] Integration tests: 5 tests
  - Upload → encrypt → download → decrypt
  - Backup → restore → verify

**Componentes a crear:**
- Service: `DocumentEncryptionService.php`
- Trait: `Encryptable.php`
- Observer: `DocumentEncryptionObserver.php`
- Exception: `EncryptionException.php`, `EncryptionIntegrityException.php`
- Config: Actualizar `config/filesystems.php`
- Comando: `EncryptExistingDocumentsCommand.php`, `BackupDocumentsCommand.php`
- Tests: 30 tests (Unit + Feature + Integration)

**Nota de seguridad:**
- Master key en `.env` (servidor seguro)
- Master key en AWS Secrets Manager o Vault en producción
- Keys derivadas nunca se almacenan en BD
- Rotación de master key requiere re-encriptar todos los documentos (comando preparado)
- Audit trail de accesos a documentos encriptados

**Dependencias:**
- ✅ ADR-010 (Estrategia de encriptación) - **COMPLETADO**
- ⚠️ Master key generada por DevOps

**Bloqueos:** Ninguno técnico, requiere coordinación con DevOps

---

## 🗓️ Plan de Implementación (4 semanas)

### Semana 1: Multi-tenant Foundation (E0-001)
**Objetivo:** Panel superadmin + CRUD tenants operativo

**Días 1-2**: Infraestructura
- Día 1: Middleware superadmin + migración tenants
- Día 2: Livewire TenantManagement + formulario alta

**Días 3-4**: Funcionalidades avanzadas
- Día 3: Usuario admin inicial + emails
- Día 4: Edición/suspensión + audit trail

**Día 5**: Tests + documentación
- Unit tests: TenantManagement (10 tests)
- Feature tests: Superadmin panel (10 tests)
- Documentación: Guía superadmin

**Entregable Semana 1:** Panel superadmin funcional, tenants creables ✅

---

### Semana 2: User Management (E0-002)
**Objetivo:** CRUD usuarios + invitaciones + RBAC

**Días 1-2**: RBAC + Panel usuarios
- Día 1: Roles/permissions enum + middleware
- Día 2: Livewire UserManagement + tabla usuarios

**Días 3-4**: Sistema de invitaciones
- Día 3: Tabla invitations + email + token
- Día 4: Ruta pública aceptar invitación + registro

**Día 5**: Tests + integración
- Unit tests: Roles/permissions (8 tests)
- Feature tests: Invitations (12 tests)
- Feature tests: User CRUD (10 tests)
- Documentación: Guía administrador tenant

**Entregable Semana 2:** Gestión usuarios completa, invitaciones funcionando ✅

---

### Semana 3: Encriptación (E2-003)
**Objetivo:** Documentos encriptados at-rest + backup

**Días 1-2**: Servicio de encriptación
- Día 1: DocumentEncryptionService + HKDF + AES-256-GCM
- Día 2: Trait Encryptable + observers

**Días 3-4**: Comandos + backup
- Día 3: Comando encrypt-existing + progress bar
- Día 4: Comando backup + schedule + S3 config

**Día 5**: Tests exhaustivos
- Unit tests: EncryptionService (15 tests)
- Feature tests: Encryptable trait (10 tests)
- Integration tests: End-to-end (5 tests)
- Benchmark de performance

**Entregable Semana 3:** Encriptación at-rest operativa, backup automático ✅

---

### Semana 4: Pulido, Tests, Documentación, Deployment
**Objetivo:** MVP 100% completo, testeado, documentado, desplegable

**Día 1**: Tests de regresión
- Suite completa E2E: Upload → Firma → Descarga
- Tests multi-tenant isolation (críticos)
- Tests seguridad encriptación

**Día 2**: Tests de integración
- Flujo completo superadmin → tenant → usuario → documento
- Tests de performance con volumen
- Tests de backup/restore

**Día 3**: Documentación técnica
- Guía administrador superadmin (crear tenants)
- Guía administrador tenant (gestionar usuarios)
- Guía configuración encriptación
- Guía deployment multi-tenant
- Actualizar README.md

**Día 4**: Preparación deployment
- Variables `.env` documentadas
- Secretos en vault (master key)
- Migración staging ejecutada
- Seed data probado
- Smoke tests en staging

**Día 5**: Sprint Review + Demo + Retrospectiva
- Demo completa stakeholders
- Sprint Review: logros, métricas
- Retrospective: Start/Stop/Continue
- Planificación Sprint 7 (opcional)

**Entregable Semana 4:** MVP 100% COMPLETO, TESTEADO, DOCUMENTADO, DESPLEGABLE ✅

---

## 📊 Matriz de Priorización (ICE Score)

| Feature | Impact | Confidence | Ease | ICE | Prioridad |
|---------|--------|------------|------|-----|-----------|
| E0-001 | 9 | 8 | 7 | 8.0 | P0 |
| E0-002 | 8 | 8 | 7 | 7.7 | P0 |
| E2-003 | 8 | 9 | 6 | 7.7 | P0 |

**Rationale ICE:**
- **E0-001 (8.0)**: Impacto alto (desbloquea modelo SaaS), confianza alta, ease media (requiere UI)
- **E0-002 (7.7)**: Impacto alto (control de acceso), confianza alta, ease media (invitaciones complejas)
- **E2-003 (7.7)**: Impacto alto (seguridad GDPR), confianza muy alta (ADR-010 hecho), ease media (crypto)

---

## ⚠️ Riesgos Identificados

| # | Riesgo | Probabilidad | Impacto | Mitigación |
|---|--------|--------------|---------|------------|
| R1 | Multi-tenant rompe funcionalidad existente | 🟡 MEDIA | 🔴 ALTO | Tests de regresión exhaustivos pre-merge, feature flag si necesario |
| R2 | Encriptación degrada performance | 🟢 BAJA | 🟡 MEDIO | Benchmark día 1, cache agresivo, async processing si necesario |
| R3 | Invitaciones con email delivery falla | 🟡 MEDIA | 🟡 MEDIO | Queue con retry, Mailtrap para testing, SES en prod |
| R4 | Master key compromised en dev | 🟡 MEDIA | 🔴 ALTO | Key en .env.example es dummy, docs claros sobre rotación |
| R5 | Tenant isolation breach (bug crítico) | 🟢 BAJA | 🔴 CRÍTICO | Tests específicos isolation, code review doble por Security Expert |
| R6 | Velocity menor por complejidad | 🟡 MEDIA | 🟡 MEDIO | Buffer 40% incluido, Plan B preparado |

### Plan B (Contingencia)

**Criterio de activación:** Final Semana 2, <50% avance en historias

**Si E0-001 toma más tiempo:**
- **Acción 1**: Simplificar formulario de alta (solo campos básicos)
- **Acción 2**: Suspensión de tenants → Sprint 7
- **Acción 3**: Usuario admin inicial manual (no automático)

**Si E0-002 toma más tiempo:**
- **Acción 1**: Sistema de invitaciones → Sprint 7
- **Acción 2**: Implementar solo CRUD básico de usuarios (sin invitaciones)
- **Acción 3**: Roles simplificados (solo admin y user)

**Si E2-003 toma más tiempo:**
- **Acción 1**: Encriptación solo de documentos nuevos (no existing)
- **Acción 2**: Backup manual (no automático)
- **Acción 3**: Comandos de mantenimiento → Sprint 7

**Decisión final:** Mantener E0-001 y E0-002 completas (críticas SaaS), simplificar E2-003 si necesario

---

## 🎯 Definition of Done (Sprint 6)

Un Sprint 6 está **DONE** cuando:

### Funcionalidad
- [ ] 3 historias implementadas (E0-001, E0-002, E2-003)
- [ ] Demo multi-tenant funcional:
  - Superadmin crea tenant
  - Admin tenant invita usuario
  - Usuario acepta invitación
  - Usuario opera con documentos encriptados
- [ ] Panel admin superadmin operativo
- [ ] Invitaciones de usuarios funcionando
- [ ] Encriptación de documentos activa
- [ ] Backup automático configurado

### Calidad
- [ ] Tests: mínimo 65 nuevos tests (target >268 total)
  - E0-001: 20 tests
  - E0-002: 25 tests
  - E2-003: 30 tests
- [ ] Tests de aislamiento multi-tenant: 10 tests críticos pasando
- [ ] Cobertura: >85%
- [ ] Laravel Pint: 0 issues
- [ ] PHPStan: 0 errores
- [ ] Security audit: 0 HIGH vulnerabilities (E2-003 y tenant isolation)

### Documentación
- [ ] Guía administrador superadmin (crear y gestionar tenants)
- [ ] Guía administrador tenant (gestionar usuarios y roles)
- [ ] Guía configuración encriptación (master key, rotación)
- [ ] Guía deployment multi-tenant (subdominios, variables)
- [ ] README actualizado con instrucciones completas
- [ ] API docs actualizados (si hay endpoints nuevos)

### Seguridad
- [ ] Security Expert aprueba E2-003 (encriptación)
- [ ] Security Expert aprueba tenant isolation tests
- [ ] Master key en vault (no en .env en prod)
- [ ] Tenant isolation verificado (no data leakage)
- [ ] RBAC permissions testeadas exhaustivamente

### Integración
- [ ] Migraciones ejecutadas en staging sin errores
- [ ] Seed data funciona (superadmin, tenants, users)
- [ ] Email delivery probado (invitations, welcome)
- [ ] Encriptación probada con volumen (1000+ docs)
- [ ] Backup/restore probado end-to-end

### Performance
- [ ] Encriptación/desencriptación <100ms per document
- [ ] Backup completo <10 minutos (1000 docs)
- [ ] Dashboard superadmin carga <2s
- [ ] Queries multi-tenant optimizadas (N+1 prevención)

### Code Review
- [ ] Tech Lead aprueba todos los PRs
- [ ] Security Expert revisa E2-003 (encriptación) - MANDATORY
- [ ] Arquitecto valida estructura multi-tenant
- [ ] No deuda técnica crítica introducida

### Despliegue
- [ ] Branch `sprint6` mergeado a `develop`
- [ ] Staging desplegado y probado
- [ ] Variables `.env` documentadas completamente
- [ ] Secretos en vault configurados (master key)
- [ ] Backup strategy probada y documentada
- [ ] Rollback plan documentado

---

## 📞 Ceremonias Sprint 6

### Daily Standup (15 min)
- **Frecuencia**: Todos los días laborables (lunes a viernes)
- **Hora**: 10:00 AM (Europe/Madrid)
- **Foco días 1-5**: Avance E0-001 (panel superadmin)
- **Foco días 6-10**: Avance E0-002 (user management)
- **Foco días 11-15**: Avance E2-003 (encriptación)
- **Foco días 16-20**: Tests, documentación, deployment prep

### Sprint Planning (2 horas)
- **Fecha**: Día 1 del Sprint 6 (2025-12-30)
- **Agenda**:
  - Review Sprint Goal
  - Detalle de historias E0-001, E0-002, E2-003
  - Estimación y asignación
  - Identificación de riesgos
  - Plan de mitigación
  - Definición de Definition of Done

### Mid-Sprint Review (30 min)
- **Fecha**: Final Semana 2 (día 10)
- **Checkpoint**: 50% avance esperado
  - E0-001 completada ✅
  - E0-002 al 50% (CRUD básico listo, invitaciones en progreso)
- **Decisión**: Activar Plan B si <50% avance

### Sprint Review/Demo (1 hora)
- **Fecha**: Último día del Sprint 6 (día 20)
- **Demo completa**:
  1. Superadmin crea tenant "Acme Corp"
  2. Admin inicial de Acme recibe email de bienvenida
  3. Admin Acme invita operador "John Doe"
  4. John acepta invitación y crea cuenta
  5. John sube documento (encriptado at-rest)
  6. John crea proceso de firma
  7. Firmante completa firma
  8. John descarga documento final (desencriptado)
  9. Mostrar en BD: documento encriptado
  10. Mostrar backup automático funcionando
- **Métricas**: Tests, cobertura, performance

### Retrospective (1 hora)
- **Fecha**: Último día del Sprint 6
- **Formato**: Start/Stop/Continue
- **Foco**:
  - Lecciones de implementación multi-tenant
  - Eficacia de tests de aislamiento
  - Performance de encriptación
  - Preparación para Sprint 7 (opcional)

---

## 🚀 Entregable Final Sprint 6

Al completar el Sprint 6, Firmalum será:

### ✅ Producto 100% completo
- **28/28 historias del backlog original implementadas**
- Flujo end-to-end completamente funcional
- Todas las épicas E0, E1, E2, E3, E4, E5 cerradas
- MVP listo para producción

### ✅ SaaS Multi-tenant operativo
- Panel de administración superadmin profesional
- Creación y gestión de múltiples organizaciones
- Subdominios automáticos por tenant
- Aislamiento completo de datos entre tenants
- Gestión completa de usuarios por organización
- Sistema de invitaciones con roles (admin, operator, viewer)
- RBAC granular implementado

### ✅ Seguridad enterprise
- Encriptación AES-256-GCM at-rest de documentos
- Master key en vault seguro
- Derivación de claves por tenant (HKDF)
- Backup automático diario configurado
- Verificación de integridad automática
- Tenant isolation garantizado (testeado)
- Audit trail completo de accesos

### ✅ Listo para clientes reales
- Onboarding de tenants en <5 minutos
- Invitaciones de usuarios automáticas
- Soporte multi-empresa con aislamiento total
- Documentación completa de administración
- Guías de deployment documentadas
- Performance optimizada (<100ms encriptación)

### ✅ Calidad asegurada
- >268 tests totales (65+ nuevos en Sprint 6)
- Cobertura >85%
- Security audit completo
- Code review aprobado por Tech Lead + Security Expert
- Laravel Pint: 0 issues
- PHPStan: 0 errores

**Milestone alcanzado:** 🎯 **MVP 100% COMPLETO** → Listo para lanzamiento comercial y onboarding de clientes reales

---

## 📈 Métricas de Éxito

### Funcionales
- **Velocity target**: 3/3 tareas completadas (100%)
- **MVP completion**: 28/28 historias (100%)
- **Demo success**: Flujo completo superadmin → tenant → user → document funcional

### Calidad
- **Tests target**: >268 tests totales (203 actuales + 65 nuevos)
- **Cobertura target**: >85%
- **Security audit**: 0 HIGH, 0 MEDIUM vulnerabilities
- **Code quality**: Laravel Pint 0 issues, PHPStan Level 5 pass

### Performance
- **Encriptación**: <100ms per document
- **Backup**: <10 min para 1000 docs
- **Dashboard load**: <2s
- **Tenant isolation**: 0 data leakage bugs

### Negocio
- **Time to onboard tenant**: <5 minutos
- **Time to invite user**: <1 minuto
- **User activation rate**: >80% (invitations accepted)
- **System uptime**: 99.9% target

---

## 🔄 Próximos Pasos Post-Sprint 6

### Sprint 7 (Opcional - Mejoras no-MVP)
Si se decide continuar desarrollo post-MVP:
- E2-002: Definir zonas de firma (postponed Sprint 4)
- E3-007: Reenviar recordatorios a firmantes
- E4-002: Enviar solicitudes por SMS
- E5-004: Acceso histórico a documentos (búsqueda avanzada)
- E6-001: Personalizar logo y colores (white-label)
- E6-002: Dominio personalizado
- E6-003: Personalizar plantillas email

### Lanzamiento Comercial
- Marketing: Preparar landing page y materiales
- Sales: Estrategia de onboarding de primeros clientes
- Support: Documentación de usuario final
- DevOps: Monitorización y alertas en producción

### Roadmap Futuro
- Integraciones: API REST pública, webhooks
- White-label avanzado: Custom domains, branding completo
- Mobile apps: iOS y Android nativas
- Advanced features: Workflows, templates, bulk operations

---

## 📋 Checklist de Inicio de Sprint

Antes de comenzar el desarrollo, verificar:

**Product Owner:**
- [x] Sprint 6 plan documentado ✅
- [x] Historias detalladas con AC completos ✅
- [ ] Sprint Goal comunicado a stakeholders
- [ ] Prioridades claras: E0-001 → E0-002 → E2-003

**Arquitecto:**
- [x] ADR-010 (Encriptación) completado ✅
- [ ] Revisar diseño multi-tenant (validar con E0-004 existente)
- [ ] Validar estrategia HKDF para derivación de keys

**Developer:**
- [ ] Branch `sprint6` creado desde `develop`
- [ ] Entorno local actualizado (composer install, npm install)
- [ ] Migraciones Sprint 5 ejecutadas localmente
- [ ] .env configurado con variables necesarias

**DevOps:**
- [ ] Master key de encriptación generada (dummy para dev)
- [ ] AWS Secrets Manager o Vault preparado (prod)
- [ ] S3 bucket para backup configurado (staging + prod)
- [ ] Ambiente staging preparado para multi-tenant

**Security Expert:**
- [ ] Plan de auditoría E2-003 (encriptación) preparado
- [ ] Plan de tests tenant isolation preparado
- [ ] Checklist de security review documentado

**Tech Lead:**
- [ ] Code review guidelines comunicados
- [ ] Tests de regresión identificados
- [ ] Performance benchmarks definidos (encriptación)

---

## 📚 Recursos y Referencias

### Documentación Técnica
- [ADR-010: Estrategia de Encriptación at-Rest](../architecture/adr-010-encryption-at-rest.md)
- [Kanban Board](../kanban.md)
- [Product Backlog](../backlog.md)
- [Sprint 5 Plan](sprint5-plan.md)

### Guías de Implementación
- Laravel Multi-tenancy: [Spatie Multi-tenancy Package](https://spatie.be/docs/laravel-multitenancy)
- AES-256-GCM PHP: [OpenSSL Documentation](https://www.php.net/manual/en/function.openssl-encrypt.php)
- HKDF PHP: [Hash HKDF Function](https://www.php.net/manual/en/function.hash-hkdf.php)

### Compliance
- GDPR Art. 32: Security of processing
- eIDAS Regulation: Multi-tenant requirements
- ISO 27001: Encryption standards

---

*Próximo paso: Comunicar Sprint Goal a stakeholders y comenzar desarrollo E0-001*  
*Fecha: 2025-12-30*  
*Product Owner: Firmalum Team*  
*Milestone: MVP 100% COMPLETO*
