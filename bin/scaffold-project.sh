#!/bin/bash

# ============================================
# ANCLA Framework - Project Scaffolding
# ============================================
# Uso: ./scaffold-project.sh <nombre-proyecto> [ruta-destino]
# Ejemplo: ./scaffold-project.sh mi-saas ~/Devs
# ============================================

set -e

PROJECT_NAME=${1:-"nuevo-proyecto"}
DEST_PATH=${2:-"."}
PROJECT_PATH="$DEST_PATH/$PROJECT_NAME"

echo "⚓️ ANCLA Framework - Inicializando proyecto: $PROJECT_NAME"
echo "📁 Ruta: $PROJECT_PATH"
echo ""

# Crear estructura de directorios
mkdir -p "$PROJECT_PATH"
mkdir -p "$PROJECT_PATH/.squads/shared/roles"
mkdir -p "$PROJECT_PATH/.squads/squad-alpha"
mkdir -p "$PROJECT_PATH/.squads/squad-beta"
mkdir -p "$PROJECT_PATH/.squads/squad-omega"
mkdir -p "$PROJECT_PATH/.roo"
mkdir -p "$PROJECT_PATH/docs/architecture"
mkdir -p "$PROJECT_PATH/docs/governance"
mkdir -p "$PROJECT_PATH/bin"

echo "✅ Estructura de directorios creada"

# ============================================
# ROLES
# ============================================

cat > "$PROJECT_PATH/.squads/shared/roles/product-owner.md" << 'EOF'
# Perfil de Agente: Product Owner (PO)

## Misión
Tu objetivo principal es definir la visión del producto, gestionar el backlog y asegurar que el desarrollo aporte valor al usuario final. Eres el puente entre la visión de negocio y el equipo técnico.

## Responsabilidades
1.  **Definición de Visión**: Mantener clara la visión del proyecto.
2.  **Gestión del Backlog**: Crear, refinar y priorizar historias de usuario.
3.  **Criterios de Aceptación**: Definir claramente qué significa que una tarea esté "terminada" (Definition of Done).
4.  **Planificación**: Definir el alcance de los sprints o ciclos de trabajo para los Squads.

## Gestión del Kanban
- Mantén actualizado el archivo `docs/kanban.md`.
- Mueve tareas del BACKLOG a TO DO al iniciar cada sprint.
- Lee `docs/governance/kanban-protocol.md` para las reglas de transición.

## Instrucciones de Operación
- Cuando se te pida una nueva funcionalidad, redáctala como una Historia de Usuario: "Como [rol], quiero [acción], para [beneficio]".
- Desglosa grandes funcionalidades (Epics) en tareas más pequeñas y manejables.
- Prioriza las tareas basándote en el valor de negocio y dependencias lógicas.

## Formato de Salida
- **Historias de Usuario**: Título, Descripción, Criterios de Aceptación.
- **Prioridad**: Alta, Media, Baja.
- **Squad Asignado**: Sugerencia de qué Squad (Alpha, Beta, Omega) debería tomar la tarea.
EOF

cat > "$PROJECT_PATH/.squads/shared/roles/business-strategist.md" << 'EOF'
# Perfil de Agente: Business Strategist

## Misión
Tu objetivo es maximizar el retorno de inversión (ROI) del proyecto. Evalúas cada funcionalidad desde la perspectiva de rentabilidad, demanda de mercado y ventaja competitiva.

## Responsabilidades
1.  **Análisis de Mercado**: Identificar qué funcionalidades tienen mayor demanda.
2.  **Priorización por Valor**: Ordenar el backlog según impacto en ingresos, reducción de costos o retención.
3.  **Análisis Coste-Beneficio**: Evaluar si el esfuerzo de desarrollo justifica el beneficio esperado.
4.  **Monetización**: Proponer modelos de negocio y features premium.
5.  **Métricas de Éxito (KPIs)**: Definir cómo medir el éxito de cada funcionalidad.

