# ADR-011: Plantillas de documentos y API de cumplimentación

> **Status**: Propuesto
> **Fecha**: 2026-09-03
> **Historias relacionadas**: E2-005 (plantillas), API-001 (REST API signing)
> **Sprint**: 7 (plantillas) y 8 (API)

---

## Contexto y Problema

Hoy el promotor sube un PDF ya terminado y lo manda a firmar. Para procesos
repetitivos —un contrato de alquiler, un alta de empleado, un consentimiento
informado— eso obliga a preparar el documento fuera de la plataforma cada vez,
con el consiguiente riesgo de erratas y versiones descontroladas.

Se quieren dos cosas, en este orden:

1. **Plantillas con formulario dinámico**: subir un documento una vez, marcar
   qué partes son variables, y que cada envío se genere rellenando un
   formulario.
2. **Cumplimentación por API**: que un sistema externo mande los valores, se
   genere el documento y arranque el proceso de firma, sin pasar por la web.

La clave es que **son la misma funcionalidad con dos interfaces**. Si el
esquema de campos se diseña una sola vez, el formulario web y el cuerpo JSON
de la API son dos vistas de lo mismo. Si se diseñan por separado, acabaremos
con dos definiciones de campo que divergen: exactamente el "frankenstein" que
el estándar de la casa prohíbe.

---

## Decisión

### D1: Los campos se definen posicionándolos sobre el PDF

Una plantilla es **un PDF inmutable más un esquema de campos**, donde cada
campo lleva su página y sus coordenadas. Al rellenar, los valores se estampan
sobre el PDF con FPDI.

```
PLANTILLA: contrato-alquiler.pdf  (inmutable)
┌──────────────────────────────┐
│ CONTRATO DE ARRENDAMIENTO    │
│                              │
│ Arrendatario: [ nombre     ] │  campo: nombre
│ DNI:          [ dni        ] │  página 1, x=140 y=210, 200x18
│ Renta:        [ importe    ] │  tipo: text | number | date | ...
└──────────────────────────────┘
                │
                ├──> formulario dinámico (web)
                └──> cuerpo JSON (API)
```

**Por qué esta y no otra.** `PdfEmbedder` ya hace exactamente esto para
estampar la firma: importa el PDF con `setasign\Fpdi\Fpdi`, resuelve la página
y dibuja en coordenadas. Rellenar una plantilla es el mismo mecanismo con otro
contenido, así que se reutiliza maquinaria ya probada en producción en vez de
introducir una segunda librería de PDF.

