# Protocolo de Mantenimiento Autónomo

## Objetivo
Lograr que la refactorización y la coherencia documental ocurran sin intervención directa del usuario ("Zero-Touch Maintenance").

## Herramientas de Automatización
El proyecto utiliza herramientas estándar para aplicar correcciones automáticamente:
1.  **Laravel Pint**: Para corrección de estilo de código (PSR-12).
2.  **Rector**: Para refactorización lógica y actualizaciones de PHP/Laravel.
3.  **Larastan**: Para análisis estático y detección de bugs.

## Flujo de Trabajo Autónomo

### 1. El Ciclo de "Auto-Fix"
Cada vez que un agente Desarrollador termina una tarea, DEBE ejecutar el script de auto-corrección:
`./bin/auto-fix.sh`

Este script se encarga de:
- Formatear el código.
- Aplicar reglas de refactorización (DRY, Modernización).
- Detectar errores estáticos.

### 2. El Guardián de la Documentación (Tech Lead)
El agente Tech Lead tiene la responsabilidad de ejecutar periódicamente la tarea "Unificación Documental":
1.  Leer todos los archivos en `docs/`.
2.  Identificar secciones repetidas o contradictorias.
3.  Fusionar contenido en una "Fuente de Verdad" única.
4.  Eliminar archivos obsoletos.

### 3. Regla de "No Duplicidad"
- Si un agente necesita explicar cómo funciona algo, PRIMERO busca si ya existe en `docs/`.
- Si existe, lo referencia (link).
- Si no existe, lo crea en el lugar adecuado, NO en un comentario de código ni en un archivo temporal.

## Configuración de Rector (Sugerida)
El Tech Lead debe mantener el archivo `rector.php` con reglas como:
- `SetList::LARAVEL_100`
- `SetList::CODE_QUALITY`
- `SetList::DEAD_CODE`
