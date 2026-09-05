#!/usr/bin/env bash
#
# Monta el entorno de TESTING junto al de produccion, en el mismo servidor.
#
#   scripts/setup-testing.sh            # monta o actualiza
#   scripts/setup-testing.sh --ssl      # ademas pide el certificado
#
# Idempotente: se puede ejecutar las veces que haga falta.
#
# Se ejecuta EN EL SERVIDOR, como root. No toca nada de produccion: crea un
# directorio, una base de datos y un vhost aparte.
#
# Requisito previo que este script NO puede resolver: el DNS.
#   app.test.firmalum.com  ->  A  ->  <ip del servidor>
#   *.test.firmalum.com    ->  A  ->  <ip del servidor>   (subdominios de tenant)
#
# @see docs/ENTORNOS.md
#
set -euo pipefail

PROD_DIR="${PROD_DIR:-/var/www/ancla}"
TEST_DIR="${TEST_DIR:-/var/www/firmalum-test}"
TEST_DB="${TEST_DB:-firmalum_test}"
TEST_HOST="${TEST_HOST:-app.test.firmalum.com}"
BASE_DOMAIN="${BASE_DOMAIN:-test.firmalum.com}"
BRANCH="${BRANCH:-staging}"
PHP_FPM="${PHP_FPM:-php8.2-fpm}"

WANT_SSL=0
[[ "${1:-}" == "--ssl" ]] && WANT_SSL=1

log()  { printf '\033[1;34m==>\033[0m %s\n' "$*"; }
warn() { printf '\033[1;33m!! \033[0m%s\n' "$*"; }
fail() { printf '\033[1;31mABORTADO:\033[0m %s\n' "$*" >&2; exit 1; }

[[ $EUID -eq 0 ]] || fail "ejecutalo como root."
[[ -d "$PROD_DIR" ]] || fail "no existe $PROD_DIR"

env_get() {
    local file="$1" key="$2" val
    val=$(grep -E "^${key}=" "$file" | tail -1 | cut -d= -f2- || true)
    val="${val%\"}"; val="${val#\"}"
    echo "$val"
}

DB_HOST=$(env_get "$PROD_DIR/.env" DB_HOST)
DB_PORT=$(env_get "$PROD_DIR/.env" DB_PORT)
DB_USER=$(env_get "$PROD_DIR/.env" DB_USERNAME)
DB_PASS=$(env_get "$PROD_DIR/.env" DB_PASSWORD)

#------------------------------------------------------------------------------
# 1. Base de datos propia
#------------------------------------------------------------------------------
log "Base de datos ${TEST_DB}"
MYSQL_PWD="$DB_PASS" mysql --host="$DB_HOST" --port="$DB_PORT" --user="$DB_USER" \
    -e "CREATE DATABASE IF NOT EXISTS \`${TEST_DB}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"

#------------------------------------------------------------------------------
# 2. Codigo
#------------------------------------------------------------------------------
if [[ ! -d "$TEST_DIR/.git" ]]; then
    log "Clonando el repositorio en ${TEST_DIR}"
    git clone --branch "$BRANCH" "$(cd "$PROD_DIR" && git remote get-url origin)" "$TEST_DIR"
else
    log "Actualizando ${TEST_DIR}"
    git -C "$TEST_DIR" fetch --quiet origin
    git -C "$TEST_DIR" checkout --quiet "$BRANCH"
    git -C "$TEST_DIR" reset --hard --quiet "origin/${BRANCH}"
fi

#------------------------------------------------------------------------------
# 3. Configuracion
#------------------------------------------------------------------------------
if [[ ! -f "$TEST_DIR/.env" ]]; then
    log "Creando el .env de testing a partir del de produccion"

    cp "$PROD_DIR/.env" "$TEST_DIR/.env"

    set_env() {
        local key="$1" value="$2"
        if grep -qE "^${key}=" "$TEST_DIR/.env"; then
            sed -i "s|^${key}=.*|${key}=${value}|" "$TEST_DIR/.env"
        else
            printf '%s=%s\n' "$key" "$value" >> "$TEST_DIR/.env"
        fi
    }

    set_env APP_ENV testing
    set_env APP_DEBUG false
    set_env APP_URL "https://${TEST_HOST}"
    set_env APP_BASE_DOMAIN "$BASE_DOMAIN"
    set_env DB_DATABASE "$TEST_DB"

    # Testing NUNCA usa servicios externos de produccion.
    set_env MAIL_MAILER log
    set_env TSA_MOCK_ENABLED true
    set_env FILESYSTEM_DISK local

    # Clon nocturno
    set_env CLONE_SOURCE_ENV_FILE "${PROD_DIR}/.env"
    set_env CLONE_TARGET_PATTERN test

    chown www-data:www-data "$TEST_DIR/.env"
    chmod 640 "$TEST_DIR/.env"

    warn "El .env de testing hereda APP_KEY y APP_ENCRYPTION_KEY de produccion."
    warn "Es necesario para poder descifrar los datos clonados, pero significa"
    warn "que quien comprometa testing tiene las claves de produccion. Decide"
    warn "si te compensa antes de exponerlo."
