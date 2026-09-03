# 🚀 DEPLOYMENT Firmalum - INSTRUCCIONES PASO A PASO

**Ejecuta esto DIRECTAMENTE en tu servidor 159.65.201.32**

---

## 📋 CONEXIÓN AL SERVIDOR

```bash
ssh -i ~/.ssh/firmalum_prod root@159.65.201.32
```

Una vez conectado, ejecuta los siguientes pasos **EN ORDEN**:

---

## PASO 1: Setup del Sistema (10 min)

Copia y pega TODO este bloque:

```bash
#!/bin/bash
set -e
echo "🚀 Firmalum Setup - Iniciando..."

# Actualizar sistema
echo "📦 Actualizando sistema..."
export DEBIAN_FRONTEND=noninteractive
apt-get update -qq
apt-get upgrade -y -qq

# Crear usuario deploy
echo "👤 Configurando usuario deploy..."
if ! id -u deploy > /dev/null 2>&1; then
  adduser --disabled-password --gecos "" deploy
  usermod -aG sudo deploy
  echo "deploy ALL=(ALL) NOPASSWD:ALL" >> /etc/sudoers
  mkdir -p /home/deploy/.ssh
  if [ -f ~/.ssh/authorized_keys ]; then
    cp ~/.ssh/authorized_keys /home/deploy/.ssh/
    chown -R deploy:deploy /home/deploy/.ssh
    chmod 700 /home/deploy/.ssh
    chmod 600 /home/deploy/.ssh/authorized_keys
  fi
fi

# Firewall
echo "🔥 Configurando firewall..."
ufw allow 22/tcp > /dev/null 2>&1 || true
ufw allow 80/tcp > /dev/null 2>&1 || true
ufw allow 443/tcp > /dev/null 2>&1 || true
ufw --force enable || true

# Fail2ban
echo "🛡️ Instalando fail2ban..."
apt-get install -y -qq fail2ban
systemctl enable fail2ban > /dev/null 2>&1
systemctl start fail2ban > /dev/null 2>&1

# Nginx
echo "🌐 Instalando Nginx..."
if ! command -v nginx &> /dev/null; then
  apt-get install -y -qq nginx
  systemctl enable nginx
  systemctl start nginx
fi

# PHP 8.2
echo "🐘 Instalando PHP 8.2..."
if ! command -v php8.2 &> /dev/null; then
  add-apt-repository -y ppa:ondrej/php
  apt-get update -qq
  apt-get install -y -qq php8.2-fpm php8.2-cli php8.2-common php8.2-mysql \
    php8.2-mbstring php8.2-xml php8.2-bcmath php8.2-curl php8.2-gd \
    php8.2-zip php8.2-redis php8.2-intl php8.2-soap php8.2-imagick
fi

# Composer
echo "🎵 Instalando Composer..."
if ! command -v composer &> /dev/null; then
  curl -sS https://getcomposer.org/installer | php
  mv composer.phar /usr/local/bin/composer
  chmod +x /usr/local/bin/composer
fi

# Redis
echo "💾 Instalando Redis..."
if ! command -v redis-cli &> /dev/null; then
  apt-get install -y -qq redis-server
  systemctl enable redis-server
  systemctl start redis-server
fi

# Node.js 20
echo "📦 Instalando Node.js..."
if ! command -v node &> /dev/null; then
  curl -fsSL https://deb.nodesource.com/setup_20.x | bash -
  apt-get install -y -qq nodejs
fi

# Supervisor
echo "👷 Instalando Supervisor..."
if ! command -v supervisorctl &> /dev/null; then
  apt-get install -y -qq supervisor
  systemctl enable supervisor
  systemctl start supervisor
fi

# Certbot
echo "🔒 Instalando Certbot..."
if ! command -v certbot &> /dev/null; then
  apt-get install -y -qq certbot python3-certbot-nginx
fi

# MySQL Client
echo "🗄️ Instalando MySQL Client..."
apt-get install -y -qq mysql-client

# Git y extras
apt-get install -y -qq git unzip curl wget

echo ""
echo "✅ Setup del sistema completado!"
echo ""
```

**ESPERA** a que termine (5-10 minutos).

---

## PASO 2: Clonar Firmalum

```bash
# Crear directorio
mkdir -p /var/www/firmalum
cd /var/www

# Clonar repositorio
git clone -b staging https://github.com/robertorubioes/ancla.git firmalum
cd firmalum

echo "✅ Código clonado!"
```

---

## PASO 3: Instalar Dependencias

