#!/usr/bin/env bash
#
# Clona la base de datos de produccion en testing, anonimizando los datos
# personales por el camino.
#
#   scripts/clone-prod-to-test.sh [--dry-run] [--yes]
#
# Un testing con datos inventados no prueba nada: no tiene el volumen, ni las
# formas raras, ni los casos limite que solo aparecen con datos reales. Pero
# un testing con datos reales sin tocar es una fuga de datos personales con
# patas, y ademas manda correos de verdad a clientes de verdad.
#
# Pensado para correr de madrugada por cron, EN EL SERVIDOR DE TESTING. La
# base de datos de produccion es gestionada y solo escucha en la red privada.
#
# Configuracion, toda del .env de testing:
#
#   CLONE_SOURCE_ENV_FILE   ruta al .env de produccion (para leer su BD)
#   CLONE_PRESERVE_EMAILS   correos que NO se anonimizan, separados por coma
#   CLONE_TARGET_PATTERN    la BD destino debe casar con esto (def: test)
#
# @see docs/ENTORNOS.md
#
set -euo pipefail

cd "$(dirname "$0")/.."

DRY_RUN=0
ASSUME_YES=0

while [[ $# -gt 0 ]]; do
    case "$1" in
        --dry-run) DRY_RUN=1; shift ;;
        --yes) ASSUME_YES=1; shift ;;
        *) echo "Opcion desconocida: $1" >&2; exit 1 ;;
    esac
done

TARGET_ENV="${CLONE_TARGET_ENV_FILE:-.env}"
[[ -f "$TARGET_ENV" ]] || { echo "ERROR: no existe $TARGET_ENV" >&2; exit 1; }

env_get() {
    local file="$1" key="$2" default="${3:-}" val
    val=$(grep -E "^${key}=" "$file" | tail -1 | cut -d= -f2- || true)
    val="${val%\"}"; val="${val#\"}"
    val="${val%\'}"; val="${val#\'}"
    echo "${val:-$default}"
}

log()  { printf '[%s] %s\n' "$(date -u +%H:%M:%S)" "$*"; }
fail() { printf '[%s] ABORTADO: %s\n' "$(date -u +%H:%M:%S)" "$*" >&2; exit 1; }

#------------------------------------------------------------------------------
# Configuracion
#------------------------------------------------------------------------------
SOURCE_ENV=$(env_get "$TARGET_ENV" CLONE_SOURCE_ENV_FILE)
[[ -n "$SOURCE_ENV" ]] || fail "CLONE_SOURCE_ENV_FILE no esta definido en $TARGET_ENV"
[[ -f "$SOURCE_ENV" ]] || fail "no existe el .env de origen: $SOURCE_ENV"

PRESERVE_EMAILS=$(env_get "$TARGET_ENV" CLONE_PRESERVE_EMAILS)
TARGET_PATTERN=$(env_get "$TARGET_ENV" CLONE_TARGET_PATTERN "test")

SRC_HOST=$(env_get "$SOURCE_ENV" DB_HOST 127.0.0.1)
SRC_PORT=$(env_get "$SOURCE_ENV" DB_PORT 3306)
SRC_DB=$(env_get "$SOURCE_ENV" DB_DATABASE)
SRC_USER=$(env_get "$SOURCE_ENV" DB_USERNAME)
SRC_PASS=$(env_get "$SOURCE_ENV" DB_PASSWORD)

DST_HOST=$(env_get "$TARGET_ENV" DB_HOST 127.0.0.1)
DST_PORT=$(env_get "$TARGET_ENV" DB_PORT 3306)
DST_DB=$(env_get "$TARGET_ENV" DB_DATABASE)
DST_USER=$(env_get "$TARGET_ENV" DB_USERNAME)
DST_PASS=$(env_get "$TARGET_ENV" DB_PASSWORD)
DST_APP_ENV=$(env_get "$TARGET_ENV" APP_ENV)