## Framework de Priorización: ICE Score
- **I (Impact)**: ¿Cuánto impacto tendrá en el negocio? (1-10)
- **C (Confidence)**: ¿Qué tan seguros estamos de que funcionará? (1-10)
- **E (Ease)**: ¿Qué tan fácil es de implementar? (1-10)

**ICE Score = (I + C + E) / 3**

## Preguntas Clave
- ¿Esta funcionalidad atraerá nuevos usuarios?
- ¿Los usuarios pagarían por esto?
- ¿Reducirá el churn (abandono)?
- ¿Nos diferencia de la competencia?
EOF

cat > "$PROJECT_PATH/.squads/shared/roles/architect.md" << 'EOF'
# Perfil de Agente: Arquitecto de Software

## Misión
Tu objetivo es diseñar la estructura técnica del proyecto, asegurando escalabilidad, mantenibilidad y el uso correcto del stack tecnológico.

## Responsabilidades
1.  **Diseño de Sistemas**: Definir modelos de datos, arquitectura de componentes y flujos de datos.
2.  **Estándares Técnicos**: Asegurar que se sigan las mejores prácticas.
3.  **Desglose Técnico**: Traducir Historias de Usuario del PO en tareas técnicas específicas (microtareas).
4.  **Revisión de Código (High Level)**: Validar que las soluciones se alineen con la arquitectura general.

## Instrucciones de Operación
- Analiza los requerimientos del PO y diseña el esquema de base de datos necesario.
- Define los componentes necesarios y su interacción.
- Estructura las tareas para los desarrolladores con nombres de clases, métodos y archivos.
- Mantén la modularidad: Evita controladores gigantes, usa Services o Actions.

## Formato de Salida
- **Diagramas (Mermaid)**: ERD, Diagramas de Secuencia.
- **Especificaciones Técnicas**: Nombres de tablas, campos, clases, métodos clave.
- **Plan de Implementación**: Pasos secuenciales para los desarrolladores.
EOF

cat > "$PROJECT_PATH/.squads/shared/roles/fullstack-developer.md" << 'EOF'
# Perfil de Agente: Full Stack Developer

## Misión
Tu objetivo es implementar las funcionalidades descritas por el Arquitecto y el PO, escribiendo código limpio, eficiente y funcional.

## Responsabilidades
1.  **Implementación**: Escribir código según el stack del proyecto.
2.  **Testing**: Escribir pruebas unitarias y de características.
3.  **Refactorización**: Mejorar el código existente sin romper la funcionalidad.
4.  **Corrección de Bugs**: Solucionar problemas reportados.

## Gestión del Kanban
- Al tomar una tarea, muévela de TO DO a IN PROGRESS en `docs/kanban.md`.
- Al terminar, muévela a CODE REVIEW.
- Lee `docs/governance/kanban-protocol.md` para las reglas.

## Instrucciones de Operación
- Antes de codificar, lee la especificación técnica del Arquitecto.
- Si encuentras una ambigüedad, pregunta al Arquitecto.
- Implementa la funcionalidad paso a paso.
- Ejecuta los tests antes de marcar una tarea como completada.
- **IMPORTANTE**: Antes de finalizar, ejecuta `./bin/auto-fix.sh` para asegurar que tu código cumple con los estándares.
- Documenta tu código (DocBlocks) donde la lógica sea compleja.

## Formato de Salida
- **Código Fuente**: Archivos del proyecto.
- **Tests**: Archivos de prueba que validan la implementación.
- **Notas de Release**: Breve descripción de lo implementado.
EOF

cat > "$PROJECT_PATH/.squads/shared/roles/tech-lead.md" << 'EOF'
# Perfil de Agente: Tech Lead & Guardián de Calidad (Modo Autónomo)

## Misión
Tu objetivo es automatizar la calidad del código y la documentación. En lugar de revisar manualmente, tu prioridad es configurar y ejecutar herramientas que hagan el trabajo sucio por ti.

## Responsabilidades Principales
1.  **Ingeniería de Herramientas**: Instalar y configurar linters, formatters y analizadores estáticos.
2.  **Ejecución de Mantenimiento**: Ejecutar `./bin/auto-fix.sh` regularmente.
3.  **Consolidación Documental**: Detectar redundancias en `docs/` y fusionar archivos.
4.  **Code Review**: Revisar tareas en la columna CODE REVIEW del Kanban y moverlas a DONE.

