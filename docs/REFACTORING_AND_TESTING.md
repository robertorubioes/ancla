# Deuda tecnica y estado de los tests

Documento vivo. Se actualiza cada vez que se paga o se contrae deuda.

> Ultima medicion: 2026-09-03, tras instalar el analisis estatico real.

## Estado de un vistazo

| Puerta | Estado |
|---|---|
| Pint (`composer pint:test`) | Verde |
| PHPStan nivel 6 (`composer stan`) | Verde sobre baseline de 928 incidencias |
| `composer audit --no-dev` | Verde, sin avisos |
| PHPUnit (`php artisan test --parallel`) | **Rojo: 174 de 657 (125 errores, 49 fallos)** |

## 1. Suite de tests en rojo (prioridad maxima)

### 1.1 El job de CI nunca llego a ejecutar un test

`ci.yml` corria `php artisan test --parallel` sin que `brianium/paratest`
estuviese instalado, asi que Collision abortaba antes de arrancar:

```
Running Collision 8.x artisan test command in parallel requires at least
ParaTest (brianium/paratest) 7.x.
```

El job llevaba en rojo al menos los ultimos 8 commits de `staging` **por una
dependencia que faltaba, no por tests rotos**. Ya esta instalada.

### 1.2 Ejecutar en paralelo, no en serie

En serie, la suite comparte una conexion SQLite `:memory:`. El primer test que
falla deja una transaccion abierta y a partir de ahi todos los demas revientan
en el `setUp` con `PDOException: There is already an active transaction`: 582
de los 594 fallos son cascada y la salida es ilegible.

Con `--parallel` cada proceso usa su propia base de datos y la cascada
desaparece. **Mide siempre con `--parallel`.**

### 1.3 Estado real

```
Tests: 657 · Errores: 125 · Fallos: 49 · Incompletos: 3 · Pasan: 483
```

Reparto por fichero (numero de menciones en la salida, como orden de
magnitud):

| Fichero | Peso |
|---|---|
| `tests/Feature/Signing/SignatureCreationTest.php` | 21 |
| `tests/Feature/SigningProcess/CreateSigningProcessTest.php` | 20 |
| `tests/Unit/Evidence/IpResolutionServiceTest.php` | 15 |
| `tests/Feature/Signing/SigningAccessTest.php` | 11 |
| `tests/Unit/Verification/QrCodeServiceTest.php` | 10 |
| `tests/Unit/Document/DocumentUploadServiceTest.php` | 10 |
| `tests/Feature/Document/PromoterDownloadTest.php` | 9 |
| `tests/Unit/Evidence/EvidenceDossierServiceTest.php` | 6 |
| `tests/Unit/Archive/TsaResealServiceTest.php` | 6 |
| `tests/Unit/Document/FinalDocumentServiceTest.php` | 5 |
| `tests/Feature/Otp/OtpVerificationTest.php` | 5 |
| `tests/Feature/Notification/DocumentDownloadTest.php` | 5 |
| `tests/Feature/Document/FinalDocumentGenerationTest.php` | 5 |

### 1.4 Causas identificadas

- **Discos no configurados en testing**: `Disk [s3-glacier] does not have a
  configured driver` en `LongTermArchiveServiceTest`. Falta declarar los
  discos de archivo en la configuracion de test.
- **`ChainVerificationResult::$isValid` no existe**:
  `LongTermArchiveService.php:214` lee una propiedad que el DTO no declara.
  Es un bug de produccion, no del test.
- **Mensajes de excepcion desalineados**: `FinalDocumentService` lanza
  "Not all signers have completed signing" donde el test espera
  "No signers found".
- **Expectativas de Mockery desactualizadas** tras cambios de firma. Ya
  corregidas las de `requestTimestamp`; quedan mas.
- **698 avisos de deprecacion de PHPUnit**: la suite usa anotaciones
  `@test` en docblock en lugar de atributos. No rompen, pero conviene migrar.

### Orden sugerido para pagarlo

1. Configurar los discos de test que faltan: es transversal y desbloquea
   varios ficheros de golpe.
2. Arreglar `ChainVerificationResult::$isValid` — es un bug real de
   produccion, no una molestia de test.
3. Ir por la tabla de 1.3 de arriba abajo.
4. Migrar las anotaciones `@test` a atributos de PHPUnit 11.
5. Anadir `Tests (PHP 8.2)` a los checks requeridos de `main`
   (ver seccion 3).

## 2. Baseline de PHPStan

`phpstan-baseline.neon` congela **928 incidencias** en nivel 6. Solo bloquean
los errores nuevos. Reparto aproximado:

| Tipo | Cantidad |
|---|---|
| `missingType.return` / `missingType.parameter` / `missingType.iterableValue` | ~430 |
| `method.notFound` | ~205 |
| `property.notFound` | ~135 |
| Resto (`argument.type`, `nullsafe.neverNull`, ...) | ~160 |

La mayoria son tipos que faltan en firmas y PHPDoc. Para reducirlo: arreglar
un modulo, regenerar el baseline (`composer stan:baseline`) y commitear la
reduccion. **Nunca regenerar el baseline para tapar un error nuevo.**

## 3. Puertas del CI pendientes

`main` esta protegido y exige `Code Style & Linting` y
`Static Analysis (PHPStan)`. **`Tests (PHP 8.2)` no esta exigido todavia**
porque la suite esta en rojo: exigirlo hoy bloquearia cualquier merge.

Cuando la suite este verde:

```bash
gh api -X PATCH repos/robertorubioes/ancla/branches/main/protection/required_status_checks \
  -f 'contexts[]=Code Style & Linting' \
  -f 'contexts[]=Static Analysis (PHPStan)' \
  -f 'contexts[]=Tests (PHP 8.2)' \
  -f 'contexts[]=Security Audit'
```

## 4. Otras desviaciones del estandar

| Desviacion | Nota |
|---|---|
| Scripts repartidos entre `bin/` y `scripts/` | `bin/deploy-production.sh` y `bin/auto-fix.sh` son anteriores al estandar. Falta un `scripts/deploy.sh` idempotente que los sustituya. |
| Identificadores `ancla` en infraestructura | BD `ancla_production`, supervisor `ancla-worker`, rutas `/var/www/ancla`, logs de nginx y el propio repositorio siguen con el nombre viejo. Renombrarlos exige ventana de parada. |
| PHP 8.2 / Node 20 | El estandar de la casa apunta a PHP 8.4 / Node 22. |
| Clave de desarrollo en el historico | `storage/certificates/ancla-dev.key` ya no se trackea, pero sigue siendo recuperable del historico de un repositorio publico. Debe considerarse comprometida. |
| Contrasena del superadmin en el historico | Igual que la anterior: el seeder ya lee del entorno, pero el valor viejo sigue en el historico. **Rotarla en produccion.** |
| Sin `app/Modules/` | Las integraciones externas (TSA, IPinfo, ProxyCheck) viven en `app/Services/`. El estandar pide un modulo propio por integracion. |
