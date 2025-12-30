# Sprint 5 Plan - Cierre de Flujo + Multi-tenant

> 📅 **Fecha**: 2025-12-30  
> 🎯 **Sprint Goal**: "Cerrar el ciclo completo del documento firmado y habilitar operación multi-tenant"  
> 🚀 **Milestone**: PRODUCTO COMPLETO END-TO-END + MULTI-TENANT (100% MVP)

---

## 📊 Contexto

### Sprint 4 - Resultados
- ✅ 7/7 tareas completadas (100%)
- ✅ MVP Funcional alcanzado (95% - 20/21 tareas)
- ✅ 132 tests implementados
- ✅ Flujo de firma end-to-end operativo

### Gap Actual
El sistema puede firmar documentos pero:
- ❌ No genera el PDF final firmado con todas las evidencias
- ❌ No entrega copias a firmantes
- ❌ No permite descargar documento + dossier
- ❌ No soporta múltiples organizaciones
- ❌ No hay gestión de usuarios por tenant

### Valor de Sprint 5
Este sprint cierra el ciclo de vida completo y habilita el modelo de negocio SaaS multi-tenant.

---

## 🎯 Sprint Goal Detallado

**Objetivo Principal:**  
Completar el flujo end-to-end desde upload hasta entrega del documento firmado, y habilitar la operación multi-tenant para escalar el negocio.

**Entregables:**
1. PDF final con firmas visibles y evidencias embebidas
2. Email automático a firmantes con su copia
3. Dashboard de descarga para promotor (PDF + dossier)
4. Panel de administración multi-tenant
5. Gestión completa de usuarios por organización

**Resultado esperado:**  
Producto 100% funcional, listo para onboarding de clientes reales.

---

## 📋 Historias Seleccionadas

### MUST (Prioridad 0 - Bloqueantes)

| ID | Historia | Squad | Estimación | Valor | Complejidad |
|----|----------|-------|------------|-------|-------------|
| E5-001 | Generar documento final firmado | Alpha | 3 días | 10 | Alta |
| E5-002 | Enviar copia a firmantes | Beta | 2 días | 9 | Media |
| E5-003 | Descargar documento y dossier | Beta | 2 días | 9 | Baja |
| E0-001 | Crear nuevas organizaciones | Alpha | 3 días | 9 | Media |
| E0-002 | Gestionar usuarios de organización | Alpha | 2 días | 8 | Media |

### SHOULD (Prioridad 1 - Importantes)

| ID | Historia | Squad | Estimación | Valor | Complejidad |
|----|----------|-------|------------|-------|-------------|
| E2-003 | Almacenamiento seguro y encriptado | Alpha | 2 días | 8 | Media |
| E3-006 | Cancelar proceso de firma | Beta | 1 día | 6 | Baja |

**Total Estimado:** 15 días de desarrollo  
**Capacidad Sprint (4 semanas):** 20 días  
**Buffer:** 25% (5 días)

---

## 📝 Historias Detalladas

### E5-001: Generar documento final firmado

**Como** sistema,  
**Quiero** generar el documento final firmado con todas las evidencias,  
**Para** entregar a las partes un PDF legalmente válido.

**Criterios de Aceptación:**
- [ ] PDF con todas las firmas visibles aplicadas
- [ ] Metadata de evidencias embebida en el PDF
- [ ] Página de certificación anexa al final del documento
- [ ] QR code de verificación en cada firma
- [ ] Verificable con herramienta pública (E1-009)
- [ ] Almacenado en `storage/final/{tenant}/{year}/{month}/`
- [ ] Hash SHA-256 del documento final
- [ ] TSA timestamp del momento de generación
- [ ] Registro en tabla `signed_documents` actualizado con `status=completed`

**Componentes a crear/modificar:**
- Servicio: `FinalDocumentService.php` (nuevo)
- Job: `GenerateFinalDocumentJob.php` (queue)
- Comando: `php artisan documents:generate-final {process_id}`
- Migración: Añadir campos a `signed_documents` (final_path, final_hash, completed_at)
- Tests: Unit + Feature (15 tests mínimo)

**Dependencias técnicas:**
- ✅ E3-004 (PdfSignatureService) - Firmas PAdES disponibles
- ✅ E1-007 (EvidenceDossierService) - Evidencias exportables
- ✅ Librería FPDI/TCPDF para manipular PDF

**Bloqueos:** Ninguno (desbloqueado)

---

### E5-002: Enviar copia a firmantes

**Como** firmante,  
**Quiero** recibir automáticamente una copia del documento firmado,  
**Para** mis registros personales.

