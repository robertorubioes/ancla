# Product Backlog - ANCLA

> 📅 **Última actualización**: 2025-12-30 (Sprint 6 COMPLETADO ✅)
> 🎯 **MVP Status**: 100% COMPLETO (28/28 historias) 🎉
> 📊 **Sprint Actual**: Sprint 6 - Multi-tenant Foundation

## Visión del Producto

**ANCLA** es una plataforma SaaS de firma electrónica avanzada conforme al Reglamento eIDAS, diseñada para operar como **marca blanca (multi-tenant)**. Su diferencial competitivo es la **generación, conservación y exportación de evidencias legales incontestables**, capaces de defenderse ante cualquier auditoría o procedimiento judicial.

### Propuesta de Valor
- **Para empresas** que necesitan enviar documentos a firmar de forma legal y segura
- **ANCLA proporciona** una plataforma de firma electrónica avanzada con trazabilidad completa
- **Que se diferencia** por su sistema de evidencias legales blindado y verificación pública única
- **Permitiendo** operar bajo marca propia (white-label) con total personalización

### Ventajas Competitivas Únicas

1. ✨ **Verificación Pública Abierta** (Único en el mercado)
   - Cualquiera puede verificar autenticidad sin registro
   - Competencia: Requiere login o pago

2. ✨ **Dossier Probatorio Exportable** (Único en el mercado)
   - PDF completo con evidencias para procedimientos judiciales
   - Competencia: Solo audit trail interno

3. ✨ **eIDAS Compliance desde Free Tier**
   - PAdES-B-LT desde $15/mes
   - Competencia: Solo en tiers Enterprise ($100+/mes)

4. ✨ **Multi-tenant White-Label desde MVP**
   - SaaS-ready desde día 1
   - Competencia: Solo Enterprise tier

---

## Épicas (Epics)

| Epic ID | Nombre | Prioridad | Descripción |
|---------|--------|-----------|-------------|
| E0 | Infraestructura Multi-tenant | Alta | Arquitectura base para soportar múltiples organizaciones |
| E1 | **Sistema de Evidencias** | **CRÍTICA** | Generación, conservación y exportación de pruebas legales |
| E2 | Gestión de Documentos | Alta | Upload, almacenamiento y versionado de PDFs |
| E3 | Proceso de Firma | Alta | Flujo completo de firma electrónica avanzada |
| E4 | Notificaciones | Media | Envío de solicitudes por email y SMS |
| E5 | Entrega de Copias | Media | Distribución automática de documentos firmados |
| E6 | Marca Blanca | Media | Personalización visual y funcional por tenant |

---

## Historias de Usuario por Epic

### EPIC E0: Infraestructura Multi-tenant

| ID | Historia de Usuario | Prioridad | Criterios de Aceptación | Squad |
|----|---------------------|-----------|-------------------------|-------|
| E0-001 | Como **superadmin**, quiero crear nuevas organizaciones (tenants), para permitir que múltiples empresas usen la plataforma | Alta | - Formulario de alta de organización<br>- Generación de subdominio o dominio personalizado<br>- Aislamiento de datos por tenant | Alpha |
| E0-002 | Como **administrador de tenant**, quiero gestionar usuarios de mi organización, para controlar quién accede a mi cuenta | Alta | - CRUD de usuarios por tenant<br>- Roles: admin, operador, visor<br>- Invitaciones por email | Alpha |
| E0-003 | Como **usuario**, quiero autenticarme de forma segura, para acceder a mis documentos | Alta | - Login con email/contraseña<br>- 2FA opcional<br>- Recuperación de contraseña<br>- Sesiones seguras | Alpha |
| E0-004 | Como **desarrollador**, quiero una base de datos con aislamiento por tenant, para garantizar la seguridad de los datos | Alta | - Columna `tenant_id` en todas las tablas<br>- Scopes automáticos en queries<br>- Middleware de tenant | Alpha |

---

### EPIC E1: Sistema de Evidencias (PRIORIDAD ABSOLUTA)

> ⚠️ **CRÍTICO**: Este es el núcleo diferenciador del producto. Cada evidencia debe ser legalmente válida y verificable ante un tribunal.