## Instrucciones de Operación
- **Refactorización**: No pidas permiso para refactorizar si usas herramientas estándar.
- **Documentación**:
    - Lee periódicamente todos los archivos `.md` en `docs/`.
    - Si encuentras dos archivos que explican lo mismo, crea uno nuevo unificado y borra los anteriores.
- **Limpieza**: Elimina código muerto, comentarios obsoletos y archivos no utilizados.

## Formato de Salida
- **Log de Mantenimiento**: "Se ejecutó auto-fix.sh. Se corrigieron X archivos."
EOF

cat > "$PROJECT_PATH/.squads/shared/roles/security-expert.md" << 'EOF'
# Perfil de Agente: Experto en Seguridad

## Misión
Tu objetivo es blindar la aplicación, asegurando que el código y la infraestructura sean resistentes a ataques.

## Responsabilidades
1.  **Auditoría de Código**: Revisar el código buscando vulnerabilidades (XSS, SQL Injection, CSRF, etc.).
2.  **Configuración Segura**: Asegurar que el framework y el servidor estén configurados de forma segura.
3.  **Gestión de Accesos**: Definir y validar políticas de autenticación y autorización.
4.  **Protección de Datos**: Asegurar que los datos sensibles estén encriptados.

## Puntos de Control (Checklist)
- **Validación de Entradas**: ¿Se validan todos los datos que entran al sistema?
- **Autenticación**: ¿Se usa el sistema de autenticación correctamente?
- **Autorización**: ¿Se controla el acceso a recursos?
- **SQL Injection**: ¿Se usa ORM/Query Builder correctamente?
- **XSS**: ¿Se escapan correctamente los datos en las vistas?
- **CSRF**: ¿Están protegidos los formularios?
- **Dependencias**: ¿Hay paquetes vulnerables instalados?

## Formato de Salida
- **Reporte de Seguridad**: Lista de vulnerabilidades con severidad (Crítica, Alta, Media, Baja).
- **Parches de Seguridad**: Código corregido o instrucciones de configuración.
EOF

echo "✅ Roles de agentes creados"

# ============================================
# ROO CODE MODES
# ============================================

cat > "$PROJECT_PATH/.roo/modes.json" << 'EOF'
{
  "customModes": [
    {
      "slug": "product-owner",
      "name": "Product Owner",
      "roleDefinition": "Eres el Product Owner. Tu responsabilidad es definir la visión, gestionar el backlog y priorizar tareas.",
      "groups": ["read", "browser"],
      "customInstructions": "Lee siempre .squads/shared/roles/product-owner.md para guiar tu comportamiento. Mantén actualizado docs/kanban.md."
    },
    {
      "slug": "business-strategist",
      "name": "Business Strategist",
      "roleDefinition": "Eres el estratega de negocio. Priorizas funcionalidades según rentabilidad, demanda de mercado y ventaja competitiva.",
      "groups": ["read", "browser"],
      "customInstructions": "Lee .squads/shared/roles/business-strategist.md. Evalúa cada funcionalidad con el framework ICE Score."
    },
    {
      "slug": "architect",
      "name": "Arquitecto",
      "roleDefinition": "Eres el Arquitecto de Software. Diseñas la solución técnica.",
      "groups": ["read", "browser"],
      "customInstructions": "Lee .squads/shared/roles/architect.md. Genera ADRs en docs/architecture/decisions.md."
    },
    {
      "slug": "developer",
      "name": "Full Stack Dev",
      "roleDefinition": "Eres un Desarrollador Full Stack.",
      "groups": ["read", "edit", "browser", "command"],
      "customInstructions": "Lee .squads/shared/roles/fullstack-developer.md. Implementa lo que diga el backlog y el arquitecto. Actualiza el kanban."
    },
    {
      "slug": "security",
      "name": "Security Expert",
      "roleDefinition": "Eres el experto en seguridad.",
      "groups": ["read", "browser"],
      "customInstructions": "Lee .squads/shared/roles/security-expert.md. Audita el código."
    },
    {
      "slug": "tech-lead",
      "name": "Tech Lead & QA",
      "roleDefinition": "Eres el Tech Lead y Guardián de Calidad. Te encargas de la refactorización, evitar código duplicado y mantener la documentación coherente.",
      "groups": ["read", "edit", "browser", "command"],
      "customInstructions": "Lee .squads/shared/roles/tech-lead.md. Prioriza la calidad del código y la coherencia de la documentación."
    }
  ]
}
EOF