**Criterios de Aceptación:**
- [ ] Email automático al completar proceso
- [ ] Enlace de descarga seguro (token único)
- [ ] Enlace expira en 30 días
- [ ] Email con plantilla profesional
- [ ] Opción de envío por SMS (enlace corto) - opcional
- [ ] Tracking de descarga del firmante
- [ ] Audit trail: `signer.document_delivered`

**Componentes a crear:**
- Mail: `SignedDocumentDeliveryMail.php`
- Job: `DeliverSignedDocumentJob.php`
- Controlador: `PublicDownloadController.php` (ruta pública con token)
- Vista: `emails/signed-document-delivery.blade.php`
- Migración: `delivery_tokens` table
- Tests: 12 tests mínimo

**Dependencias:**
- ⚠️ E5-001 (documento final generado)

---

### E5-003: Descargar documento y dossier

**Como** promotor,  
**Quiero** descargar el documento firmado y el dossier de evidencias,  
**Para** mis archivos legales.

**Criterios de Aceptación:**
- [ ] Botón "Download Signed PDF" en dashboard
- [ ] Botón "Download Evidence Dossier"
- [ ] Botón "Download ZIP Bundle" (ambos)
- [ ] Solo disponible cuando proceso = `completed`
- [ ] Descarga directa sin redirects
- [ ] Headers correctos (Content-Disposition, MIME type)
- [ ] Audit trail: `process.downloaded`
- [ ] Nombre archivo: `{document_name}_signed_{date}.pdf`

**Componentes a crear:**
- Método Livewire: `downloadSignedDocument()`, `downloadDossier()`, `downloadBundle()`
- Servicio: `DocumentDownloadService.php`
- Tests: 10 tests

**Dependencias:**
- ⚠️ E5-001 (documento final)

---

### E0-001: Crear nuevas organizaciones (tenants)

**Como** superadmin,  
**Quiero** crear nuevas organizaciones (tenants),  
**Para** permitir que múltiples empresas usen la plataforma.

**Criterios de Aceptación:**
- [ ] Panel superadmin en `/admin/tenants`
- [ ] Formulario de alta con campos:
  - Nombre de organización
  - Dominio/slug (único)
  - Email de contacto
  - Plan (free, basic, pro, enterprise)
  - Estado (active, suspended, trial)
- [ ] Validación de slug único (lowercase, alfanumérico, guiones)
- [ ] Auto-generación de subdominio: `{slug}.firmalum.com`
- [ ] Creación de usuario admin inicial
- [ ] Seed de datos básicos (RetentionPolicy default del tenant)
- [ ] Middleware SuperadminOnly
- [ ] Tabla de tenants con índices optimizados

**Componentes a crear:**
- Modelo: `Tenant.php` ya existe, expandir
- Controlador: `Admin/TenantController.php`
- Livewire: `Admin/TenantManagement.php`
- Migración: Añadir campos a `tenants` (plan, status, settings JSON)
- Seeder: `TenantSeeder.php`
- Middleware: `EnsureSuperadmin.php`
- Tests: 18 tests

**Nota de seguridad:**
- Aislamiento total de datos entre tenants
- Validación estricta de permisos superadmin

---

### E0-002: Gestionar usuarios de organización

**Como** administrador de tenant,  
**Quiero** gestionar usuarios de mi organización,  
**Para** controlar quién accede a mi cuenta.

**Criterios de Aceptación:**
- [ ] Panel en `/settings/users`
- [ ] CRUD completo de usuarios:
  - Listar usuarios del tenant
  - Invitar por email
  - Editar roles
  - Desactivar/reactivar
  - Eliminar
- [ ] Roles implementados:
  - `admin` - Acceso total al tenant
  - `operator` - Crear procesos, gestionar documentos
  - `viewer` - Solo lectura
- [ ] Invitaciones por email con token de registro
- [ ] Expiración de invitaciones (7 días)
- [ ] Usuario solo ve usuarios de su tenant
- [ ] Audit trail completo

**Componentes a crear:**
- Enum: `UserRole` (actualizar con permisos detallados)
- Modelo: `UserInvitation.php`
- Mail: `UserInvitationMail.php`
- Livewire: `Settings/UserManagement.php`
- Migración: `user_invitations` table
- Middleware: Role-based authorization
- Tests: 20 tests

**Dependencias:**
- ⚠️ E0-001 (tenants creados)

---

### E2-003: Almacenamiento seguro y encriptado

**Como** sistema,  
**Quiero** almacenar documentos de forma segura y encriptada,  
**Para** proteger información sensible.

**Criterios de Aceptación:**
- [ ] Encriptación at-rest con AES-256-GCM
- [ ] Clave de encriptación por tenant (derivada de master key)
- [ ] Encriptación automática al guardar documento
- [ ] Desencriptación automática al leer documento
- [ ] Encriptación in-transit ya garantizada por TLS 1.3
- [ ] Backup automático diario de storage
- [ ] Configuración de driver storage (local/S3)
- [ ] Testing de encriptación/desencriptación