#------------------------------------------------------------------------------
# Guardas. Este script ESCRIBE en una base de datos: si se equivoca de destino
# destruye datos. Cada guarda esta aqui por una forma concreta de equivocarse.
#------------------------------------------------------------------------------
[[ -n "$SRC_DB" ]] || fail "el .env de origen no define DB_DATABASE"
[[ -n "$DST_DB" ]] || fail "el .env de destino no define DB_DATABASE"

# 1. Nunca escribir sobre produccion
if [[ "$DST_APP_ENV" == "production" ]]; then
    fail "el destino tiene APP_ENV=production. Este script nunca escribe en produccion."
fi

# 2. El nombre de la BD destino tiene que parecer de testing
if [[ ! "$DST_DB" =~ $TARGET_PATTERN ]]; then
    fail "la BD destino '${DST_DB}' no casa con '${TARGET_PATTERN}'. Si es correcta, ajusta CLONE_TARGET_PATTERN."
fi

# 3. Origen y destino no pueden ser el mismo sitio
if [[ "$SRC_HOST:$SRC_PORT:$SRC_DB" == "$DST_HOST:$DST_PORT:$DST_DB" ]]; then
    fail "origen y destino son la misma base de datos."
fi

log "Origen : ${SRC_USER}@${SRC_HOST}:${SRC_PORT}/${SRC_DB}"
log "Destino: ${DST_USER}@${DST_HOST}:${DST_PORT}/${DST_DB}  (APP_ENV=${DST_APP_ENV:-sin definir})"

if [[ $DRY_RUN -eq 1 ]]; then
    log "--dry-run: no se toca nada."
    exit 0
fi

if [[ $ASSUME_YES -eq 0 ]]; then
    echo
    echo "Se va a REEMPLAZAR por completo el contenido de ${DST_DB}."
    read -r -p "Escribe el nombre de la BD destino para confirmar: " CONFIRM
    [[ "$CONFIRM" == "$DST_DB" ]] || fail "cancelado."
fi

#------------------------------------------------------------------------------
# 1. Volcado de produccion
#------------------------------------------------------------------------------
DUMP=$(mktemp -t clone_prod_XXXXXX.sql.gz)
trap 'rm -f "$DUMP"' EXIT

log "Volcando ${SRC_DB}"
MYSQL_PWD="$SRC_PASS" mysqldump \
    --host="$SRC_HOST" --port="$SRC_PORT" --user="$SRC_USER" \
    --single-transaction --quick --routines --triggers \
    --set-gtid-purged=OFF \
    --default-character-set=utf8mb4 \
    "$SRC_DB" | gzip > "$DUMP"

log "Volcado: $(wc -c < "$DUMP" | tr -d ' ') bytes"

#------------------------------------------------------------------------------
# 2. Restauracion en testing
#------------------------------------------------------------------------------
run_sql() {
    MYSQL_PWD="$DST_PASS" mysql \
        --host="$DST_HOST" --port="$DST_PORT" --user="$DST_USER" \
        --default-character-set=utf8mb4 "$DST_DB" "$@"
}

log "Restaurando en ${DST_DB}"
MYSQL_PWD="$DST_PASS" mysql --host="$DST_HOST" --port="$DST_PORT" --user="$DST_USER" \
    -e "DROP DATABASE IF EXISTS \`${DST_DB}\`; CREATE DATABASE \`${DST_DB}\` CHARACTER SET utf8mb4;"

gzip -dc "$DUMP" | run_sql

#------------------------------------------------------------------------------
# 3. Anonimizacion
#------------------------------------------------------------------------------
log "Anonimizando"

# Los correos preservados se dejan intactos: normalmente el del operador, para
# poder entrar a mirar.
PRESERVE_SQL="''"
if [[ -n "$PRESERVE_EMAILS" ]]; then
    PRESERVE_SQL=$(printf "'%s'" "$(echo "$PRESERVE_EMAILS" | sed "s/,/','/g")")
fi

run_sql <<SQL
SET FOREIGN_KEY_CHECKS = 0;
SET @preserve_off = 0;