| ID | Historia de Usuario | Prioridad | Criterios de Aceptación | Squad |
|----|---------------------|-----------|-------------------------|-------|
| E1-001 | Como **sistema**, quiero capturar timestamp fiable de cada evento, para probar cuándo ocurrió cada acción | **CRÍTICA** | - Integración con TSA (Time Stamping Authority)<br>- Soporte híbrido (Standard/Qualified)<br>- RFC 3161 compliant<br>- Almacenar token TSA con cada evento | Alpha |
| E1-002 | Como **sistema**, quiero generar hash SHA-256 de cada documento, para probar su integridad | **CRÍTICA** | - Hash generado al subir documento<br>- Hash verificable en cualquier momento<br>- Detección de alteraciones | Alpha |
| E1-003 | Como **sistema**, quiero capturar la huella digital del dispositivo del firmante, para identificar desde dónde firmó | **CRÍTICA** | - User-Agent completo<br>- Resolución de pantalla<br>- Sistema operativo<br>- Navegador y versión<br>- Fingerprint único | Alpha |
| E1-004 | Como **sistema**, quiero capturar la geolocalización del firmante (con consentimiento), para probar desde dónde firmó | **CRÍTICA** | - Solicitud de permiso de ubicación<br>- Almacenar latitud/longitud<br>- Precisión del dato<br>- Alternativa: IP geolocation si rechaza GPS | Alpha |
| E1-005 | Como **sistema**, quiero registrar la IP del firmante con resolución inversa, para trazabilidad de red | **CRÍTICA** | - Captura de IP pública<br>- Resolución DNS inversa<br>- Detección de VPN/proxy (informativo) | Alpha |
| E1-006 | Como **sistema**, quiero generar un trail de auditoría inmutable, para cada proceso de firma | **CRÍTICA** | - Log de cada evento: creación, envío, apertura, firma, descarga<br>- Cada entrada con timestamp TSA<br>- Hash encadenado (blockchain-like)<br>- Imposible modificar sin detectar | Alpha |
| E1-007 | Como **promotor**, quiero exportar un dossier probatorio en PDF, para usar como evidencia legal | **CRÍTICA** | - PDF con todos los eventos<br>- Hashes verificables<br>- Tokens TSA embebidos<br>- Datos de dispositivos/IPs<br>- Firma del dossier con sello de la plataforma | Alpha |
| E1-008 | Como **sistema**, quiero conservar las evidencias por mínimo 5 años, para cumplir con requisitos legales | **CRÍTICA** | - Almacenamiento redundante<br>- Migración de formatos si es necesario<br>- Re-sellado TSA antes de expiración<br>- Política de retención configurable | Alpha |
| E1-009 | Como **auditor**, quiero verificar la integridad de cualquier documento firmado, para validar su autenticidad | **CRÍTICA** | - Herramienta de verificación pública<br>- Validar hashes<br>- Validar tokens TSA<br>- Validar cadena de evidencias | Alpha |
| E1-010 | Como **sistema**, quiero capturar evidencia del consentimiento explícito del firmante, para probar que aceptó firmar | **CRÍTICA** | - Checkbox de aceptación obligatorio<br>- Texto legal visible<br>- Timestamp del click de aceptación<br>- Screenshot o captura del momento | Alpha |

---

### EPIC E2: Gestión de Documentos

| ID | Historia de Usuario | Prioridad | Criterios de Aceptación | Squad |
|----|---------------------|-----------|-------------------------|-------|
| E2-001 | Como **promotor**, quiero subir documentos PDF, para enviarlos a firmar | Alta | - Drag & drop de archivos<br>- Validación de formato PDF<br>- Límite de tamaño configurable (default 25MB)<br>- Preview del documento | Beta |
| E2-002 | Como **promotor**, quiero definir zonas de firma en el documento, para indicar dónde debe firmar cada persona | Alta | - Editor visual de posicionamiento<br>- Múltiples zonas por documento<br>- Asignación de zona a firmante<br>- Campos opcionales: fecha, texto | Beta |
| E2-003 | Como **sistema**, quiero almacenar documentos de forma segura y encriptada, para proteger información sensible | Alta | - Encriptación at-rest (AES-256)<br>- Encriptación in-transit (TLS 1.3)<br>- Almacenamiento en storage seguro<br>- Backup automático | Alpha |
| E2-004 | Como **promotor**, quiero organizar documentos en carpetas, para mantener orden en mi cuenta | Media | - Crear/editar/eliminar carpetas<br>- Mover documentos entre carpetas<br>- Búsqueda global | Beta |
| E2-005 | Como **promotor**, quiero usar plantillas de documentos, para agilizar procesos repetitivos | Media | - Guardar documento como plantilla<br>- Campos variables (placeholders)<br>- Reutilizar plantillas | Beta |