**Componentes a crear:**
- Servicio: `DocumentEncryptionService.php`
- Trait: `Encryptable.php` para modelos
- Config: Actualizar `config/filesystems.php`
- Comando: `documents:encrypt-existing`
- Tests: 12 tests

**Nota de seguridad:**
- Master key en `.env` (DOCUMENT_ENCRYPTION_KEY)
- Keys derivadas con HKDF por tenant
- Rotación de claves futura

---

### E3-006: Cancelar proceso de firma

**Como** promotor,  
**Quiero** cancelar un proceso de firma,  
**Para** anular documentos no deseados.

**Criterios de Aceptación:**
- [ ] Botón "Cancel Process" en dashboard
- [ ] Modal de confirmación con input de motivo (obligatorio)
- [ ] Solo disponible si `status != completed`
- [ ] Cambio de estado a `cancelled`
- [ ] Notificación por email a firmantes pendientes
- [ ] Audit trail: `process.cancelled` con motivo
- [ ] Proceso irreversible (no se puede reactivar)
- [ ] Links de firma se invalidan automáticamente

**Componentes a crear:**
- Método Livewire: `cancelProcess(reason)`
- Servicio: `SigningProcessCancellationService.php`
- Mail: `ProcessCancelledNotificationMail.php`
- Tests: 10 tests

---

## 🗓️ Plan de Implementación (4 semanas)

### Semana 1: Documento Final + Entrega
**Objetivo:** Completar el ciclo de documento firmado

- **Días 1-3**: E5-001 (Generar documento final)
  - Día 1: `FinalDocumentService` + migración
  - Día 2: Generación PDF con firmas + certificación
  - Día 3: Job + tests
  
- **Días 4-5**: E5-002 (Enviar copia a firmantes)
  - Día 4: Mail + token system
  - Día 5: Controlador público + tests

**Entregable Semana 1:** Documento final generado y entregado ✅

---

### Semana 2: Descarga + Multi-tenant Foundation
**Objetivo:** Descargas + infraestructura tenants

- **Días 1-2**: E5-003 (Descargar documento y dossier)
  - Día 1: Botones UI + métodos descarga
  - Día 2: ZIP bundle + tests
  
- **Días 3-5**: E0-001 (Crear organizaciones)
  - Día 3: Panel admin + formulario
  - Día 4: Validaciones + seed
  - Día 5: Tests + documentación

**Entregable Semana 2:** Descargas funcionales + Panel admin tenants ✅

---

### Semana 3: Gestión Usuarios + Encriptación
**Objetivo:** RBAC + Seguridad storage

- **Días 1-2**: E0-002 (Gestionar usuarios)
  - Día 1: CRUD usuarios + roles
  - Día 2: Invitaciones + tests
  
- **Días 3-4**: E2-003 (Almacenamiento encriptado)
  - Día 3: `DocumentEncryptionService` + trait
  - Día 4: Tests + migración documentos existentes

- **Día 5**: Buffer para deuda técnica

**Entregable Semana 3:** Multi-tenant completo + Encriptación ✅

---

### Semana 4: Cancelación + Tests + Documentación
**Objetivo:** Pulido + QA + Demo

- **Día 1**: E3-006 (Cancelar proceso)
- **Días 2-3**: Suite completa de tests E2E
  - Test flujo completo: upload → firma → descarga
  - Test multi-tenant isolation
  - Test seguridad encriptación
- **Día 4**: Documentación técnica
  - Guía administrador multi-tenant
  - Guía configuración encriptación
  - Actualizar README
- **Día 5**: Demo + Sprint Review

**Entregable Semana 4:** Producto 100% completo + Documentación ✅

---

## 📊 Matriz de Priorización (ICE Score)

| Feature | Impact | Confidence | Ease | ICE | Prioridad |
|---------|--------|------------|------|-----|-----------|
| E5-001 | 10 | 9 | 6 | 8.3 | P0 |
| E5-002 | 9 | 9 | 8 | 8.7 | P0 |
| E5-003 | 9 | 10 | 9 | 9.3 | P0 |
| E0-001 | 9 | 8 | 7 | 8.0 | P0 |
| E0-002 | 8 | 8 | 7 | 7.7 | P0 |
| E2-003 | 8 | 9 | 6 | 7.7 | P1 |
| E3-006 | 6 | 9 | 8 | 7.7 | P1 |

---

## ⚠️ Riesgos Identificados

