# Documentacion de Firmalum

Indice de toda la documentacion tecnica. **No se crean `.md` sueltos en la
raiz**: todo cuelga de `docs/` y se anade aqui en el mismo commit que lo
implementa.

## Empezar

| Documento | Para que |
|---|---|
| [SYSTEM_OVERVIEW.md](SYSTEM_OVERVIEW.md) | Que es Firmalum y como encajan sus piezas. Empieza por aqui. |
| [../README.md](../README.md) | Instalacion y comandos del dia a dia. |
| [../CHANGELOG.md](../CHANGELOG.md) | Que ha cambiado en cada version. |

## Producto y planificacion

| Documento | Para que |
|---|---|
| [backlog.md](backlog.md) | Epicas, historias y estado del MVP. |
| [kanban.md](kanban.md) | Tablero de trabajo en curso. |
| [product/competitive-analysis-signing-flows.md](product/competitive-analysis-signing-flows.md) | Analisis competitivo de flujos de firma. |
| [planning/sprint4-plan.md](planning/sprint4-plan.md) · [sprint5](planning/sprint5-plan.md) · [sprint6](planning/sprint6-plan.md) | Planes de sprint. |

## Arquitectura

| Documento | Para que |
|---|---|
| [architecture/decisions.md](architecture/decisions.md) | Registro general de decisiones. |
| [architecture/adr-006-evidence-capture-sprint2.md](architecture/adr-006-evidence-capture-sprint2.md) | Captura de evidencias. |
| [architecture/adr-007-sprint3-retention-verification-upload.md](architecture/adr-007-sprint3-retention-verification-upload.md) | Retencion, verificacion y subida. |
| [architecture/adr-008-tsa-strategy.md](architecture/adr-008-tsa-strategy.md) | Estrategia de sellado de tiempo (TSA). |
| [architecture/adr-009-pades-signature-strategy.md](architecture/adr-009-pades-signature-strategy.md) | Estrategia de firma PAdES. |
| [architecture/adr-010-encryption-at-rest.md](architecture/adr-010-encryption-at-rest.md) | Cifrado en reposo. |

## Dominio

| Documento | Para que |
|---|---|
| [signing/README.md](signing/README.md) | Firma PAdES: componentes, niveles y certificados. |
| [encryption/README.md](encryption/README.md) | Cifrado de documentos. |
| [implementation/e0-001-tenant-management-summary.md](implementation/e0-001-tenant-management-summary.md) | Gestion de tenants. |
| [implementation/e0-002-user-management-summary.md](implementation/e0-002-user-management-summary.md) | Gestion de usuarios. |
| [implementation/e2-003-encryption-at-rest-summary.md](implementation/e2-003-encryption-at-rest-summary.md) | Cifrado en reposo. |
| [implementation/e3-004-pades-signature-summary.md](implementation/e3-004-pades-signature-summary.md) | Firma PAdES. |

## Operacion

| Documento | Para que |
|---|---|
| [BACKUPS.md](BACKUPS.md) | Backup, verificacion y restore. |
| [deployment/DEPLOY-NOW.md](deployment/DEPLOY-NOW.md) | Despliegue paso a paso. |
| [deployment/production-deployment-digitalocean.md](deployment/production-deployment-digitalocean.md) | Produccion en DigitalOcean. |
| [deployment/production-requirements-checklist.md](deployment/production-requirements-checklist.md) | Checklist previo a produccion. |
| [deployment/staging-deployment-guide.md](deployment/staging-deployment-guide.md) | Despliegue en staging. |
| [deployment/mvp-deployment-guide.md](deployment/mvp-deployment-guide.md) | Guia de despliegue del MVP. |
| [deployment/environment-variables.md](deployment/environment-variables.md) | Variables de entorno. |
| [deployment/encryption-setup-guide.md](deployment/encryption-setup-guide.md) | Puesta en marcha del cifrado. |
| [deployment/WILDCARD-SSL-SETUP.md](deployment/WILDCARD-SSL-SETUP.md) | Certificado SSL wildcard. |

## Administracion

| Documento | Para que |
|---|---|
| [admin/superadmin-guide.md](admin/superadmin-guide.md) | Panel de superadministrador. |
| [admin/user-management-guide.md](admin/user-management-guide.md) | Gestion de usuarios y roles. |

## Calidad

| Documento | Para que |
|---|---|
| [REFACTORING_AND_TESTING.md](REFACTORING_AND_TESTING.md) | Deuda tecnica viva: que esta roto y en que orden se paga. |
| [reviews/](reviews/) | Revisiones de codigo y auditorias de seguridad por historia. |

## Gobernanza

| Documento | Para que |
|---|---|
| [governance/autonomy-protocol.md](governance/autonomy-protocol.md) | Protocolo de autonomia de los agentes. |
| [governance/autonomous-maintenance.md](governance/autonomous-maintenance.md) | Mantenimiento autonomo. |
| [governance/kanban-protocol.md](governance/kanban-protocol.md) | Protocolo del tablero. |