---

### EPIC E3: Proceso de Firma

| ID | Historia de Usuario | Prioridad | Criterios de Aceptación | Squad |
|----|---------------------|-----------|-------------------------|-------|
| E3-001 | Como **promotor**, quiero crear un proceso de firma con uno o varios firmantes, para obtener sus firmas | Alta | - Añadir firmantes por email/teléfono<br>- Orden de firma (secuencial/paralelo)<br>- Fecha límite opcional<br>- Mensaje personalizado | Beta |
| E3-002 | Como **firmante**, quiero acceder al documento mediante un enlace único y seguro, para poder firmarlo | Alta | - Token único por firmante<br>- Expiración configurable<br>- Acceso sin registro obligatorio<br>- Verificación por código OTP (email/SMS) | Beta |
| E3-003 | Como **firmante**, quiero dibujar mi firma o usar una tipográfica, para firmar el documento | Alta | - Canvas para firma manuscrita<br>- Generador de firma tipográfica<br>- Guardar firma para futuros usos<br>- Firma adaptable a zona definida | Beta |
| E3-004 | Como **sistema**, quiero aplicar la firma electrónica avanzada al PDF, para generar un documento legalmente válido | Alta | - Firma PAdES (PDF Advanced Electronic Signature)<br>- Sello de tiempo cualificado (según plan)<br>- Certificado de la plataforma como testigo<br>- Metadata de evidencias embebida | Alpha |
| E3-005 | Como **promotor**, quiero ver el estado de cada proceso de firma en tiempo real, para hacer seguimiento | Alta | - Estados: borrador, enviado, parcialmente firmado, completado, expirado, cancelado<br>- Notificaciones de cambio de estado<br>- Timeline de eventos | Beta |
| E3-006 | Como **promotor**, quiero cancelar un proceso de firma, para anular documentos no deseados | Media | - Cancelación con motivo obligatorio<br>- Notificación a firmantes<br>- Registro en trail de auditoría | Beta |
| E3-007 | Como **promotor**, quiero reenviar recordatorios a firmantes pendientes, para agilizar el proceso | Media | - Reenvío manual<br>- Recordatorios automáticos configurables<br>- Límite de reenvíos | Beta |

---

### EPIC E4: Notificaciones

| ID | Historia de Usuario | Prioridad | Criterios de Aceptación | Squad |
|----|---------------------|-----------|-------------------------|-------|
| E4-001 | Como **sistema**, quiero enviar solicitudes de firma por email, para notificar a los firmantes | Alta | - Email con enlace único<br>- Plantilla personalizable por tenant<br>- Tracking de apertura<br>- Reintentos automáticos si falla | Beta |
| E4-002 | Como **sistema**, quiero enviar solicitudes de firma por SMS, para firmantes que prefieren móvil | Alta | - SMS con enlace corto<br>- Integración con proveedor SMS (Twilio/similar)<br>- Tracking de entrega<br>- Coste por SMS configurable | Beta |
| E4-003 | Como **sistema**, quiero enviar códigos OTP por email/SMS, para verificar identidad del firmante | Alta | - Código de 6 dígitos<br>- Expiración de 10 minutos<br>- Máximo 3 intentos<br>- Registro de verificación en evidencias | Alpha |
| E4-004 | Como **promotor**, quiero recibir notificaciones cuando un documento sea firmado, para estar informado | Media | - Email de notificación<br>- Notificación in-app<br>- Webhook opcional para integraciones | Beta |
| E4-005 | Como **administrador**, quiero configurar las plantillas de email de mi organización, para mantener mi marca | Media | - Editor de plantillas HTML<br>- Variables dinámicas<br>- Preview antes de guardar | Beta |

