# Kanban Board - ANCLA

> 📋 Última actualización: 2025-12-28

## 🎯 Sprint Actual: Sprint 1 - Fundamentos + Evidencias Core

---

## BACKLOG

| ID | Tarea | Prioridad | Squad | Bloqueado por |
|----|-------|-----------|-------|---------------|
| E1-003 | Capturar huella digital del dispositivo | CRÍTICA | Alpha | E1-006 |
| E1-004 | Capturar geolocalización del firmante | CRÍTICA | Alpha | E1-006 |
| E1-005 | Registrar IP con resolución inversa | CRÍTICA | Alpha | E1-006 |
| E1-007 | Exportar dossier probatorio PDF | CRÍTICA | Alpha | E1-006 |
| E1-008 | Conservación de evidencias 5+ años | CRÍTICA | Alpha | E1-007 |
| E1-009 | Verificación de integridad pública | CRÍTICA | Alpha | E1-007 |
| E1-010 | Captura de consentimiento explícito | CRÍTICA | Alpha | E1-006 |
| E2-001 | Subir documentos PDF | Alta | Beta | E0-004 |
| E2-002 | Definir zonas de firma | Alta | Beta | E2-001 |
| E2-003 | Almacenamiento seguro y encriptado | Alta | Alpha | E0-004 |
| E3-001 | Crear proceso de firma | Alta | Beta | E2-001 |
| E3-002 | Acceso por enlace único | Alta | Beta | E3-001 |
| E3-003 | Dibujar/seleccionar firma | Alta | Beta | E3-002 |
| E3-004 | Aplicar firma PAdES al PDF | Alta | Alpha | E3-003 |
| E3-005 | Ver estado de procesos | Alta | Beta | E3-001 |
| E4-001 | Enviar solicitudes por email | Alta | Beta | E3-001 |
| E4-002 | Enviar solicitudes por SMS | Alta | Beta | E4-001 |
| E4-003 | Enviar códigos OTP | Alta | Alpha | E0-003 |
| E5-001 | Generar documento final firmado | Alta | Alpha | E3-004 |
| E5-002 | Enviar copia a firmantes | Alta | Beta | E5-001 |
| E5-003 | Descargar documento y dossier | Alta | Beta | E5-001 |
| E0-001 | Crear nuevas organizaciones (tenants) | Alta | Alpha | E0-004 |
| E0-002 | Gestionar usuarios de organización | Alta | Alpha | E0-001 |
| E6-001 | Personalizar logo y colores | Media | Beta | E0-001 |
| E6-002 | Dominio personalizado | Media | Alpha | E0-001 |

---

## TO DO (Sprint 1)

| ID | Tarea | Prioridad | Squad | Asignado a | Fecha límite |
|----|-------|-----------|-------|------------|--------------|
| E0-003 | Autenticación segura (Login, 2FA, recuperación) | Alta | Alpha | - | - |
| E0-004 | Base de datos multi-tenant (scopes, middleware) | Alta | Alpha | - | - |
| E1-001 | Capturar timestamp cualificado (TSA RFC 3161) | **CRÍTICA** | Alpha | - | - |
| E1-002 | Generar hash SHA-256 de documentos | **CRÍTICA** | Alpha | - | - |
| E1-006 | Trail de auditoría inmutable (hash encadenado) | **CRÍTICA** | Alpha | - | - |

---

## IN PROGRESS

| ID | Tarea | Squad | Asignado a | Fecha inicio | Notas |
|----|-------|-------|------------|--------------|-------|
| - | - | - | - | - | - |

---

## CODE REVIEW

| ID | Tarea | Squad | Revisor | Fecha envío | Estado |
|----|-------|-------|---------|-------------|--------|
| - | - | - | - | - | - |

---

## DONE

| ID | Tarea | Squad | Completado por | Fecha completado |
|----|-------|-------|----------------|------------------|
| - | - | - | - | - |

---

## 📊 Métricas del Sprint

- **Tareas en TO DO**: 5
- **Tareas en PROGRESS**: 0
- **Tareas en REVIEW**: 0
- **Tareas DONE**: 0
- **Velocidad estimada**: 5 tareas/sprint

## 🚧 Bloqueos Activos

| Tarea bloqueada | Bloqueada por | Responsable | Acción requerida |
|-----------------|---------------|-------------|------------------|
| Ninguno | - | - | - |

---

## 📝 Notas del Sprint

### Objetivo del Sprint 1
Establecer la infraestructura base de autenticación, multi-tenancy, y los componentes core del sistema de evidencias que son la **prioridad absoluta** del producto.

### Definición de "Done"
- [ ] Código implementado y funcionando
- [ ] Tests unitarios con cobertura > 80%
- [ ] Tests de integración para flujos críticos
- [ ] Documentación técnica actualizada
- [ ] Revisión de seguridad completada (para E1-*)
- [ ] `./bin/auto-fix.sh` ejecutado sin errores

### Riesgos Identificados
1. **Integración TSA**: Dependencia de proveedores externos para sellado de tiempo
2. **Cumplimiento eIDAS**: Requiere validación legal de la implementación

---

*Protocolo: Ver [kanban-protocol.md](governance/kanban-protocol.md)*
