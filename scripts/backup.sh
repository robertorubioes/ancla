#!/usr/bin/env bash
#
# Backup de la base de datos de Firmalum.
#
#   scripts/backup.sh [--env-file .env.production]
#
# Idempotente y sin nada cableado: toda la configuracion sale del .env.
#
# Produce, por ejecucion:
#   <BACKUP_LOCAL_DIR>/<db>-<timestamp>.sql.gz       volcado comprimido
#   <BACKUP_LOCAL_DIR>/<db>-<timestamp>.env.enc      .env cifrado (AES-256)
#   <BACKUP_LOCAL_DIR>/<db>-<timestamp>.manifest.json  manifiesto
#
# Y los sube a S3 bajo BACKUP_PATH aplicando BACKUP_RETENTION_DAYS.
#
# Los documentos firmados NO se respaldan aqui: ya viven en S3.
#
set -euo pipefail

cd "$(dirname "$0")/.."

ENV_FILE=".env"
if [[ "${1:-}" == "--env-file" ]]; then
    ENV_FILE="${2:?falta la ruta del fichero .env}"
fi

[[ -f "$ENV_FILE" ]] || { echo "ERROR: no existe $ENV_FILE" >&2; exit 1; }

# Lee una clave del .env sin exportar todo el fichero al entorno.
env_get() {
    local key="$1" default="${2:-}"
    local val
    val=$(grep -E "^${key}=" "$ENV_FILE" | tail -1 | cut -d= -f2- || true)
    val="${val%\"}"; val="${val#\"}"
    val="${val%\'}"; val="${val#\'}"
    echo "${val:-$default}"
}

DB_HOST=$(env_get DB_HOST 127.0.0.1)
DB_PORT=$(env_get DB_PORT 3306)
DB_DATABASE=$(env_get DB_DATABASE)
DB_USERNAME=$(env_get DB_USERNAME)
DB_PASSWORD=$(env_get DB_PASSWORD)

BACKUP_LOCAL_DIR=$(env_get BACKUP_LOCAL_DIR storage/backups)
BACKUP_PATH=$(env_get BACKUP_PATH backups/database)
BACKUP_RETENTION_DAYS=$(env_get BACKUP_RETENTION_DAYS 30)
BACKUP_ENCRYPTION_KEY=$(env_get BACKUP_ENCRYPTION_KEY)
AWS_BUCKET=$(env_get AWS_BUCKET)

[[ -n "$DB_DATABASE" ]] || { echo "ERROR: DB_DATABASE vacio en $ENV_FILE" >&2; exit 1; }

if [[ -z "$BACKUP_ENCRYPTION_KEY" ]]; then
    echo "ERROR: BACKUP_ENCRYPTION_KEY vacio en $ENV_FILE." >&2
    echo "       Es la clave con la que se cifra el .env del backup." >&2
    echo "       Debe ser exclusiva de este proyecto, sin reutilizar." >&2
    exit 1
fi

TS=$(date -u +%Y%m%dT%H%M%SZ)
PREFIX="${BACKUP_LOCAL_DIR}/${DB_DATABASE}-${TS}"
mkdir -p "$BACKUP_LOCAL_DIR"

log() { echo "[$(date -u +%H:%M:%S)] $*"; }

#------------------------------------------------------------------------------
# 1. Volcado de la base de datos
#------------------------------------------------------------------------------
log "Volcando ${DB_DATABASE} desde ${DB_HOST}:${DB_PORT}"
MYSQL_PWD="$DB_PASSWORD" mysqldump \
    --host="$DB_HOST" \
    --port="$DB_PORT" \
    --user="$DB_USERNAME" \
    --single-transaction \
    --quick \
    --routines \
    --triggers \
    --set-gtid-purged=OFF \
    --default-character-set=utf8mb4 \
    "$DB_DATABASE" | gzip -9 > "${PREFIX}.sql.gz"

SQL_SIZE=$(wc -c < "${PREFIX}.sql.gz" | tr -d ' ')
log "Volcado: ${SQL_SIZE} bytes"

#------------------------------------------------------------------------------
# 2. .env cifrado
#------------------------------------------------------------------------------
log "Cifrando ${ENV_FILE}"
openssl enc -aes-256-cbc -pbkdf2 -salt \
    -in "$ENV_FILE" \
    -out "${PREFIX}.env.enc" \
    -pass "pass:${BACKUP_ENCRYPTION_KEY}"

#------------------------------------------------------------------------------
# 3. Manifiesto
#------------------------------------------------------------------------------
SQL_SHA=$(shasum -a 256 "${PREFIX}.sql.gz" | cut -d' ' -f1)
ENV_SHA=$(shasum -a 256 "${PREFIX}.env.enc" | cut -d' ' -f1)

cat > "${PREFIX}.manifest.json" <<JSON
{
  "project": "firmalum",
  "timestamp": "${TS}",
  "host": "$(hostname)",
  "database": "${DB_DATABASE}",
  "db_host": "${DB_HOST}",
  "files": {
    "dump": {
      "name": "$(basename "${PREFIX}.sql.gz")",
      "bytes": ${SQL_SIZE},
      "sha256": "${SQL_SHA}"
    },
    "env": {
      "name": "$(basename "${PREFIX}.env.enc")",
      "bytes": $(wc -c < "${PREFIX}.env.enc" | tr -d ' '),
      "sha256": "${ENV_SHA}"
    }
  },
  "retention_days": ${BACKUP_RETENTION_DAYS}
}
JSON

log "Manifiesto: $(basename "${PREFIX}.manifest.json")"

#------------------------------------------------------------------------------
# 4. Subida a S3 y retencion
#------------------------------------------------------------------------------
if [[ -z "$AWS_BUCKET" ]]; then
    log "AWS_BUCKET vacio: se omite la subida, el backup queda solo en local."
else
    if ! command -v aws >/dev/null 2>&1; then
        echo "ERROR: aws CLI no instalado y AWS_BUCKET esta definido." >&2
        exit 1
    fi

    DEST="s3://${AWS_BUCKET}/${BACKUP_PATH}"
    log "Subiendo a ${DEST}"
    for f in "${PREFIX}.sql.gz" "${PREFIX}.env.enc" "${PREFIX}.manifest.json"; do
        aws s3 cp "$f" "${DEST}/$(basename "$f")" --only-show-errors
    done

    log "Aplicando retencion de ${BACKUP_RETENTION_DAYS} dias"
    CUTOFF=$(date -u -v-"${BACKUP_RETENTION_DAYS}"d +%Y-%m-%d 2>/dev/null \
        || date -u -d "${BACKUP_RETENTION_DAYS} days ago" +%Y-%m-%d)

    aws s3 ls "${DEST}/" | while read -r d _ _ name; do
        [[ -z "${name:-}" ]] && continue
        if [[ "$d" < "$CUTOFF" ]]; then
            log "  borrando $name (del $d)"
            aws s3 rm "${DEST}/${name}" --only-show-errors
        fi
    done
fi

#------------------------------------------------------------------------------
# 5. Verificacion
#------------------------------------------------------------------------------
log "Verificando el backup recien creado"
scripts/backup-verify.sh "${PREFIX}.sql.gz"

log "Backup completado: ${PREFIX}.*"