---

### EPIC E5: Entrega de Copias

| ID | Historia de Usuario | Prioridad | Criterios de Aceptación | Squad |
|----|---------------------|-----------|-------------------------|-------|
| E5-001 | Como **sistema**, quiero generar el documento final firmado con todas las evidencias, para entrega a las partes | Alta | - PDF con firmas visibles<br>- Metadata de evidencias embebida<br>- Página de certificación anexa<br>- Verificable con herramienta pública | Alpha |
| E5-002 | Como **firmante**, quiero recibir automáticamente una copia del documento firmado, para mis registros | Alta | - Email automático al completar<br>- Enlace de descarga (expira en 30 días)<br>- Opción de envío por SMS (enlace) | Beta |
| E5-003 | Como **promotor**, quiero descargar el documento firmado y el dossier de evidencias, para mis archivos | Alta | - Descarga de PDF firmado<br>- Descarga de dossier probatorio separado<br>- Descarga en ZIP con ambos | Beta |
| E5-004 | Como **promotor**, quiero acceder a documentos firmados en cualquier momento, para consulta histórica | Media | - Búsqueda por fecha, firmante, estado<br>- Filtros avanzados<br>- Exportación masiva | Beta |

---

### EPIC E6: Marca Blanca (White-Label)

| ID | Historia de Usuario | Prioridad | Criterios de Aceptación | Squad |
|----|---------------------|-----------|-------------------------|-------|
| E6-001 | Como **administrador de tenant**, quiero personalizar el logo y colores de la plataforma, para reflejar mi marca | Media | - Upload de logo<br>- Selector de color primario/secundario<br>- Preview en tiempo real | Beta |
| E6-002 | Como **administrador de tenant**, quiero usar mi propio dominio, para que los firmantes vean mi marca | Media | - Configuración de dominio personalizado<br>- Gestión automática de SSL<br>- DNS verification | Alpha |
| E6-003 | Como **administrador de tenant**, quiero personalizar los emails que envía la plataforma, para mantener coherencia de marca | Media | - Nombre del remitente personalizable<br>- Dominio de envío personalizado (DKIM/SPF)<br>- Plantillas con branding | Beta |
| E6-004 | Como **administrador de tenant**, quiero ocultar referencias a Firmalum, para una experiencia 100% white-label | Baja | - Opción de ocultar "Powered by Firmalum"<br>- Términos y condiciones propios<br>- Solo disponible en planes premium | Beta |

---

## 📊 Estado del Backlog - Actualizado Sprint 6

### ✅ COMPLETADO (28/28 historias - 100%) 🎉 MVP COMPLETO

**Sprint 1-5** (23 historias):
| ID | Historia | Estado | Sprint | Nota |
|----|----------|--------|--------|------|
| E0-003 | Autenticación segura | ✅ DONE | Sprint 1 | Login, 2FA, recuperación |
| E0-004 | Base de datos multi-tenant | ✅ DONE | Sprint 1 | Scopes, middleware |
| E1-001 | Timestamp cualificado (TSA) | ✅ DONE | Sprint 1 | RFC 3161 |
| E1-002 | Hash SHA-256 de documentos | ✅ DONE | Sprint 1 | Integridad |
| E1-006 | Trail de auditoría inmutable | ✅ DONE | Sprint 1 | Hash chain |
| E1-003 | Huella digital del dispositivo | ✅ DONE | Sprint 2 | Fingerprint |
| E1-004 | Geolocalización del firmante | ✅ DONE | Sprint 2 | GPS + IP geolocation |
| E1-005 | IP con resolución inversa | ✅ DONE | Sprint 2 | DNS reverse |
| E1-010 | Consentimiento explícito | ✅ DONE | Sprint 2 | Checkbox legal |
| E1-007 | Dossier probatorio PDF | ✅ DONE | Sprint 2 | Evidence export |
| E1-008 | Conservación 5+ años | ✅ DONE | Sprint 3 | Tiers + reseal TSA |
| E1-009 | Verificación pública | ✅ DONE | Sprint 3 | API REST sin auth |
| E2-001 | Subir documentos PDF | ✅ DONE | Sprint 3 | Upload + validación |
| E3-001 | Crear proceso de firma | ✅ DONE | Sprint 4 | Livewire component |
| E3-002 | Acceso por enlace único | ✅ DONE | Sprint 4 | Token único |
| E3-003 | Dibujar/seleccionar firma | ✅ DONE | Sprint 4 | Canvas + type + upload |
| E3-004 | Aplicar firma PAdES | ✅ DONE | Sprint 4 | PAdES-B-LT |
| E3-005 | Ver estado de procesos | ✅ DONE | Sprint 4 | Dashboard |
| E4-001 | Enviar solicitudes por email | ✅ DONE | Sprint 4 | Queue jobs |
| E4-003 | Enviar códigos OTP | ✅ DONE | Sprint 4 | 6 dígitos |
| E5-001 | Documento final firmado | ✅ DONE | Sprint 5 | Merge + certification |
| E5-002 | Enviar copia a firmantes | ✅ DONE | Sprint 5 | Auto-send |
| E5-003 | Descargar documento y dossier | ✅ DONE | Sprint 5 | ZIP bundle |
| E3-006 | Cancelar proceso de firma | ✅ DONE | Sprint 5 | Con notificaciones |

