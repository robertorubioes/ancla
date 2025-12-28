# Architecture Decision Records (ADR)

## ADR-001: Selección del Stack Tecnológico
- **Estado**: Aceptado
- **Contexto**: Necesitamos un framework robusto y rápido para desarrollo web.
- **Decisión**: Usar Laravel, Livewire, Tailwind CSS y Blade.
- **Consecuencias**: Desarrollo rápido, pero requiere conocimiento específico de Livewire.

## ADR-002: Estructura de Agentes
- **Estado**: Aceptado
- **Contexto**: El desarrollo será autónomo.
- **Decisión**: Usar roles definidos (PO, Arquitecto, Dev, Seguridad) en `.squads/shared/roles`.
