#!/usr/bin/env bash
#
# Verifica un volcado producido por scripts/backup.sh.
#
#   scripts/backup-verify.sh storage/backups/firmalum-20260903T104500Z.sql.gz
#
# Un backup no verificado no existe. Comprueba, en este orden:
#   1. El gzip esta integro.
#   2. El SQL contiene las tablas criticas del dominio.
#   3. El volcado restaura limpiamente en una BD desechable (si hay mysql).
#
set -euo pipefail

cd "$(dirname "$0")/.."

DUMP="${1:?uso: scripts/backup-verify.sh <fichero.sql.gz>}"
[[ -f "$DUMP" ]] || { echo "ERROR: no existe $DUMP" >&2; exit 1; }

# Tablas sin las cuales el backup no sirve para reconstruir la plataforma.
CRITICAL_TABLES=(
    tenants
    users
    documents
    signing_processes
    signers
    signed_documents
    audit_trail_entries
    evidence_packages
    tsa_tokens
    verification_codes
)

log() { echo "[verify] $*"; }
fail() { echo "[verify] FALLO: $*" >&2; exit 1; }

#------------------------------------------------------------------------------
# 1. Integridad del gzip
#------------------------------------------------------------------------------
log "Comprobando integridad de gzip"
gzip -t "$DUMP" || fail "el gzip esta corrupto"

#------------------------------------------------------------------------------
# 2. Tablas criticas presentes
#------------------------------------------------------------------------------
log "Comprobando tablas criticas"
TABLES=$(gzip -dc "$DUMP" | grep -oE 'CREATE TABLE `[^`]+`' | sed 's/.*`\(.*\)`/\1/' | sort -u)

MISSING=()
for t in "${CRITICAL_TABLES[@]}"; do
    grep -qx "$t" <<< "$TABLES" || MISSING+=("$t")
done

if [[ ${#MISSING[@]} -gt 0 ]]; then
    fail "faltan tablas criticas: ${MISSING[*]}"
fi
log "  $(wc -l <<< "$TABLES" | tr -d ' ') tablas, las ${#CRITICAL_TABLES[@]} criticas presentes"

#------------------------------------------------------------------------------
# 3. Restauracion de prueba
#------------------------------------------------------------------------------
if ! command -v mysql >/dev/null 2>&1; then
    log "mysql no disponible: se omite la restauracion de prueba."
    log "OK (verificacion parcial)"
    exit 0
fi

ENV_FILE="${VERIFY_ENV_FILE:-.env}"
env_get() {
    local val
    val=$(grep -E "^$1=" "$ENV_FILE" | tail -1 | cut -d= -f2- || true)
    val="${val%\"}"; val="${val#\"}"
    echo "${val:-${2:-}}"
}

DB_HOST=$(env_get DB_HOST 127.0.0.1)
DB_PORT=$(env_get DB_PORT 3306)
DB_USERNAME=$(env_get DB_USERNAME root)
DB_PASSWORD=$(env_get DB_PASSWORD)

SCRATCH="firmalum_verify_$$"
log "Restaurando en la BD desechable ${SCRATCH}"

cleanup() {
    MYSQL_PWD="$DB_PASSWORD" mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USERNAME" \
        -e "DROP DATABASE IF EXISTS \`${SCRATCH}\`" 2>/dev/null || true
}
trap cleanup EXIT

MYSQL_PWD="$DB_PASSWORD" mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USERNAME" \
    -e "CREATE DATABASE \`${SCRATCH}\` CHARACTER SET utf8mb4" \
    || fail "no se pudo crear la BD de verificacion"

gzip -dc "$DUMP" | MYSQL_PWD="$DB_PASSWORD" mysql -h "$DB_HOST" -P "$DB_PORT" \
    -u "$DB_USERNAME" "$SCRATCH" \
    || fail "la restauracion de prueba fallo"

COUNT=$(MYSQL_PWD="$DB_PASSWORD" mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USERNAME" \
    -N -B -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='${SCRATCH}'")

[[ "$COUNT" -gt 0 ]] || fail "la BD restaurada esta vacia"

log "  ${COUNT} tablas restauradas correctamente"
log "OK: backup verificado"