**Sprint 6** (3 historias):
| ID | Historia | Estado | Progreso |
|----|----------|--------|----------|
| E0-001 | Crear organizaciones | ✅ DONE | 100% (25/25 tests) |
| E0-002 | Gestionar usuarios | ✅ DONE | 100% (42/42 tests) |
| E2-003 | Encriptación at-rest | ✅ DONE | 100% (37/37 tests) |

---

### ⏳ PENDIENTE Sprint 7+ (Post-MVP Features)

**MVP**: ✅ 100% COMPLETADO (28/28 historias)

**Post-MVP** (Feature expansion):
| ID | Historia | Prioridad | Sprint estimado | Mercado objetivo |
|----|----------|-----------|-----------------|------------------|
| E2-002 | Definir zonas de firma | 🔴 ALTA | Sprint 7 | HR, Sales, General |
| E2-005 | Plantillas de documentos | 🔴 ALTA | Sprint 7 | HR, Sales |
| E3-007 | Reenviar recordatorios | 🟡 MEDIA | Sprint 7 | UX improvement |
| E4-002 | SMS notifications | 🟡 MEDIA | Sprint 8 | Mobile users |
| E5-004 | Acceso histórico avanzado | 🟡 MEDIA | Sprint 8 | Power users |
| API-001 | REST API signing | 🔴 ALTA | Sprint 8 | Developers, integrations |
| E2-006 | Document Builder | 🟡 MEDIA | Sprint 10-11 | Sales teams |
| E6-001 | Personalizar logo/colores | 🟢 BAJA | Sprint 7-8 | White-label |
| E6-002 | Dominio personalizado | 🟢 BAJA | Sprint 8 | White-label |
| E6-003 | Personalizar emails | 🟢 BAJA | Sprint 7-8 | White-label |
| E6-004 | Ocultar referencias plataforma | 🟢 BAJA | Sprint 9+ | Premium feature |
| E2-004 | Carpetas de documentos | 🟢 BAJA | Sprint 9+ | Organization |
| E4-004 | Notificaciones in-app | 🟢 BAJA | Sprint 9+ | UX nice-to-have |
| E4-005 | Editor plantillas email | 🟢 BAJA | Sprint 9+ | Advanced customization |

---

## Resumen del Backlog - Tabla Maestra (Actualizado 2025-12-30)

### Estado Actual: 28/28 MVP (100% COMPLETO) 🎉

