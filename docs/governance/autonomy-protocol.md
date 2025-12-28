# Protocolo de Autonomía del Sistema

## Señales de Transición
Cada agente debe terminar su trabajo con una señal clara que indica al siguiente qué hacer:

| Agente | Señal de Finalización | Siguiente Agente |
|--------|----------------------|------------------|
| Product Owner | `LISTO PARA ARQUITECTO` | Arquitecto |
| Arquitecto | `LISTO PARA DESARROLLO` | Developer |
| Developer | `LISTO PARA REVIEW` | Tech Lead |
| Tech Lead | `REVIEW COMPLETADO` | Security (opcional) → PO |
| Security | `AUDITORÍA COMPLETADA` | Tech Lead (si hay fixes) |
| Business Strategist | `PRIORIDADES ACTUALIZADAS` | Product Owner |

## Modo Orquestador
El agente **Orchestrator** puede ejecutar todo el ciclo de forma autónoma:

1. Lee el estado actual del Kanban
2. Identifica qué agente debe actuar
3. Ejecuta la acción correspondiente
4. Repite hasta completar el sprint

### Comando para Iniciar Autonomía
Selecciona el modo **🎯 Orchestrator** y di:

```
Ejecuta un ciclo completo de desarrollo autónomo.
Lee el Kanban y el backlog, y procesa todas las tareas pendientes.
```

## Límites de Autonomía
El sistema se detendrá y pedirá intervención humana cuando:

- [ ] El backlog esté vacío y no haya tareas en progreso
- [ ] Haya un error de compilación o test que no pueda resolver
- [ ] Una decisión de negocio requiera validación (ej: cambio de arquitectura mayor)
- [ ] Se detecte una vulnerabilidad de seguridad crítica

## Archivos Clave que los Agentes Deben Mantener

| Archivo | Propósito | Quién lo actualiza |
|---------|-----------|-------------------|
| `docs/kanban.md` | Estado de tareas | Todos |
| `docs/backlog.md` | Historias de usuario | PO, Business |
| `docs/architecture/decisions.md` | Decisiones técnicas | Arquitecto |
| `docs/architecture/*.md` | Diseños específicos | Arquitecto |