```bash
cd /var/www/firmalum

# Composer (puede tardar 2-3 minutos)
echo "📦 Instalando dependencias Composer..."
composer install --optimize-autoloader --no-dev

# NPM (puede tardar 3-5 minutos)
echo "📦 Instalando y compilando assets..."
npm install
npm run build

# Permisos
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

echo "✅ Dependencias instaladas!"
```

---

## PASO 4: Configurar .env

```bash
cd /var/www/firmalum
nano .env
```

**BORRA TODO** el contenido y pega esto:

```env
APP_NAME="Firmalum"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://app.firmalum.com
APP_TIMEZONE=Europe/Madrid
APP_LOCALE=es

# DATABASE (reemplaza con tus valores de Digital Ocean Managed Database)
DB_CONNECTION=mysql
DB_HOST=YOUR_DB_HOST_FROM_DO
DB_PORT=25060
DB_DATABASE=ancla_production
DB_USERNAME=doadmin
DB_PASSWORD=YOUR_DB_PASSWORD_FROM_DO
MYSQL_ATTR_SSL_CA=/root/ca-certificate.crt

# REDIS
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
REDIS_CLIENT=phpredis
CACHE_DRIVER=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

# MAIL (LOG temporal)
MAIL_MAILER=log
MAIL_FROM_ADDRESS=noreply@firmalum.com
MAIL_FROM_NAME="Firmalum"

# SPACES (reemplaza con tus valores de Digital Ocean Spaces)
FILESYSTEM_DISK=s3
AWS_BUCKET=firmalum
AWS_REGION=ams3
AWS_ENDPOINT=https://ams3.digitaloceanspaces.com
AWS_USE_PATH_STYLE_ENDPOINT=false
AWS_S3_KEY=YOUR_SPACES_ACCESS_KEY_FROM_DO
AWS_S3_SECRET=YOUR_SPACES_SECRET_KEY_FROM_DO

# ENCRYPTION
APP_ENCRYPTION_KEY=
ENCRYPTION_KEY_VERSION=v1
ENCRYPTION_KEY_CACHE_TTL=3600

# BACKUP
BACKUP_ENCRYPTION_ENABLED=true
BACKUP_SCHEDULE="0 2 * * *"
BACKUP_RETENTION_DAYS=30
BACKUP_DISK=s3

# SIGNING
SIGNATURE_PADES_LEVEL=B-LT
SIGNATURE_CERT_PATH=storage/certificates/ancla-production.crt
SIGNATURE_KEY_PATH=storage/certificates/ancla-production.key
SIGNATURE_KEY_PASSWORD=
SIGNATURE_APPEARANCE_MODE=visible
SIGNATURE_PAGE=last
SIGNATURE_TSA_QUALIFIED=true

# TSA
TSA_MOCK=false
TSA_URL=http://timestamp.digicert.com
TSA_TIMEOUT=30

# OTP
OTP_LENGTH=6
OTP_EXPIRES_MINUTES=10
OTP_MAX_ATTEMPTS=5
OTP_RATE_LIMIT_HOUR=3

# LOGGING
LOG_CHANNEL=daily
LOG_LEVEL=warning

# SECURITY
RATE_LIMIT_API_PUBLIC=60,1
RATE_LIMIT_API_PUBLIC_DAILY=1000,1440

# SUPERADMIN
SUPERADMIN_EMAIL=mail@robertorubio.es
SUPERADMIN_NAME="Juan Pérez"
SUPERADMIN_PASSWORD=

# BROADCAST
BROADCAST_DRIVER=log

# VITE
VITE_APP_NAME="${APP_NAME}"
```

**GUARDA**: `Ctrl+O`, `Enter`, `Ctrl+X`

---

## PASO 5: Descargar CA Certificate y Generar Keys

```bash
cd /var/www/firmalum

# CA Certificate
wget -q https://raw.githubusercontent.com/digitalocean/do-certs/main/ca-certificate.crt -O /root/ca-certificate.crt

# Generar APP_KEY
php artisan key:generate --force

# Generar password superadmin
SUPERADMIN_PASS=$(openssl rand -base64 16)
echo "========================================="
echo "PASSWORD SUPERADMIN: $SUPERADMIN_PASS"
echo "========================================="
echo "⚠️  COPIA ESTE PASSWORD AHORA!"
echo "========================================="

# Añadir al .env
sed -i "s/SUPERADMIN_PASSWORD=/SUPERADMIN_PASSWORD=$SUPERADMIN_PASS/" .env

# Generar encryption key
ENCRYPTION_KEY="base64:$(openssl rand -base64 32)"
sed -i "s|APP_ENCRYPTION_KEY=|APP_ENCRYPTION_KEY=$ENCRYPTION_KEY|" .env

# Certificado de firma
mkdir -p storage/certificates
openssl req -x509 -newkey rsa:4096 -sha256 -days 3650 \
  -nodes -keyout storage/certificates/ancla-production.key \
  -out storage/certificates/ancla-production.crt \
  -subj "/C=ES/ST=Madrid/L=Madrid/O=Firmalum/CN=app.firmalum.com"

chmod 600 storage/certificates/ancla-production.key
chmod 644 storage/certificates/ancla-production.crt

echo "✅ Keys generadas!"
```

