# ADR-008: Estrategia Híbrida de Sellado de Tiempo (TSA)

- **Estado**: Aceptado
- **Fecha**: 2025-12-28
- **Backlog Items**: E1-001, E1-006, E3-004
- **Autor**: Arquitecto de Software
- **Prioridad**: ALTA

## Contexto

El Reglamento eIDAS y la normativa de firma electrónica avanzada valoran el uso de Sellos de Tiempo Cualificados (Qualified Electronic Time Stamps) para garantizar la "fecha cierta" y la integridad a largo plazo (LTV).

Sin embargo, el coste de un sello cualificado (emitido por un QTSP como DigiCert, ANF, etc.) oscila entre $0.15 y $0.50 por unidad. Un proceso de firma típico genera múltiples eventos (envío, apertura, lectura, consentimiento, firma), lo que haría inviable económicamente sellar cada evento con un QTSP para planes básicos o freemium.

Necesitamos una estrategia que equilibre:
1.  **Coste**: Viabilidad del modelo de negocio.
2.  **Seguridad Jurídica**: Validez legal de las evidencias.
3.  **Cumplimiento**: Adherencia a eIDAS.

## Decisión

Adoptar una **Estrategia Híbrida de TSA** que diferencia entre "Eventos de Auditoría" y "Cierre de Documento".

### 1. Niveles de Sellado

Definimos dos niveles de autoridad de tiempo:

| Nivel | Tecnología | Proveedor | Coste | Uso |
|-------|------------|-----------|-------|-----|
| **Nivel 1: Standard TSA** | RFC 3161 (Self-hosted o Free) | Servidor Interno / Free TSA | ~$0.00 | Eventos intermedios del Audit Trail (Log de auditoría) |
| **Nivel 2: Qualified TSA** | RFC 3161 (eIDAS Qualified) | QTSP (DigiCert, ANF, etc.) | ~$0.15+ | Cierre final del documento (PAdES-LTV) y Dossier Probatorio |

### 2. Aplicación por Tipo de Evento

-   **Eventos Intermedios (Audit Trail)**:
    -   *Ejemplos*: "Email enviado", "Documento visto", "Checkbox marcado".
    -   *Tratamiento*: Se sellan con **Standard TSA**.
    -   *Justificación*: La integridad se garantiza mediante el encadenamiento de hashes (Blockchain-like). Si el hash final está sellado cualificadamente, toda la cadena anterior queda protegida indirectamente.

-   **Evento Final (Firma y Cierre)**:
    -   *Ejemplos*: "Firma aplicada al PDF", "Generación de Dossier".
    -   *Tratamiento*: Se sellan con **Qualified TSA**.
    -   *Justificación*: Es el momento crítico que requiere "fecha cierta" oponible a terceros e inversión de la carga de la prueba.

### 3. Configuración por Plan (Tenant)

La arquitectura permitirá configurar el proveedor de TSA a nivel de Tenant o Plan:

-   **Plan Básico**: Standard TSA para todo (sin validez LTV cualificada, solo integridad técnica).
-   **Plan Pro/Enterprise**: Standard TSA para traza + Qualified TSA para cierre.

## Consecuencias

### Positivas
-   **Reducción drástica de costes**: De ~$1.50/doc (10 eventos) a ~$0.15/doc (1 evento cualificado).
-   **Escalabilidad**: Podemos procesar millones de eventos de auditoría sin coste marginal.
-   **Flexibilidad**: Permite ofrecer planes de precios diferenciados.

### Negativas
-   **Complejidad técnica**: Necesitamos gestionar múltiples proveedores de TSA y lógica de selección.
-   **Gestión de claves**: Requiere proteger las claves del TSA interno.

## Implementación Técnica

El servicio `TsaService` debe aceptar un parámetro de "nivel" o "importancia":

```php
interface TsaServiceInterface {
    public function stamp(string $hash, TsaLevel $level = TsaLevel::STANDARD): string;
}
```

El `EvidenceDossierService` orquestará la llamada al nivel adecuado al cerrar el paquete.