-- Usuarios: correo y nombre. La contrasena se conserva en su hash, de modo
-- que nadie puede entrar sin conocer la real.
UPDATE users
SET email = CONCAT('usuario', id, '@test.invalid'),
    name  = CONCAT('Usuario ', id),
    remember_token = NULL
WHERE email NOT IN (${PRESERVE_SQL});

-- Firmantes: son terceros, y sus correos son a los que se enviarian avisos.
UPDATE signers
SET email = CONCAT('firmante', id, '@test.invalid'),
    name  = CONCAT('Firmante ', id),
    phone = NULL
WHERE email NOT IN (${PRESERVE_SQL});

UPDATE user_invitations
SET email = CONCAT('invitado', id, '@test.invalid'),
    name  = CONCAT('Invitado ', id)
WHERE email NOT IN (${PRESERVE_SQL});

UPDATE signed_documents
SET signed_name = CONCAT('Firmante ', id)
WHERE signed_name IS NOT NULL;

-- Evidencias: correos, IP y coordenadas son datos personales por si mismos.
UPDATE consent_records
SET signer_email = CONCAT('firmante', id, '@test.invalid');

UPDATE device_fingerprints
SET signer_email = CONCAT('firmante', id, '@test.invalid'),
    user_agent_raw = 'Mozilla/5.0 (anonimizado)';

UPDATE ip_resolution_records
SET signer_email = CONCAT('firmante', id, '@test.invalid'),
    ip_address = '203.0.113.1',
    asn_name = 'Anonimizado';

UPDATE geolocation_records
SET signer_email = CONCAT('firmante', id, '@test.invalid'),
    latitude = NULL, longitude = NULL,
    ip_latitude = NULL, ip_longitude = NULL,
    formatted_address = 'Direccion anonimizada';

UPDATE audit_trail_entries
SET ip_address = '203.0.113.1',
    user_agent = 'Mozilla/5.0 (anonimizado)';

UPDATE verification_logs
SET ip_address = '203.0.113.1',
    user_agent = 'Mozilla/5.0 (anonimizado)';

-- Credenciales de un solo uso y sesiones: no se clonan, se tiran.
DELETE FROM otp_codes;
DELETE FROM password_reset_tokens;
DELETE FROM sessions;

SET FOREIGN_KEY_CHECKS = 1;
SQL

#------------------------------------------------------------------------------
# 4. Comprobacion
#------------------------------------------------------------------------------
log "Comprobando que no queda ningun correo real"

FUGAS=$(run_sql -N -B -e "
SELECT
  (SELECT COUNT(*) FROM users   WHERE email NOT LIKE '%@test.invalid' AND email NOT IN (${PRESERVE_SQL}))
+ (SELECT COUNT(*) FROM signers WHERE email NOT LIKE '%@test.invalid' AND email NOT IN (${PRESERVE_SQL}))
+ (SELECT COUNT(*) FROM consent_records       WHERE signer_email NOT LIKE '%@test.invalid')
+ (SELECT COUNT(*) FROM device_fingerprints   WHERE signer_email NOT LIKE '%@test.invalid')
+ (SELECT COUNT(*) FROM ip_resolution_records WHERE signer_email NOT LIKE '%@test.invalid')
+ (SELECT COUNT(*) FROM geolocation_records   WHERE signer_email NOT LIKE '%@test.invalid')
")

if [[ "${FUGAS:-1}" != "0" ]]; then
    fail "quedan ${FUGAS} correos sin anonimizar. Revisa el script antes de exponer testing."
fi

TABLAS=$(run_sql -N -B -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='${DST_DB}'")
USUARIOS=$(run_sql -N -B -e "SELECT COUNT(*) FROM users")
PROCESOS=$(run_sql -N -B -e "SELECT COUNT(*) FROM signing_processes")

log "Listo: ${TABLAS} tablas, ${USUARIOS} usuarios, ${PROCESOS} procesos de firma."
log ""
log "AVISO: al reescribir audit_trail_entries se rompe su encadenado por"
log "hash, asi que en testing la verificacion de la cadena dara invalido."
log "Es lo esperado y es la cadena haciendo su trabajo: los datos se han"
log "alterado. No es un bug que reportar."
