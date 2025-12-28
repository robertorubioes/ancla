# Protocolo de Gestión del Kanban

## Fuente de Verdad
El archivo **`docs/kanban.md`** es la fuente de verdad para el estado de las tareas. Todos los agentes DEBEN mantenerlo actualizado.

## Columnas del Kanban

| Columna | Significado | Quién mueve aquí |
|---------|-------------|------------------|
| **BACKLOG** | Ideas y tareas futuras | PO, Business Strategist |
| **TO DO** | Priorizadas para el sprint actual | PO |
| **IN PROGRESS** | Activamente en desarrollo | Developer (al empezar) |
| **CODE REVIEW** | Esperando revisión | Developer (al terminar) |
| **DONE** | Completado y validado | Tech Lead / Security |

## Reglas de Transición

### BACKLOG → TO DO
- **Quién**: Product Owner
- **Cuándo**: Al iniciar un sprint o cuando hay capacidad
- **Requisito**: La tarea debe tener criterios de aceptación claros

### TO DO → IN PROGRESS
- **Quién**: Developer que toma la tarea
- **Cuándo**: Al comenzar a trabajar
- **Acción**: Añadir fecha de inicio y nombre del agente

### IN PROGRESS → CODE REVIEW
- **Quién**: Developer que completó la tarea
- **Cuándo**: Código listo y tests pasando
- **Requisito**: Haber ejecutado `./bin/auto-fix.sh`

### CODE REVIEW → DONE
- **Quién**: Tech Lead o Security Expert
- **Cuándo**: Tras validar código y seguridad
- **Acción**: Añadir fecha de completado

## Límites WIP (Work In Progress)
- Máximo **3 tareas** en IN PROGRESS por Squad
- Máximo **5 tareas** en CODE REVIEW total

## Bloqueos
Si una tarea está bloqueada:
1. Añadir en la columna "Bloqueado por" el ID de la tarea bloqueante
2. Notificar al PO para repriorizar si es necesario