else
    log ".env de testing ya existe, no se toca"
fi

#------------------------------------------------------------------------------
# 4. Dependencias y assets
#------------------------------------------------------------------------------
log "Dependencias"
cd "$TEST_DIR"
composer install --no-interaction --no-dev --optimize-autoloader --quiet

if [[ -f package.json ]] && command -v npm >/dev/null 2>&1; then
    log "Assets"
    npm ci --silent
    npm run build --silent
else
    warn "npm no disponible: los assets no se han compilado."
fi

log "Migraciones"
php artisan migrate --force --no-interaction

log "Cacheando configuracion"
php artisan config:cache >/dev/null
php artisan route:cache >/dev/null
php artisan view:cache >/dev/null

chown -R www-data:www-data "$TEST_DIR/storage" "$TEST_DIR/bootstrap/cache"

#------------------------------------------------------------------------------
# 5. nginx
#------------------------------------------------------------------------------
VHOST="/etc/nginx/sites-available/firmalum-test"

log "Vhost ${TEST_HOST}"
cat > "$VHOST" <<NGINX
# Firmalum - entorno de TESTING
# Generado por scripts/setup-testing.sh. Ver docs/ENTORNOS.md
server {
    listen 80;
    server_name ${TEST_HOST} *.${BASE_DOMAIN};
    root ${TEST_DIR}/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";
    # Que ningun buscador indexe el entorno de pruebas.
    add_header X-Robots-Tag "noindex, nofollow" always;

    index index.php;
    charset utf-8;
    client_max_body_size 100M;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php\$ {
        fastcgi_pass unix:/run/php/${PHP_FPM}.sock;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_read_timeout 300;
    }

    location ~ /\.(?!well-known).* { deny all; }

    access_log /var/log/nginx/firmalum-test_access.log;
    error_log  /var/log/nginx/firmalum-test_error.log;
}
NGINX

ln -sf "$VHOST" /etc/nginx/sites-enabled/firmalum-test
nginx -t
systemctl reload nginx

#------------------------------------------------------------------------------
# 6. Clon nocturno
#------------------------------------------------------------------------------
CRON="/etc/cron.d/firmalum-test-clone"

log "Cron del clon nocturno"
cat > "$CRON" <<CRONTAB
# Clon anonimizado de produccion a testing, cada madrugada.
# Generado por scripts/setup-testing.sh
0 3 * * * root cd ${TEST_DIR} && scripts/clone-prod-to-test.sh --yes >> ${TEST_DIR}/storage/logs/clone.log 2>&1
CRONTAB
chmod 644 "$CRON"

#------------------------------------------------------------------------------
# 7. Certificado
#------------------------------------------------------------------------------
if [[ $WANT_SSL -eq 1 ]]; then
    if ! getent hosts "$TEST_HOST" >/dev/null; then
        fail "${TEST_HOST} no resuelve todavia. Crea el registro DNS y vuelve a intentarlo."
    fi

    log "Certificado para ${TEST_HOST}"
    certbot --nginx -d "$TEST_HOST" --non-interactive --agree-tos \
        --email "$(env_get "$PROD_DIR/.env" MAIL_FROM_ADDRESS)" --redirect
else
    warn "Sin certificado. Cuando ${TEST_HOST} resuelva, ejecuta:"
    warn "  $0 --ssl"
fi

#------------------------------------------------------------------------------
# 8. Comprobacion
#------------------------------------------------------------------------------
log "Comprobando"

CODE=$(curl -s -o /dev/null -w '%{http_code}' -H "Host: ${TEST_HOST}" http://127.0.0.1/ || echo ERR)
printf '   %-34s HTTP %s\n' "$TEST_HOST" "$CODE"

TABLAS=$(MYSQL_PWD="$DB_PASS" mysql --host="$DB_HOST" --port="$DB_PORT" --user="$DB_USER" \
    -N -B -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='${TEST_DB}'")
printf '   %-34s %s tablas\n' "$TEST_DB" "$TABLAS"

log "Listo."
log ""
log "Siguiente paso: poblarlo con datos reales anonimizados."
log "  cd ${TEST_DIR} && scripts/clone-prod-to-test.sh"
