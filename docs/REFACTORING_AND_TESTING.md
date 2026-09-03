# Deuda tecnica y estado de los tests

Documento vivo. Se actualiza cada vez que se paga o se contrae deuda.

> Ultima medicion: 2026-09-03, tras instalar el analisis estatico real.

## Estado de un vistazo

| Puerta | Estado |
|---|---|
| Pint (`composer pint:test`) | Verde |
| PHPStan nivel 6 (`composer stan`) | Verde sobre baseline de 928 incidencias |
| `composer audit --no-dev` | Verde, sin avisos |
| PHPUnit (`composer test`) | **Rojo: 93 de 657 en local. Eran 594 en serie / 174 en paralelo.** |

## 1. Suite de tests en rojo (prioridad maxima)

### 1.1 Tres causas transversales, ya resueltas

El grueso del rojo no eran 174 problemas distintos, sino tres:

**a) El job de CI nunca llego a ejecutar un test.** `ci.yml` corria
`php artisan test --parallel` sin `brianium/paratest` instalado, asi que
Collision abortaba antes de arrancar. El CI llevaba rojo al menos 8 commits
por una dependencia que faltaba.

**b) `Mockery::close()` antes de `parent::tearDown()`.** Once clases repetian
este patron:

```php
protected function tearDown(): void
{
    Mockery::close();      // si un mock incumple una expectativa, lanza AQUI
    parent::tearDown();    // no llega -> RefreshDatabase no deshace nada
}
```

Laravel ya llama a `Mockery::close()` en `tearDownTheTestEnvironment()`,
**despues** de `callBeforeApplicationDestroyedCallbacks()`, que es donde
`RefreshDatabase` deshace su transaccion. Adelantarlo dejaba la transaccion
abierta y todos los tests siguientes morian en `setUp` con
`PDOException: There is already an active transaction`. En serie, 582 de 594
fallos eran esa cascada.

**c) Dos bugs de produccion que los tests detectaban correctamente**, ambos
en el archivo a largo plazo: la construccion invalida de
`ChainVerificationResult` y los discos de S3 sin declarar ni adaptador
instalado. Ver seccion 1.4.

Resultado acumulado:

```
serie     594 fallos -> 93
paralelo  174 fallos -> 93
```

Que ambos modos coincidan es la senal de que ya no hay contaminacion entre
tests: los 93 restantes son fallos reales, uno a uno.

### 1.2 Ejecutar en paralelo

`composer test` usa `--parallel`, igual que el CI. Cada proceso recibe su
propia base de datos. Medir en serie ya no distorsiona, pero el paralelo es
mucho mas rapido.

### 1.3 Estado real

```
local (SQLite)  657 tests - 44 errores - 49 fallos    = 93
CI    (MySQL)   657 tests - 63 errores - 50 fallos    = 113
```

**La cifra buena es la del CI**: produccion es MySQL. Los 20 fallos extra no
son ruido del entorno, son bugs reales que SQLite tolera en silencio y MySQL
rechaza:

| Error solo en MySQL | Veces | Que significa |
|---|---|---|
| `Data truncated for column 'status'` | 10 | Se escribe un valor que no esta en el ENUM de la columna. SQLite lo acepta; MySQL lo trunca y avisa. |
| `Incorrect string value: '\xFF\x99...'` | 10 | Se guardan bytes binarios en una columna de texto utf8mb4. Deberia ir en base64 o en una columna binaria. |
| `Unknown column 'action' in 'where clause'` | 1 | Una consulta filtra por una columna que no existe. |

Correr los tests en SQLite mientras produccion es MySQL **oculta bugs de
produccion**. Conviene apuntar `phpunit.xml` a MySQL, con una BD aislada
`firmalum_test` como pide el estandar de la casa.

Reparto por fichero (menciones en la salida, como orden de magnitud):

| Fichero | Peso |
|---|---|
| `tests/Feature/SigningProcess/CreateSigningProcessTest.php` | 20 |
| `tests/Feature/Signing/SigningAccessTest.php` | 11 |
| `tests/Feature/Signing/SignatureCreationTest.php` | 10 |
| `tests/Unit/Document/DocumentUploadServiceTest.php` | 7 |
| `tests/Unit/Evidence/EvidenceDossierServiceTest.php` | 6 |
| `tests/Unit/Document/FinalDocumentServiceTest.php` | 5 |
| `tests/Feature/Otp/OtpVerificationTest.php` | 5 |
| `tests/Feature/Document/FinalDocumentGenerationTest.php` | 5 |
| `tests/Feature/Settings/UserManagementTest.php` | 3 |
| `tests/Feature/AuditTrailIntegrationTest.php` | 3 |

### 1.4 Bugs de produccion destapados por la suite

| Bug | Efecto |
|---|---|
| `TsaResealService::verifyChain()` construia `ChainVerificationResult` con cuatro parametros con nombre inexistentes | La funcion **siempre lanzaba `Error`**. La verificacion de integridad de la cadena de sellos de tiempo, que sostiene la promesa probatoria, nunca funciono. |
| `league/flysystem-aws-s3-v3` no era dependencia | `.env.production` fija `FILESYSTEM_DISK=s3`: **toda** lectura o escritura de documentos en produccion fallaba con "Class not found". |
| Discos `s3-glacier` y `s3-deep-archive` sin declarar | Mover un documento a nivel frio o de archivo lanzaba excepcion. |
| `SendCancellationNotificationJob.php` empezaba por `2<?php` | PHP emitia un `2` al output al autocargar la clase. |
| `markAsError()` dentro de `DB::transaction()` en `DocumentUploadService:176` | El rollback lo deshace junto al `Document::create()`. Una subida fallida no deja **ni fila ni estado de error**, solo la linea de log. **Sin arreglar**: es un cambio de comportamiento que merece decision propia. |

### 1.5 Desajustes de configuracion pendientes

`.env.production` usa `AWS_REGION`, `AWS_S3_KEY` y `AWS_S3_SECRET`, pero
`config/filesystems.php` lee `AWS_DEFAULT_REGION`, `AWS_ACCESS_KEY_ID` y
`AWS_SECRET_ACCESS_KEY`. **Los nombres no coinciden**, asi que aun con el
adaptador instalado las credenciales no se recogen. Hay que alinearlos antes
del proximo despliegue.

### Orden sugerido para lo que queda

1. `CreateSigningProcessTest` y los dos de `Signing/`: 41 de los 93.
2. `DocumentUploadServiceTest` y `FinalDocumentServiceTest`.
3. El resto, de arriba abajo por la tabla de 1.3.
4. Migrar las 698 anotaciones `@test` a atributos de PHPUnit 11.
5. Anadir `Tests (PHP 8.2)` a los checks requeridos de `main` (seccion 3).

## 2. Baseline de PHPStan

`phpstan-baseline.neon` congela **892 incidencias** en nivel 6 (eran 928;
las 36 que faltan se han arreglado, no silenciado). Solo bloquean
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
