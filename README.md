# ANCLA - Proyecto Autónomo

## Descripción
ANCLA es un proyecto desarrollado de forma autónoma por squads de agentes de IA. El objetivo es construir una aplicación robusta utilizando Laravel, Livewire, Tailwind y Blade.

## Estructura de Squads
El trabajo se divide en 3 squads:
- **Squad Alpha**: (Definir enfoque, ej. Core/Backend)
- **Squad Beta**: (Definir enfoque, ej. Frontend/UI)
- **Squad Omega**: (Definir enfoque, ej. Testing/Seguridad/DevOps)

## Agentes y Roles
Los perfiles de los agentes están definidos en `.squads/shared/roles/`:
- [Product Owner](.squads/shared/roles/product-owner.md)
- [Business Strategist](.squads/shared/roles/business-strategist.md)
- [Arquitecto](.squads/shared/roles/architect.md)
- [Full Stack Developer](.squads/shared/roles/fullstack-developer.md)
- [Tech Lead & QA](.squads/shared/roles/tech-lead.md)
- [Experto en Seguridad](.squads/shared/roles/security-expert.md)

## Inicio del Proyecto
Para inicializar el proyecto base (si aún no está hecho):

```bash
# Instalar Laravel (requiere mover archivos si el directorio no está vacío)
composer create-project laravel/laravel ancla-app
mv ancla-app/* .
mv ancla-app/.* .
rmdir ancla-app
```

## Flujo de Trabajo
1.  **PO** define historias en el backlog.
2.  **Arquitecto** diseña la solución técnica.
3.  **Squads** implementan las microtareas.
4.  **Seguridad** audita los cambios.
