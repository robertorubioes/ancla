# Backups

Los datos de Firmalum son prueba legal: un backup no verificado no existe.
Todo el ciclo esta scriptado en `scripts/` y no tiene nada cableado — la
configuracion sale del `.env`.

| Script | Que hace |
|---|---|
| `scripts/backup.sh` | Volcado + `.env` cifrado + manifiesto, sube a S3 y aplica retencion. Verifica al terminar. |
| `scripts/backup-verify.sh` | Comprueba gzip, tablas criticas y restauracion de prueba. |
| `scripts/restore.sh` | Restaura un volcado, verificandolo antes. |

## Configuracion

```bash
# .env
DB_HOST=...
DB_PORT=3306
DB_DATABASE=...
DB_USERNAME=...
DB_PASSWORD=...

BACKUP_LOCAL_DIR=storage/backups
BACKUP_PATH=backups/database       # prefijo dentro del bucket
BACKUP_RETENTION_DAYS=30
BACKUP_ENCRYPTION_KEY=             # obligatorio, exclusivo de este proyecto
AWS_BUCKET=                        # vacio -> el backup se queda en local
```

`BACKUP_ENCRYPTION_KEY` cifra el `.env` que acompana al volcado. **No se
comparte entre proyectos ni tiene valor por defecto**: si falta, el script
aborta.

## Uso

```bash
# Backup del entorno actual
scripts/backup.sh

# Backup de produccion desde la propia maquina de produccion
scripts/backup.sh --env-file .env.production

# Verificar un backup existente
scripts/backup-verify.sh storage/backups/firmalum-20260903T114915Z.sql.gz

# Restaurar (pide confirmacion escribiendo el nombre de la BD)
scripts/restore.sh storage/backups/firmalum-20260903T114915Z.sql.gz

# Restaurar sobre una BD distinta, sin preguntar
scripts/restore.sh <dump> --target firmalum_staging --yes

# Recuperar el .env del backup
openssl enc -d -aes-256-cbc -pbkdf2 \
  -in storage/backups/firmalum-20260903T114915Z.env.enc \
  -out .env.recuperado
```

## Que produce cada ejecucion

```
<db>-<timestamp>.sql.gz           mysqldump --single-transaction comprimido
<db>-<timestamp>.env.enc          .env cifrado con AES-256-CBC (pbkdf2)
<db>-<timestamp>.manifest.json    timestamp, host, BD, tamanos y sha256
```

## Que NO se respalda aqui

Los documentos firmados y los dossieres probatorios viven en S3 y tienen su
propio versionado. No se duplican en el backup de base de datos.

## Verificacion

`backup-verify.sh` hace tres comprobaciones, en orden:

1. **Integridad del gzip** (`gzip -t`).
2. **Tablas criticas presentes**: `tenants`, `users`, `documents`,
   `signing_processes`, `signers`, `signed_documents`, `audit_trail_entries`,
   `evidence_packages`, `tsa_tokens`, `verification_codes`. Si falta
   cualquiera, el backup no sirve para reconstruir la plataforma.
3. **Restauracion de prueba** en una base de datos desechable que se destruye
   al terminar (pasa a verificacion parcial si no hay cliente `mysql`).

`backup.sh` la invoca automaticamente al final, y `restore.sh` antes de
tocar nada.

## Automatizacion

```cron
# Diario a las 02:00
0 2 * * * cd /var/www/firmalum && scripts/backup.sh >> storage/logs/backup.log 2>&1
```

Revisa `storage/logs/backup.log` periodicamente: un backup que falla en
silencio es peor que no tenerlo.