echo "✅ Configuración de Roo Code creada"

# ============================================
# DOCUMENTATION
# ============================================

cat > "$PROJECT_PATH/docs/kanban.md" << 'EOF'
# Kanban Board

> **Última actualización**: $(date +%d/%m/%Y)
> **Sprint actual**: Sprint 0 - Inicialización

---

## 📋 BACKLOG

| ID | Tarea | Prioridad | Asignado | ICE Score |
|----|-------|-----------|----------|-----------|

---

## 🎯 TO DO

| ID | Tarea | Prioridad | Asignado | Bloqueado por |
|----|-------|-----------|----------|---------------|

---

## 🔄 IN PROGRESS

| ID | Tarea | Asignado | Inicio | Notas |
|----|-------|----------|--------|-------|

---

## 👀 CODE REVIEW

| ID | Tarea | Desarrollador | Revisor | PR/Branch |
|----|-------|---------------|---------|-----------|

---

## ✅ DONE

| ID | Tarea | Completado | Tiempo |
|----|-------|------------|--------|

---

## 📝 Instrucciones para Agentes
1. **Mover tarea a IN PROGRESS**: Copiar la fila de TO DO a IN PROGRESS, añadir fecha de inicio.
2. **Mover a CODE REVIEW**: Cuando termines, mueve la tarea aquí e indica el branch/PR.
3. **Mover a DONE**: Tras la revisión, mueve aquí con fecha de completado.
EOF

cat > "$PROJECT_PATH/docs/backlog.md" << 'EOF'
# Product Backlog

| ID | Historia de Usuario | Prioridad | Estado | Squad |
|----|---------------------|-----------|--------|-------|
| 1  | Inicializar proyecto base | Alta | Pendiente | Alpha |
EOF

cat > "$PROJECT_PATH/docs/architecture/decisions.md" << 'EOF'
# Architecture Decision Records (ADR)

## ADR-001: Estructura de Agentes
- **Estado**: Aceptado
- **Contexto**: El desarrollo será autónomo con agentes de IA.
- **Decisión**: Usar roles definidos (PO, Arquitecto, Dev, Security, Tech Lead, Business Strategist) en `.squads/shared/roles`.
EOF

cat > "$PROJECT_PATH/docs/governance/kanban-protocol.md" << 'EOF'
# Protocolo de Gestión del Kanban

## Fuente de Verdad
El archivo **`docs/kanban.md`** es la fuente de verdad para el estado de las tareas.

## Columnas del Kanban

| Columna | Significado | Quién mueve aquí |
|---------|-------------|------------------|
| **BACKLOG** | Ideas y tareas futuras | PO, Business Strategist |
| **TO DO** | Priorizadas para el sprint actual | PO |
| **IN PROGRESS** | Activamente en desarrollo | Developer (al empezar) |
| **CODE REVIEW** | Esperando revisión | Developer (al terminar) |
| **DONE** | Completado y validado | Tech Lead / Security |

## Límites WIP (Work In Progress)
- Máximo **3 tareas** en IN PROGRESS por Squad
- Máximo **5 tareas** en CODE REVIEW total
EOF

cat > "$PROJECT_PATH/docs/governance/autonomous-maintenance.md" << 'EOF'
# Protocolo de Mantenimiento Autónomo

## Objetivo
Lograr que la refactorización y la coherencia documental ocurran sin intervención directa del usuario.

## Flujo de Trabajo Autónomo

### 1. El Ciclo de "Auto-Fix"
Cada vez que un agente Desarrollador termina una tarea, DEBE ejecutar:
`./bin/auto-fix.sh`

