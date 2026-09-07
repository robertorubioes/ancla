#!/usr/bin/env bash
#
# Restaura un backup de Firmalum producido por scripts/backup.sh.
#
#   scripts/restore.sh <fichero.sql.gz> [--target <bd>] [--yes]
#
# Por defecto restaura sobre la BD indicada en el .env, lo que DESTRUYE su
# contenido actual: pide confirmacion explicita salvo que se pase --yes.
#
# Para descifrar el .env del backup:
#   openssl enc -d -aes-256-cbc -pbkdf2 -in <fichero>.env.enc -out .env.recuperado
#
set -euo pipefail

cd "$(dirname "$0")/.."

DUMP="${1:?uso: scripts/restore.sh <fichero.sql.gz> [--target <bd>] [--yes]}"
shift

TARGET=""
ASSUME_YES=0
while [[ $# -gt 0 ]]; do
    case "$1" in
        --target) TARGET="${2:?falta el nombre de la BD}"; shift 2 ;;
        --yes)    ASSUME_YES=1; shift ;;
        *)        echo "Opcion desconocida: $1" >&2; exit 1 ;;
    esac
done

[[ -f "$DUMP" ]] || { echo "ERROR: no existe $DUMP" >&2; exit 1; }

ENV_FILE="${RESTORE_ENV_FILE:-.env}"
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
TARGET="${TARGET:-$(env_get DB_DATABASE)}"

[[ -n "$TARGET" ]] || { echo "ERROR: no hay BD destino" >&2; exit 1; }

echo "Se va a restaurar:"
echo "  origen : $DUMP"
echo "  destino: ${TARGET} en ${DB_HOST}:${DB_PORT}"
echo
echo "Esto DESTRUYE el contenido actual de ${TARGET}."

if [[ $ASSUME_YES -eq 0 ]]; then
    read -r -p "Escribe el nombre de la BD para confirmar: " CONFIRM
    [[ "$CONFIRM" == "$TARGET" ]] || { echo "Cancelado."; exit 1; }
fi

echo "Verificando el backup antes de restaurar..."
scripts/backup-verify.sh "$DUMP"

echo "Restaurando..."
MYSQL_PWD="$DB_PASSWORD" mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USERNAME" \
    -e "CREATE DATABASE IF NOT EXISTS \`${TARGET}\` CHARACTER SET utf8mb4"

gzip -dc "$DUMP" | MYSQL_PWD="$DB_PASSWORD" mysql -h "$DB_HOST" -P "$DB_PORT" \
    -u "$DB_USERNAME" "$TARGET"

echo "Restauracion completada en ${TARGET}."
echo "Recuerda: php artisan migrate --force && php artisan optimize"