| ID | Historia de Usuario | Prioridad | Estado | Epic | Squad | Sprint |
|----|---------------------|-----------|--------|------|-------|--------|
| E0-001 | Crear organizaciones | Alta | ✅ **DONE** | E0 | Alpha | Sprint 6 |
| E0-002 | Gestionar usuarios | Alta | ✅ **DONE** | E0 | Alpha | Sprint 6 |
| E0-003 | Autenticación segura | Alta | ✅ **DONE** | E0 | Alpha | Sprint 1 |
| E0-004 | Base de datos multi-tenant | Alta | ✅ **DONE** | E0 | Alpha | Sprint 1 |
| **E1-001** | **Timestamp cualificado (TSA)** | **CRÍTICA** | ✅ **DONE** | E1 | Alpha | Sprint 1 |
| **E1-002** | **Hash SHA-256 documentos** | **CRÍTICA** | ✅ **DONE** | E1 | Alpha | Sprint 1 |
| **E1-003** | **Huella digital dispositivo** | **CRÍTICA** | ✅ **DONE** | E1 | Alpha | Sprint 2 |
| **E1-004** | **Geolocalización firmante** | **CRÍTICA** | ✅ **DONE** | E1 | Alpha | Sprint 2 |
| **E1-005** | **IP con resolución inversa** | **CRÍTICA** | ✅ **DONE** | E1 | Alpha | Sprint 2 |
| **E1-006** | **Trail auditoría inmutable** | **CRÍTICA** | ✅ **DONE** | E1 | Alpha | Sprint 1 |
| **E1-007** | **Dossier probatorio PDF** | **CRÍTICA** | ✅ **DONE** | E1 | Alpha | Sprint 2 |
| **E1-008** | **Conservación 5+ años** | **CRÍTICA** | ✅ **DONE** | E1 | Alpha | Sprint 3 |
| **E1-009** | **Verificación pública** | **CRÍTICA** | ✅ **DONE** | E1 | Alpha | Sprint 3 |
| **E1-010** | **Consentimiento explícito** | **CRÍTICA** | ✅ **DONE** | E1 | Alpha | Sprint 2 |
| E2-001 | Subir documentos PDF | Alta | ✅ **DONE** | E2 | Beta | Sprint 3 |
| E2-002 | Definir zonas de firma | Alta | 📋 **BACKLOG** | E2 | Beta | Sprint 7 |
| E2-003 | Almacenamiento encriptado | Alta | ✅ **DONE** | E2 | Alpha | Sprint 6 |
| E2-004 | Carpetas de documentos | Media | 📋 **BACKLOG** | E2 | Beta | Sprint 9+ |
| E2-005 | Plantillas de documentos | Media | 📋 **BACKLOG** | E2 | Beta | Sprint 7 |
| E3-001 | Crear proceso de firma | Alta | ✅ **DONE** | E3 | Beta | Sprint 4 |
| E3-002 | Acceso por enlace único | Alta | ✅ **DONE** | E3 | Beta | Sprint 4 |
| E3-003 | Dibujar/seleccionar firma | Alta | ✅ **DONE** | E3 | Beta | Sprint 4 |
| E3-004 | Aplicar firma PAdES | Alta | ✅ **DONE** | E3 | Alpha | Sprint 4 |
| E3-005 | Ver estado de procesos | Alta | ✅ **DONE** | E3 | Beta | Sprint 4 |
| E3-006 | Cancelar proceso | Media | ✅ **DONE** | E3 | Beta | Sprint 5 |
| E3-007 | Reenviar recordatorios | Media | 📋 **BACKLOG** | E3 | Beta | Sprint 7 |
| E4-001 | Solicitudes por email | Alta | ✅ **DONE** | E4 | Beta | Sprint 4 |
| E4-002 | Solicitudes por SMS | Alta | 📋 **BACKLOG** | E4 | Beta | Sprint 8 |
| E4-003 | Códigos OTP | Alta | ✅ **DONE** | E4 | Alpha | Sprint 4 |
| E4-004 | Notificaciones al promotor | Media | 📋 **BACKLOG** | E4 | Beta | Sprint 9+ |
| E4-005 | Plantillas de email | Media | 📋 **BACKLOG** | E4 | Beta | Sprint 9+ |
| E5-001 | Documento final firmado | Alta | ✅ **DONE** | E5 | Alpha | Sprint 5 |
| E5-002 | Enviar copia a firmantes | Alta | ✅ **DONE** | E5 | Beta | Sprint 5 |
| E5-003 | Descargar doc y dossier | Alta | ✅ **DONE** | E5 | Beta | Sprint 5 |
| E5-004 | Acceso histórico | Media | 📋 **BACKLOG** | E5 | Beta | Sprint 8 |
| E6-001 | Personalizar logo/colores | Media | 📋 **BACKLOG** | E6 | Beta | Sprint 7-8 |
| E6-002 | Dominio personalizado | Media | 📋 **BACKLOG** | E6 | Alpha | Sprint 8 |
| E6-003 | Personalizar emails | Media | 📋 **BACKLOG** | E6 | Beta | Sprint 7-8 |
| E6-004 | Ocultar referencias | Baja | 📋 **BACKLOG** | E6 | Beta | Sprint 9+ |