| # | Riesgo | Probabilidad | Impacto | Mitigación |
|---|--------|--------------|---------|------------|
| R1 | E5-001 más complejo de lo estimado | 🟡 MEDIA | 🔴 ALTO | POC con FPDI día 1, ajustar alcance si necesario |
| R2 | Multi-tenant rompe funcionalidad existente | 🟡 MEDIA | 🔴 ALTO | Tests de regresión exhaustivos, feature flag |
| R3 | Encriptación degrada performance | 🟢 BAJA | 🟡 MEDIO | Benchmark antes/después, cache agresivo |
| R4 | Email delivery falla en producción | 🟡 MEDIA | 🟡 MEDIO | Queue con retry, Mailtrap testing |
| R5 | Velocity menor por complejidad | 🟡 MEDIA | 🔴 ALTO | Plan B: E2-003 y E3-006 → Sprint 6 |

### Plan B (Contingencia)

Si llegamos al final de Semana 2 con E5-001/002/003 incompletas:
- **Acción 1**: Mover E2-003 y E3-006 a Sprint 6
- **Acción 2**: Foco 100% en cerrar E5-xxx
- **Acción 3**: E0-001/002 simplificadas (CRUD básico, sin invitaciones)

**Criterio de activación:** Final Semana 2, <60% avance

---

## 🎯 Definition of Done (Sprint 5)

Un Sprint 5 está **DONE** cuando:

### Funcionalidad
- [ ] 7 historias implementadas (5 MUST + 2 SHOULD)
- [ ] Demo E2E funcional: upload → firma → descarga completa
- [ ] Panel admin multi-tenant operativo
- [ ] Invitaciones de usuarios funcionando
- [ ] Encriptación de documentos activa

### Calidad
- [ ] Tests: mínimo 80 tests nuevos (target >210 total)
- [ ] Cobertura: >85%
- [ ] Laravel Pint: 0 issues
- [ ] PHPStan: 0 errores
- [ ] Security audit: 0 HIGH vulnerabilities

### Documentación
- [ ] Guía administrador multi-tenant
- [ ] Guía configuración encriptación
- [ ] README actualizado con instrucciones superadmin
- [ ] API docs (si hay endpoints nuevos)

### Integración
- [ ] Migración ejecutada en staging
- [ ] Seed data funciona (tenants + usuarios)
- [ ] Email delivery probado
- [ ] Encriptación probada con volumen

### Code Review
- [ ] Tech Lead aprueba PRs
- [ ] Security Expert revisa E2-003 (encriptación)
- [ ] No deuda técnica crítica

### Despliegue
- [ ] Branch `sprint5` → `develop`
- [ ] Staging desplegado
- [ ] Variables `.env` documentadas
- [ ] Backup strategy probada

---

## 📞 Ceremonias Sprint 5

### Daily Standup (15 min)
- **Frecuencia**: Todos los días laborables
- **Foco**: Avance E5-001 (crítica)

### Sprint Planning (2 horas)
- **Fecha**: Primer día del Sprint 5
- **Agenda**: Sprint Goal, historias, estimación, asignación, riesgos

### Mid-Sprint Review (30 min)
- **Fecha**: Final Semana 2
- **Checkpoint**: 50% avance (E5-xxx completas, E0-001 en progreso)

### Sprint Review/Demo (1 hora)
- **Fecha**: Último día del Sprint 5
- **Demo**: Flujo completo + panel admin multi-tenant

### Retrospective (1 hora)
- **Formato**: Start/Stop/Continue
- **Foco**: Lecciones de multi-tenant

---

## 🚀 Entregable Final Sprint 5

Al completar el Sprint 5, Firmalum será:

✅ **Producto completo end-to-end**
- Upload → Firma → Descarga funcionando
- Documento final con evidencias embebidas
- Entrega automática a firmantes

✅ **SaaS Multi-tenant operativo**
- Panel de administración de organizaciones
- Gestión completa de usuarios por tenant
- Aislamiento de datos garantizado

✅ **Seguridad enterprise**
- Encriptación AES-256 de documentos
- RBAC implementado
- Audit trail completo

✅ **Listo para clientes reales**
- Onboarding de tenants
- Invitaciones de usuarios
- Soporte multi-empresa

**Milestone alcanzado:** 🎯 **100% MVP** → Lanzamiento comercial

---

## 📈 Métricas de Éxito

- **Velocity target**: 7/7 tareas completadas
- **Tests target**: >210 tests totales
- **Cobertura target**: >85%
- **Performance**: Upload → Descarga <30s
- **Tiempo onboarding**: Nuevo tenant en <5 min

---

*Próximo paso: Solicitar ADR al Arquitecto si hay decisiones técnicas complejas*  
*Fecha: 2025-12-30*  
*Product Owner: Firmalum Team*
