# Deuda tecnica y estado de los tests

Documento vivo. Se actualiza cada vez que se paga o se contrae deuda.

> Ultima medicion: 2026-09-03, tras instalar el analisis estatico real.

## Estado de un vistazo

| Puerta | Estado |
|---|---|
| Pint (`composer pint:test`) | Verde |
| PHPStan nivel 6 (`composer stan`) | Verde sobre baseline de 928 incidencias |
| `composer audit --no-dev` | Verde, sin avisos |
| PHPUnit (`composer test`) | **Rojo: ~100 tests fallando de 657** |

## 1. Suite de tests en rojo (prioridad maxima)

El CI lleva rojo al menos desde los ultimos 8 commits de `staging`. No es un
fallo aislado: la suite arrastra dos problemas distintos.

### 1.1 Fallos reales

Ejecutando fichero a fichero (para evitar la contaminacion descrita abajo),
22 de los 45 ficheros de test tienen fallos o errores propios. Los mas
gruesos:

| Fichero | Estado |
|---|---|
| `tests/Feature/SigningProcess/CreateSigningProcessTest.php` | 18 errores, 2 fallos de 20 |
| `tests/Feature/Signing/SignatureCreationTest.php` | 8 errores, 7 fallos de 21 |
| `tests/Unit/Document/DocumentUploadServiceTest.php` | 7 errores, 2 fallos de 13 |
| `tests/Feature/Signing/SigningAccessTest.php` | 10 fallos de 21 |
| `tests/Unit/Evidence/EvidenceDossierServiceTest.php` | 3 errores, 3 fallos de 17 |
| `tests/Feature/Otp/OtpVerificationTest.php` | 5 fallos de 20 |
| `tests/Unit/Archive/TsaResealServiceTest.php` | 4 errores de 6 |
| `tests/Unit/Document/FinalDocumentServiceTest.php` | 5 errores, 1 fallo de 16 |

Causas identificadas hasta ahora:

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
  corregidas las de `requestTimestamp`; pueden quedar mas.

### 1.2 Contaminacion entre tests

Ejecutando la suite completa, **582 de los 594 fallos son cascada** de un
unico `PDOException: There is already an active transaction`. El primer test
que falla deja abierta una transaccion sobre la conexion SQLite `:memory:`
compartida, y a partir de ahi todos los demas revientan en el `setUp`.

Esto hace que la salida de `php artisan test` sea ilegible: no se puede saber
que esta roto de verdad sin ejecutar fichero a fichero.

Ya se ha endurecido el manejo de transacciones manuales (capturar `\Throwable`
en lugar de `\Exception`, commit `2cb6e62`), pero **no basta**: la fuga sigue
apareciendo a partir de `tests/Unit/Document/`. Queda por localizar el punto
exacto.

### Orden sugerido para pagarlo

1. Localizar y cerrar la fuga de transaccion (1.2). Sin esto no se puede
   medir el progreso.
2. Configurar los discos de test que faltan.
3. Arreglar `ChainVerificationResult::$isValid` — es un bug real.
4. Ir fichero a fichero por la tabla de 1.1.
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