**Legend**:
- ✅ DONE: Completado, testeado, aprobado
- ⏳ IN PROGRESS / CODE REVIEW: En desarrollo o revisión
- 📋 BACKLOG: Planificado para sprints futuros

---

## 🚀 Roadmap de Sprints (Actualizado)

### ✅ Sprint 1-5: COMPLETADOS (23/28 historias - 82%)
Ver [`docs/kanban.md`](kanban.md) para detalles completos

### ⏳ Sprint 6: EN PROGRESO (3/3 historias - 93% MVP total)
**Goal**: Habilitar multi-tenant y completar MVP al 100%
**Duration**: 4 semanas
**Status**: Semana 1-2 completadas, E0-001 DONE, E0-002 en corrección

| Tarea | Estado | Tests | Score |
|-------|--------|-------|-------|
| E0-001: Crear organizaciones | ✅ DONE | 25/25 (100%) | 98/100 |
| E0-002: Gestionar usuarios | ⏳ REVIEW | 28/42 (67%) | 8.2/10 |
| E2-003: Encriptación at-rest | 📋 TO DO | 0/30 | - |

**ETA MVP 100%**: 3-4 días (pending correcciones + E2-003)

---

## Priorización de Desarrollo (Roadmap Sugerido)

### 🚀 Sprint 1: Fundamentos + Evidencias Core
1. E0-003 - Autenticación segura
2. E0-004 - Base de datos multi-tenant
3. E1-001 - Timestamp cualificado (TSA)
4. E1-002 - Hash SHA-256
5. E1-006 - Trail de auditoría inmutable

### 🔐 Sprint 2: Sistema de Evidencias Completo
1. E1-003 - Huella digital del dispositivo
2. E1-004 - Geolocalización
3. E1-005 - IP con resolución inversa
4. E1-010 - Captura de consentimiento
5. E1-007 - Dossier probatorio PDF

### 📄 Sprint 3: Gestión de Documentos + Firma Básica
1. E2-001 - Subir PDFs
2. E2-003 - Almacenamiento seguro
3. E3-001 - Crear proceso de firma
4. E3-002 - Acceso por enlace único
5. E3-003 - Dibujar firma

### ✍️ Sprint 4: Firma Completa + Notificaciones
1. E2-002 - Zonas de firma
2. E3-004 - Firma PAdES
3. E4-001 - Solicitudes por email
4. E4-003 - Códigos OTP
5. E3-005 - Estados de proceso

### 📬 Sprint 5: Entrega + Multi-tenant
1. E5-001 - Documento final firmado
2. E5-002 - Copia a firmantes
3. E5-003 - Descargas
4. E0-001 - Crear tenants
5. E0-002 - Gestionar usuarios

### 🎨 Sprint 6: Marca Blanca + SMS + Mejoras
1. E4-002 - SMS
2. E6-001 - Personalizar marca
3. E6-002 - Dominio personalizado
4. E1-008 - Conservación largo plazo
5. E1-009 - Verificación pública

---

## Requisitos Técnicos de Cumplimiento eIDAS

Para que las firmas electrónicas sean **avanzadas** según eIDAS, deben cumplir:

1. ✅ **Vinculación única al firmante** → Verificación OTP por email/SMS
2. ✅ **Identificación del firmante** → Captura de dispositivo, IP, geolocalización
3. ✅ **Datos bajo control exclusivo del firmante** → Proceso de firma en su dispositivo
4. ✅ **Detección de alteraciones posteriores** → Hash SHA-256 + sellado TSA
5. ✅ **Trazabilidad completa** → Trail de auditoría inmutable

### Proveedores Cualificados Recomendados (TSA)
- DigiCert
- GlobalSign
- Sectigo
- Firmaprofesional (España)
- ANF AC (España)

---

*Última actualización: 2025-12-28*
*Product Owner: Firmalum Team*