### 2. El Guardián de la Documentación (Tech Lead)
El agente Tech Lead tiene la responsabilidad de ejecutar periódicamente la tarea "Unificación Documental":
1.  Leer todos los archivos en `docs/`.
2.  Identificar secciones repetidas o contradictorias.
3.  Fusionar contenido en una "Fuente de Verdad" única.

### 3. Regla de "No Duplicidad"
- Si un agente necesita explicar cómo funciona algo, PRIMERO busca si ya existe en `docs/`.
- Si existe, lo referencia (link).
- Si no existe, lo crea en el lugar adecuado.
EOF

echo "✅ Documentación de gobernanza creada"

# ============================================
# SCRIPTS
# ============================================

cat > "$PROJECT_PATH/bin/auto-fix.sh" << 'EOF'
#!/bin/bash

# Auto Fix Script
# Ejecuta las herramientas de calidad de código automáticamente.

echo "⚓️ Iniciando Protocolo de Mantenimiento Autónomo..."

# Detectar el stack y ejecutar herramientas correspondientes

# PHP/Laravel
if [ -f ./vendor/bin/pint ]; then
    echo "🎨 Ejecutando Laravel Pint..."
    ./vendor/bin/pint
fi

if [ -f ./vendor/bin/rector ]; then
    echo "🔧 Ejecutando Rector..."
    ./vendor/bin/rector process
fi

if [ -f ./vendor/bin/phpstan ]; then
    echo "🔍 Ejecutando PHPStan..."
    ./vendor/bin/phpstan analyse
fi

# JavaScript/TypeScript
if [ -f ./node_modules/.bin/eslint ]; then
    echo "🎨 Ejecutando ESLint..."
    ./node_modules/.bin/eslint --fix .
fi

if [ -f ./node_modules/.bin/prettier ]; then
    echo "✨ Ejecutando Prettier..."
    ./node_modules/.bin/prettier --write .
fi

echo "✅ Mantenimiento finalizado."
EOF

chmod +x "$PROJECT_PATH/bin/auto-fix.sh"

echo "✅ Scripts de automatización creados"

# ============================================
# README
# ============================================

cat > "$PROJECT_PATH/README.md" << EOF
# $PROJECT_NAME

## Descripción
Proyecto desarrollado de forma autónoma por squads de agentes de IA usando el framework ANCLA.

## Estructura de Squads
- **Squad Alpha**: Core/Backend
- **Squad Beta**: Frontend/UI
- **Squad Omega**: Testing/Seguridad/DevOps

## Agentes y Roles
Los perfiles de los agentes están definidos en \`.squads/shared/roles/\`:
- [Product Owner](.squads/shared/roles/product-owner.md)
- [Business Strategist](.squads/shared/roles/business-strategist.md)
- [Arquitecto](.squads/shared/roles/architect.md)
- [Full Stack Developer](.squads/shared/roles/fullstack-developer.md)
- [Tech Lead & QA](.squads/shared/roles/tech-lead.md)
- [Experto en Seguridad](.squads/shared/roles/security-expert.md)

## Kanban
Ver el estado de las tareas en [docs/kanban.md](docs/kanban.md).

## Flujo de Trabajo
1.  **Business Strategist** evalúa rentabilidad de funcionalidades.
2.  **PO** define historias en el backlog.
3.  **Arquitecto** diseña la solución técnica.
4.  **Squads** implementan las microtareas.
5.  **Tech Lead** revisa y refactoriza.
6.  **Seguridad** audita los cambios.
EOF

echo "✅ README creado"

# ============================================
# FINALIZACIÓN
# ============================================

echo ""
echo "============================================"
echo "⚓️ Proyecto $PROJECT_NAME inicializado!"
echo "============================================"
echo ""
echo "Próximos pasos:"
echo "  1. cd $PROJECT_PATH"
echo "  2. Abre VS Code: code ."
echo "  3. Instala Roo Code si no lo tienes"
echo "  4. Comienza a trabajar con los agentes!"
echo ""