**⚠️ ANOTA EL PASSWORD** que se muestre arriba.

---

## PASO 6: Configurar Base de Datos

```bash
cd /var/www/firmalum

# Crear base de datos (usa tus credenciales de Managed Database)
echo "🗄️ Creando base de datos..."
mysql -h YOUR_DB_HOST_FROM_DO \
  -P 25060 -u doadmin -pYOUR_DB_PASSWORD --ssl-mode=REQUIRED \
  -e "CREATE DATABASE IF NOT EXISTS ancla_production CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Verificar conexión
echo "🔍 Verificando conexión..."
php artisan tinker --execute="DB::connection()->getPdo(); echo 'DB Connection OK';"

# Ejecutar migraciones
echo "🔄 Ejecutando migraciones..."
php artisan migrate --force

# Crear superadmin
echo "👤 Creando superadmin..."
php artisan db:seed --class=SuperadminSeeder --force

echo "✅ Base de datos lista!"
```

---

## PASO 7: Configurar Nginx

```bash
nano /etc/nginx/sites-available/firmalum
```

**PEGA TODO ESTO**:

```nginx
# HTTP redirect
server {
    listen 80;
    listen [::]:80;
    server_name app.firmalum.com *.app.firmalum.com;
    return 301 https://$host$request_uri;
}

# HTTPS
server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name app.firmalum.com *.app.firmalum.com;
    
    root /var/www/firmalum/public;
    index index.php;
    
    ssl_certificate /etc/ssl/certs/ssl-cert-snakeoil.pem;
    ssl_certificate_key /etc/ssl/private/ssl-cert-snakeoil.key;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;
    
    client_max_body_size 50M;
    
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    
    access_log /var/log/nginx/ancla_access.log;
    error_log /var/log/nginx/ancla_error.log;
    
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_read_timeout 300;
    }
    
    location ~ /\.(?!well-known).* {
        deny all;
    }
    
    location ~* \.(jpg|jpeg|png|gif|ico|css|js|svg|woff|woff2|ttf|eot)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }
}
```

**GUARDA**: `Ctrl+O`, `Enter`, `Ctrl+X`

```bash
# Habilitar sitio
ln -sf /etc/nginx/sites-available/firmalum /etc/nginx/sites-enabled/

# Optimizar PHP
nano /etc/php/8.2/fpm/php.ini
```

Busca y **CAMBIA** estas líneas (usa Ctrl+W para buscar):

```ini
memory_limit = 512M
upload_max_filesize = 50M
post_max_size = 50M
max_execution_time = 300
opcache.enable = 1
opcache.memory_consumption = 256
```

**GUARDA**: `Ctrl+O`, `Enter`, `Ctrl+X`

```bash
# Test y restart
nginx -t
systemctl restart php8.2-fpm
systemctl reload nginx

echo "✅ Nginx configurado!"
```

---

## PASO 8: Queue Workers

```bash
nano /etc/supervisor/conf.d/ancla-worker.conf
```

**PEGA**:

```ini
[program:ancla-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/firmalum/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=4
redirect_stderr=true
stdout_logfile=/var/www/firmalum/storage/logs/worker.log
stopwaitsecs=3600
```

**GUARDA**: `Ctrl+O`, `Enter`, `Ctrl+X`

```bash
# Activar
supervisorctl reread
supervisorctl update
supervisorctl start ancla-worker:*
supervisorctl status

echo "✅ Queue workers activos!"
```

---

## PASO 9: Laravel Scheduler

```bash
crontab -e
```

**AÑADE al final**:

```cron
* * * * * cd /var/www/firmalum && php artisan schedule:run >> /dev/null 2>&1
```

**GUARDA**: `Ctrl+O`, `Enter`, `Ctrl+X`

---

## PASO 10: Optimizar Producción

```bash
cd /var/www/firmalum

php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
composer dump-autoload -o

chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

echo "✅ Optimización completada!"
```

---

## PASO 11: Verificar Todo

