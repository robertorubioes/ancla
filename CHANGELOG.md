# Changelog

Formato basado en [Keep a Changelog](https://keepachangelog.com/es-ES/1.1.0/).

## [No publicado]

### Seguridad

- Las credenciales del superadministrador salen del codigo. `SuperadminSeeder`
  cableaba el correo real y la contrasena en claro, la imprimia por consola y
  la repetia en la documentacion, en un repositorio publico. Ahora se leen de
  `SUPERADMIN_EMAIL` / `SUPERADMIN_NAME` / `SUPERADMIN_PASSWORD`; en produccion
  y staging el seeder aborta si falta la contrasena.
- La clave privada de firma deja de estar versionada.
  `storage/certificates/` esta ignorado y `scripts/generate-dev-certificate.sh`
  genera el certificado de desarrollo.
- Actualizadas las dependencias: 50 avisos de `composer audit` (14 de
  severidad alta) reducidos a cero.
- `composer audit --no-dev` pasa a bloquear el CI.

> Tanto la contrasena como la clave siguen siendo recuperables del historico
> de git. **Deben rotarse.**

### Anadido

- Comando `superadmin:rotate-password`: rota la contrasena de un
  superadministrador y la muestra una sola vez, sin pasarla por argumento.
  Cubierto por 8 tests.
- `config/services.php` acepta `AWS_SES_KEY`, `AWS_SES_SECRET` y
  `AWS_SES_REGION`, con respaldo a las compartidas: antes SES y el disco s3
  se peleaban por las mismas tres variables.
- `docs/admin/rotacion-de-credenciales.md`.
- `docs/ENTORNOS.md` y `scripts/clone-prod-to-test.sh`: los tres entornos
  (local en `.test`, testing bajo `test.<dominio>`, produccion) y el clon
  nocturno anonimizado de produccion a testing.
- Analisis estatico real: `larastan/larastan` en nivel 6, con la deuda
  existente congelada en `phpstan-baseline.neon` (892 incidencias).
- `brianium/paratest`, sin el cual el job de tests del CI no arrancaba.
- `league/flysystem-aws-s3-v3`, sin el cual ningun disco S3 funcionaba.
- Hooks locales (`scripts/install-hooks.sh`): `pre-commit` con Pint + PHPStan
  y `commit-msg` con prefijo convencional.
- Backups scriptados y verificados: `scripts/backup.sh`,
  `scripts/backup-verify.sh` y `scripts/restore.sh`, con `.env` cifrado en
  AES-256, manifiesto JSON, subida a S3 y retencion.
- Proteccion de la rama `main`: PR obligatorio, historia lineal, sin force
  push y con `Code Style & Linting` y `Static Analysis (PHPStan)` como checks
  requeridos.
- Documentacion: `docs/INDEX.md`, `docs/SYSTEM_OVERVIEW.md`,
  `docs/BACKUPS.md`, `docs/REFACTORING_AND_TESTING.md`, `CHANGELOG.md` y un
  `README.md` propio del proyecto.
- Scripts de composer: `pint`, `pint:test`, `stan`, `stan:baseline` y
  `quality`.

### Corregido

- **El host principal respondia 404.** `IdentifyTenant` trataba `app` como
  subdominio de tenant, de modo que `app.firmalum.test` (y `app.firmalum.com`
  en cuanto se fijase `APP_BASE_DOMAIN`) buscaba un tenant llamado "app" y no
  lo encontraba. Ahora `app` es subdominio de plataforma y en el host
  principal el tenant se resuelve por el usuario autenticado.
- **La captura de evidencias al firmar nunca se ejecuto.**
  `SignatureService::captureSignatureEvidences()` llamaba a seis metodos que
  no existen y escribia en cinco columnas que tampoco: `EvidencePackage` es
  polimorfico, `document_hash` y `audit_trail_hash` son NOT NULL, y los
  estados `active`/`sealed` no estan en el enum. Ninguna firma llego nunca a
  producir un paquete de evidencias.
- `AuditTrailService::log()` no existe y se llamaba desde `SendOtpCodeJob` en
  sus tres caminos, incluido `failed()`.
- `AuditTrailService::logEvent()` solo escribe en `laravel.log`: no esta
  encadenado por hash ni sellado por TSA, y no entra en el dossier. La
  creacion de un proceso de firma pasa a registrarse con `record()`.
- **La verificacion de cadenas TSA nunca funciono.**
  `TsaResealService::verifyChain()` construia `ChainVerificationResult` con
  cuatro parametros con nombre que el DTO no tiene, asi que siempre lanzaba
  `Error`. Es la comprobacion de integridad que sostiene la promesa
  probatoria del archivo a largo plazo.
- **Ningun disco S3 funcionaba**: `league/flysystem-aws-s3-v3` no era
  dependencia del proyecto, y `.env.production` fija `FILESYSTEM_DISK=s3`.
  Ademas, los discos `s3-glacier` y `s3-deep-archive` que referencia
  `config/archive.php` no estaban declarados en `config/filesystems.php`.
- Once clases de test llamaban a `Mockery::close()` antes de
  `parent::tearDown()`, impidiendo el rollback de `RefreshDatabase`. La
  suite pasa de 594 fallos en serie a 93.
- `SendCancellationNotificationJob.php` empezaba por `2<?php`, de modo que PHP
  emitia un `2` al output cada vez que se autocargaba la clase.
- Los bloques con `DB::beginTransaction()` solo capturaban `\Exception`: un
  `\Error` escapaba sin `rollBack()` y dejaba la transaccion abierta.
- Las expectativas de Mockery sobre `requestTimestamp` esperaban un solo
  argumento desde que se anadio `tenant_id`.
- El pipeline de CI dejaba pasar todo: PHPStan corria con `continue-on-error`
  y sin estar instalado, `composer audit` era decorativo, y los PR contra
  `staging` (la rama de trabajo real) no disparaban nada.
- El job de tests **nunca llego a ejecutar un test**: corria
  `php artisan test --parallel` sin `brianium/paratest` instalado, y Collision
  abortaba antes de arrancar. Ya esta instalado.
- `config.platform.php` fijado a 8.2.0: el lock se resolvia contra la version
  de PHP del desarrollador y no era instalable en el CI.

### Cambiado

- Las guias de despliegue documentaban `AWS_REGION`, `AWS_S3_KEY` y
  `AWS_S3_SECRET`, que la aplicacion no lee. Corregidas a los nombres
  estandar de Laravel.
- `User::$role` se documenta como `UserRole`, no como `string`. Eso deja al
  descubierto varias ramas defensivas que ya no podian darse
  (`instanceof UserRole` siempre cierto, comparaciones de enum con cadena
  siempre falsas) y se eliminan.
- Renombrado ANCLA a **Firmalum** en codigo y documentacion. `base_domain`
  por defecto pasa de `ancla.app` a `firmalum.com`.
- `config/evidence.php`: `include_ancla_logo` -> `include_firmalum_logo`.
- Reformateado todo el codigo con Pint 1.30.

> Siguen con el nombre antiguo, por tocar datos o infraestructura desplegada:
> el slug `ancla-admin` del tenant interno, la BD `ancla_production`, el
> supervisor `ancla-worker`, las rutas `/var/www/ancla` y el repositorio.

### Pendiente

- La suite de tests esta en rojo: 71 de 657 (30 errores, 41 fallos). Ver
  [`docs/REFACTORING_AND_TESTING.md`](docs/REFACTORING_AND_TESTING.md).
- `Tests (PHP 8.2)` no es todavia check requerido de `main` por ese motivo.