Alternativas descartadas en [Alternativas consideradas](#alternativas-consideradas).

### D2: El esquema de campos es el contrato entre la web y la API

Un único `TemplateField` describe cada campo. El formulario Livewire y el
endpoint de la API **derivan de él**: sus reglas de validación se generan del
mismo sitio, de modo que no puede haber una divergencia entre lo que acepta la
web y lo que acepta la API.

### D3: La plantilla es inmutable; se versiona

Cambiar el PDF o mover un campo **no muta la plantilla**: crea una versión
nueva. Los procesos de firma ya emitidos apuntan a la versión con la que se
generaron.

Esto no es purismo: es un requisito probatorio. Si dentro de tres años hay que
defender un documento firmado, hay que poder demostrar exactamente qué
plantilla y qué valores lo produjeron. Una plantilla mutable haría esa prueba
imposible.

### D4: La API se autentica con tokens por tenant (Sanctum)

No hay hoy ninguna autenticación de API: solo existen los endpoints públicos
de verificación, sin auth. Se añade `laravel/sanctum` con tokens de acceso
personal **emitidos por tenant**, con habilidades (`abilities`) para separar
lectura de creación de procesos.

---

## Modelo de datos

```
tenants
   │
   ├── document_templates            la plantilla, con su PDF base
   │      │  uuid, tenant_id, name, description, status,
   │      │  current_version_id, created_by, timestamps
   │      │
   │      └── document_template_versions      inmutable
   │             │  uuid, document_template_id, version,
   │             │  document_id (el PDF base), created_by, published_at
   │             │
   │             ├── document_template_fields
   │             │      uuid, version_id, key, label, type, required,
   │             │      default_value, options (json), validation (json),
   │             │      page, x, y, width, height, font_size, align, order
   │             │
   │             └── document_template_signers   firmantes previstos
   │                    uuid, version_id, role_key, label, order,
   │                    signature_page, signature_x, signature_y
   │
   └── signing_processes
          + template_version_id (nullable)
          + template_values (json, cifrado)   los valores usados
```

Todas las tablas llevan `tenant_id` y usan el trait `BelongsToTenant`, como el
resto del dominio.

`template_values` guarda lo que se rellenó. Va **cifrado en reposo** con el
trait `Encryptable` (ver [ADR-010](adr-010-encryption-at-rest.md)): son datos
personales —nombres, DNI, importes— y merecen el mismo trato que el documento.

### Tipos de campo

| Tipo | Se estampa como | Validación derivada |
|---|---|---|
| `text` | texto en una línea | `string`, `max` |
| `textarea` | texto con salto de línea dentro de la caja | `string`, `max` |
| `number` | número formateado según locale | `numeric`, `min`, `max` |
| `date` | fecha formateada | `date` |
| `select` | la etiqueta de la opción elegida | `in:` sobre `options` |
| `checkbox` | marca o vacío | `boolean` |
| `signer_name` | se rellena solo con el nombre del firmante | — |
| `signer_email` | ídem con su correo | — |
| `today` | fecha de generación | — |

Los tres últimos son **campos calculados**: no aparecen en el formulario ni se
aceptan por API, los rellena el sistema. Evitan el error clásico de pedir al
usuario que teclee el nombre del firmante que ya conoce la plataforma.

---

## Flujo

### Crear una plantilla (web)

```
1. Subir PDF base          -> DocumentUploadService (el de siempre)
2. Editor visual           -> arrastrar cajas sobre las páginas
3. Definir firmantes       -> roles ("arrendador", "arrendatario") y dónde firman
4. Publicar                -> se crea la versión 1
```

El editor visual renderiza las páginas del PDF y superpone cajas
arrastrables. Solo produce coordenadas: **toda la generación ocurre en el
servidor**, nunca en el navegador.

### Usar una plantilla (web)

```
1. Elegir plantilla        -> se carga el esquema de la versión publicada
2. Formulario dinámico     -> generado del esquema, con sus validaciones
3. Asignar firmantes       -> a cada rol previsto, un nombre y un correo
4. Generar y enviar        -> TemplateRenderService produce el PDF final
                              y desde ahí sigue el flujo de firma actual
```

El PDF generado entra en el sistema como un `Document` normal. **A partir de
ese punto no hay nada nuevo**: firma PAdES, evidencias, verificación pública y
dossier funcionan sin enterarse de que vinieron de una plantilla.

### Usar una plantilla (API, fase 2)

```http
POST /api/v1/signing-processes
Authorization: Bearer <token del tenant>
Content-Type: application/json

{
  "template": "9f2c-...",           uuid de la plantilla
  "values": {
    "nombre":  "María López",
    "dni":     "12345678Z",
    "importe": 850
  },
  "signers": [
    { "role": "arrendatario", "name": "María López", "email": "maria@ejemplo.com" }
  ],
  "send": true
}
```

```http
201 Created
{
  "uuid": "3b1a-...",
  "status": "sent",
  "document": { "uuid": "...", "sha256": "..." },
  "signers": [ { "uuid": "...", "email": "maria@ejemplo.com", "status": "sent" } ],
  "verification_url": "https://app.firmalum.com/verify/ABCD-EFGH-IJKL"
}
```

`values` se valida contra **el mismo esquema** que genera el formulario web.
Un campo que la web rechaza, la API lo rechaza igual, con el mismo mensaje.

Con `"send": false` el proceso queda en borrador, para revisarlo en la web
antes de enviarlo.

---

## Componentes

| Componente | Responsabilidad |
|---|---|
| `app/Models/DocumentTemplate` | La plantilla y su versión publicada |
| `app/Models/DocumentTemplateVersion` | Versión inmutable: PDF base + esquema |
| `app/Models/DocumentTemplateField` | Un campo: tipo, validación y posición |
| `app/Services/Template/TemplateSchema` | Traduce el esquema a reglas de validación. **Única fuente** para web y API |
| `app/Services/Template/TemplateRenderService` | Estampa los valores sobre el PDF con FPDI |
| `app/Services/Template/TemplateVersionService` | Publica versiones; garantiza la inmutabilidad |
| `app/Livewire/Template/TemplateEditor` | Editor visual de campos |
| `app/Livewire/Template/TemplateFill` | Formulario dinámico |
| `app/Http/Controllers/Api/V1/SigningProcessController` | Endpoint de la API (fase 2) |

`TemplateRenderService` comparte con `PdfEmbedder` la mecánica de FPDI. Antes
de duplicarla, se extrae lo común a un `PdfCanvas` en `app/Services/Pdf/`, del
que dependan ambos. Crecer estructurado, no apilar.

---

## Decisiones de diseño

### Coordenadas en milímetros desde arriba a la izquierda

FPDI/FPDF trabaja en milímetros con origen arriba a la izquierda, y
`config/signing.php` ya expresa así la posición de la firma
(`SIGNATURE_X`, `SIGNATURE_Y`). Se mantiene esa convención para no tener dos
sistemas de coordenadas en el mismo proyecto.

El editor visual trabaja en píxeles de pantalla y convierte al guardar,
usando las dimensiones reales de la página.

### El texto que no cabe se reduce, no se desborda

Un campo tiene una caja de tamaño fijo. Si el valor no entra, se reduce el
cuerpo de letra hasta un mínimo configurable; si aun así no cabe, **se aborta
la generación con un error de validación**, en lugar de producir un documento
con texto recortado o superpuesto.

Un contrato con el importe cortado a la mitad es peor que un error.

### Los valores se guardan, no solo el resultado

Se conserva `template_values` junto al proceso. Permite reconstruir el
documento, mostrar en el dossier probatorio qué se rellenó, y reutilizar los
valores para un envío similar.

### La plantilla no se firma; se firma el documento generado

Cada envío produce un `Document` propio, con su hash, su sellado y sus
evidencias. Dos envíos de la misma plantilla son dos documentos
independientes. No se comparte nada entre ellos salvo la referencia a la
versión de plantilla.

---

## Alternativas consideradas

### A1: Campos de formulario del propio PDF (AcroForm)

El cliente prepara el PDF con campos en Acrobat y la plataforma los lee.

**Descartada.** Traslada al cliente un trabajo técnico que no tiene por qué
saber hacer, y FPDI —la librería que ya usamos— no importa campos de
formulario. Habría que añadir una segunda librería de PDF y mantener las dos.

Puede añadirse más adelante como **atajo** para importar posiciones: si el PDF
subido trae AcroForms, precargar las cajas a partir de ellos. Eso es una
mejora del editor, no una arquitectura distinta.

### A2: Plantilla HTML con variables renderizada por dompdf

Más rápida de construir, y dompdf ya está instalado.

**Descartada** como mecanismo principal porque rompe el flujo del producto: el
cliente sube su contrato en PDF, no lo maqueta en HTML. Sigue siendo la opción
natural si algún día se ofrecen plantillas propias de la plataforma.

### A3: Sustituir marcadores de texto (`{{campo}}`) dentro del PDF

**Descartada.** Un PDF no es un formato de texto editable: sustituir una
cadena obliga a reconstruir el flujo de contenido de la página y rompe el
diseño en cuanto el valor tiene otra longitud. Frágil y sin control sobre el
resultado.

---

## Plan por fases

Cada fase es entregable por sí sola y entra por PR con CI verde.

| Fase | Contenido | Historia |
|---|---|---|
| 1 | Modelo de datos, migraciones, `TemplateSchema` y `TemplateRenderService`, con tests | E2-005 |
| 2 | Editor visual de campos y firmantes | E2-005 |
| 3 | Formulario dinámico y generación del proceso | E2-005 |
| 4 | Versionado y publicación | E2-005 |
| 5 | Sanctum, tokens por tenant y gestión desde la web | API-001 |
| 6 | Endpoints REST y su documentación como fuente de verdad | API-001 |

La fase 1 es la que fija el contrato. Si el esquema queda bien, las cinco
siguientes son trabajo mecánico sobre él.

---

## Riesgos

| Riesgo | Mitigación |
|---|---|
| El editor visual es la parte más cara y la más fácil de subestimar | La fase 1 no depende de él: el esquema se puede definir por seeder o JSON y probarse entero antes de escribir una línea de interfaz |
| Divergencia entre validación web y API | Una sola clase, `TemplateSchema`, genera ambas. Un test comprueba que un valor rechazado por la web lo rechaza también la API |
| PDFs con rotación, o tamaños distintos por página | `TemplateRenderService` lee las dimensiones y la rotación de cada página con FPDI y convierte. Casos cubiertos por tests con PDFs reales |
| Abrir una API de escritura amplía la superficie de ataque | Tokens por tenant con habilidades, rate limiting como en los endpoints públicos, y todo lo que entra por la API queda en el audit trail encadenado igual que lo que entra por la web |
| Los valores rellenados son datos personales | Cifrados en reposo con `Encryptable`, y sujetos a las políticas de retención existentes |

---

## Consecuencias

**A favor**

- Los procesos repetitivos dejan de exigir preparar el PDF fuera.
- La API abre la integración con CRM, ERP y portales de cliente, que es lo que
  pide API-001.
- Nada del núcleo de firma cambia: el documento generado entra por donde
  entran hoy los subidos.

**En contra**

- Tres tablas nuevas y un editor visual que mantener.
- El versionado añade una indirección: los procesos apuntan a una versión, no
  a la plantilla.
- Sanctum es una dependencia nueva.

**Que hay que aceptar**

- Las plantillas no se pueden editar "en caliente": publicar cambia la
  versión. Es deliberado, por trazabilidad probatoria.