```bash
# Test servicios
echo "Nginx: $(systemctl is-active nginx)"
echo "PHP-FPM: $(systemctl is-active php8.2-fpm)"
echo "Redis: $(redis-cli ping)"
echo "Supervisor: $(supervisorctl status | grep ancla-worker)"

# Test aplicación
cd /var/www/firmalum
php artisan tinker --execute="DB::connection()->getPdo(); echo 'DB: OK';"

echo ""
echo "✅ Todos los servicios funcionando!"
```

---

## 🌐 CONFIGURAR DNS (AHORA - 5 min)

Sal del servidor temporalmente (`exit`) y ve a tu panel DNS de **firmalum.com**.

Añade estos registros:

```
Tipo    Nombre    Valor              TTL
─────────────────────────────────────────
A       app       159.65.201.32      600
A       *.app     159.65.201.32      600
```

**Espera 5-10 minutos** para propagación.

Verifica desde tu terminal local:
```bash
ping app.firmalum.com
# Debe responder: 159.65.201.32

dig app.firmalum.com
# Debe mostrar A record con 159.65.201.32
```

---

## 🔒 CONFIGURAR SSL (15 min)

Reconecta al servidor:
```bash
ssh -i ~/.ssh/firmalum_prod root@159.65.201.32
```

Ejecuta:
```bash
certbot certonly --manual --preferred-challenges dns \
  -d app.firmalum.com -d *.app.firmalum.com \
  --agree-tos --email mail@robertorubio.es
```

**Certbot te pedirá** añadir un registro TXT:

1. **NO CIERRES LA TERMINAL** (deja certbot esperando)
2. Ve a tu panel DNS
3. Añade:
   ```
   Tipo: TXT
   Nombre: _acme-challenge.app
   Valor: [el valor que certbot te dé]
   TTL: 600
   ```
4. **Espera 2-3 minutos**
5. Verifica desde otra terminal:
   ```bash
   dig _acme-challenge.app.firmalum.com TXT
   # Debe mostrar el valor que añadiste
   ```
6. Vuelve a la terminal con certbot y presiona **Enter**

Si todo va bien, certbot dirá: "Successfully received certificate"

Ahora **actualiza nginx** con los certificados reales:

```bash
nano /etc/nginx/sites-available/firmalum
```

**BUSCA** estas dos líneas:
```nginx
ssl_certificate /etc/ssl/certs/ssl-cert-snakeoil.pem;
ssl_certificate_key /etc/ssl/private/ssl-cert-snakeoil.key;
```

**REEMPLAZA** con:
```nginx
ssl_certificate /etc/letsencrypt/live/app.firmalum.com/fullchain.pem;
ssl_certificate_key /etc/letsencrypt/live/app.firmalum.com/privkey.pem;
```

**GUARDA**: `Ctrl+O`, `Enter`, `Ctrl+X`

```bash
# Test y reload
nginx -t
systemctl reload nginx

# Configurar auto-renewal
echo '0 3 * * * certbot renew --quiet --deploy-hook "systemctl reload nginx"' | crontab -

echo "✅ SSL configurado!"
```

---

## ✅ VERIFICACIÓN FINAL

```bash
# Test aplicación
curl -I https://app.firmalum.com

# Debe mostrar: HTTP/2 200

# Ver logs si hay error
tail -f /var/log/nginx/ancla_error.log
# Ctrl+C para salir
```

---

## 🎉 ¡DEPLOYMENT COMPLETADO!

### Accesos:

**URL**: https://app.firmalum.com/login  
**Email**: mail@robertorubio.es  
**Password**: [el que se generó en PASO 5 - lo anotaste ¿verdad?]

### Verificar:

1. Abre navegador: https://app.firmalum.com
2. Login con tus credenciales
3. Deberías ver el dashboard de Firmalum
4. Ve a Admin → Tenants para crear tu primer tenant

---

## 🔧 Si Algo Falla:

```bash
# Ver logs Laravel
tail -f /var/www/firmalum/storage/logs/laravel.log

# Ver logs Nginx
tail -f /var/log/nginx/ancla_error.log

# Ver status PHP-FPM
systemctl status php8.2-fpm

# Restart todo
systemctl restart php8.2-fpm
systemctl reload nginx
supervisorctl restart ancla-worker:*
```

---

## 📞 Próximos Pasos:

1. ✅ Login y **cambiar password** inmediatamente
2. ✅ Crear tenant de prueba
3. ✅ Testear flujo de firma
4. ✅ Configurar Mailgun (opcional, 10 min más)

**¿Algún problema? Avísame qué error ves y te ayudo a resolverlo** 🚀
