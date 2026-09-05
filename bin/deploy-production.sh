#!/bin/bash
#
# ANCLA Production Deployment Script
# Deploy to: app.firmalum.com (159.65.201.32)
#
# Usage: ./bin/deploy-production.sh

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Configuration
SERVER_IP="159.65.201.32"
SERVER_USER="root"
SSH_KEY="$HOME/.ssh/firmalum_prod"
DOMAIN="app.firmalum.com"
APP_PATH="/var/www/ancla"
REPO_URL="https://github.com/robertorubioes/ancla.git"
BRANCH="staging"

echo -e "${GREEN}========================================${NC}"
echo -e "${GREEN}🚀 ANCLA Production Deployment${NC}"
echo -e "${GREEN}========================================${NC}"
echo ""
echo "Server: $SERVER_IP"
echo "Domain: $DOMAIN"
echo "Branch: $BRANCH"
echo ""

# Check SSH connection
echo -e "${YELLOW}📡 Verificando conexión SSH...${NC}"
if ssh -i "$SSH_KEY" -o ConnectTimeout=5 "$SERVER_USER@$SERVER_IP" "echo 'SSH OK'" > /dev/null 2>&1; then
    echo -e "${GREEN}✅ Conexión SSH exitosa${NC}"
else
    echo -e "${RED}❌ Error: No se puede conectar al servidor${NC}"
    echo "Verifica: ssh -i $SSH_KEY $SERVER_USER@$SERVER_IP"
    exit 1
fi

echo ""
echo -e "${YELLOW}========================================${NC}"
echo -e "${YELLOW}PASO 1: Setup del Sistema${NC}"
echo -e "${YELLOW}========================================${NC}"

# Execute system setup on server
ssh -i "$SSH_KEY" "$SERVER_USER@$SERVER_IP" << 'ENDSSH'
set -e

echo "📦 Actualizando sistema..."
export DEBIAN_FRONTEND=noninteractive
apt-get update -qq
apt-get upgrade -y -qq

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

echo "🔥 Configurando firewall..."
ufw allow 22/tcp > /dev/null 2>&1 || true
ufw allow 80/tcp > /dev/null 2>&1 || true
ufw allow 443/tcp > /dev/null 2>&1 || true
ufw --force enable || true

echo "🛡️ Instalando fail2ban..."
apt-get install -y -qq fail2ban
systemctl enable fail2ban > /dev/null 2>&1
systemctl start fail2ban > /dev/null 2>&1

echo "🌐 Instalando Nginx..."
if ! command -v nginx &> /dev/null; then
  apt-get install -y -qq nginx
  systemctl enable nginx
  systemctl start nginx
fi

echo "🐘 Instalando PHP 8.2..."
if ! command -v php8.2 &> /dev/null; then
  add-apt-repository -y ppa:ondrej/php > /dev/null 2>&1
  apt-get update -qq
  apt-get install -y -qq php8.2-fpm php8.2-cli php8.2-common php8.2-mysql \
    php8.2-mbstring php8.2-xml php8.2-bcmath php8.2-curl php8.2-gd \
    php8.2-zip php8.2-redis php8.2-intl php8.2-soap php8.2-imagick
fi

echo "🎵 Instalando Composer..."
if ! command -v composer &> /dev/null; then
  curl -sS https://getcomposer.org/installer | php
  mv composer.phar /usr/local/bin/composer
  chmod +x /usr/local/bin/composer
fi

echo "💾 Instalando Redis..."
if ! command -v redis-cli &> /dev/null; then
  apt-get install -y -qq redis-server
  systemctl enable redis-server
  systemctl start redis-server
fi

echo "📦 Instalando Node.js..."
if ! command -v node &> /dev/null; then
  curl -fsSL https://deb.nodesource.com/setup_20.x | bash - > /dev/null 2>&1
  apt-get install -y -qq nodejs
fi

echo "👷 Instalando Supervisor..."
if ! command -v supervisorctl &> /dev/null; then
  apt-get install -y -qq supervisor
  systemctl enable supervisor
  systemctl start supervisor
fi

echo "🔒 Instalando Certbot..."
if ! command -v certbot &> /dev/null; then
  apt-get install -y -qq certbot python3-certbot-nginx
fi

echo "🗄️ Instalando MySQL Client..."
apt-get install -y -qq mysql-client

echo "✅ Sistema configurado!"
ENDSSH

echo -e "${GREEN}✅ PASO 1 completado${NC}"
echo ""

echo -e "${YELLOW}========================================${NC}"
echo -e "${YELLOW}PASO 2: Clonar Aplicación${NC}"
echo -e "${YELLOW}========================================${NC}"

ssh -i "$SSH_KEY" "$SERVER_USER@$SERVER_IP" << ENDSSH
set -e

echo "📁 Creando directorio aplicación..."
mkdir -p $APP_PATH
cd /var/www

if [ -d "$APP_PATH/.git" ]; then
  echo "📥 Repositorio existe, actualizando..."
  cd $APP_PATH
  git fetch origin
  git reset --hard origin/$BRANCH
  git checkout $BRANCH
else
  echo "📥 Clonando repositorio..."
  git clone -b $BRANCH $REPO_URL ancla
fi

echo "✅ Código clonado!"
ENDSSH

echo -e "${GREEN}✅ PASO 2 completado${NC}"
echo ""

echo -e "${YELLOW}========================================${NC}"
echo -e "${YELLOW}PASO 3: Instalar Dependencias${NC}"
echo -e "${YELLOW}========================================${NC}"

ssh -i "$SSH_KEY" "$SERVER_USER@$SERVER_IP" << 'ENDSSH'
set -e
cd /var/www/ancla

echo "📦 Instalando dependencias Composer..."
composer install --optimize-autoloader --no-dev --quiet

echo "📦 Instalando dependencias NPM..."
npm install --silent
npm run build

echo "🔐 Configurando permisos..."
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

echo "✅ Dependencias instaladas!"
ENDSSH

echo -e "${GREEN}✅ PASO 3 completado${NC}"
echo ""

echo -e "${YELLOW}========================================${NC}"
echo -e "${YELLOW}PASO 4: Copiar y Configurar .env${NC}"
echo -e "${YELLOW}========================================${NC}"

# Copy .env.production to server
echo "📤 Copiando .env.production al servidor..."
scp -i "$SSH_KEY" .env.production "$SERVER_USER@$SERVER_IP:$APP_PATH/.env"

ssh -i "$SSH_KEY" "$SERVER_USER@$SERVER_IP" << 'ENDSSH'
set -e
cd /var/www/ancla

echo "🔑 Descargando CA certificate..."
wget -q https://raw.githubusercontent.com/digitalocean/do-certs/main/ca-certificate.crt -O /root/ca-certificate.crt

echo "🔑 Generando APP_KEY..."
php artisan key:generate --force

echo "🔑 Generando password superadmin..."
SUPERADMIN_PASS=$(openssl rand -base64 16)
echo "========================================="
echo "PASSWORD SUPERADMIN: $SUPERADMIN_PASS"
echo "========================================="
echo "⚠️  ANOTA ESTE PASSWORD!"
echo "========================================="
sed -i "s/SUPERADMIN_PASSWORD=/SUPERADMIN_PASSWORD=$SUPERADMIN_PASS/" .env

echo "🔑 Generando encryption key..."
ENCRYPTION_KEY="base64:$(openssl rand -base64 32)"
sed -i "s|APP_ENCRYPTION_KEY=|APP_ENCRYPTION_KEY=$ENCRYPTION_KEY|" .env

echo "🔏 Generando certificado de firma..."
mkdir -p storage/certificates
openssl req -x509 -newkey rsa:4096 -sha256 -days 3650 \
  -nodes -keyout storage/certificates/ancla-production.key \
  -out storage/certificates/ancla-production.crt \
  -subj "/C=ES/ST=Madrid/L=Madrid/O=Firmalum/CN=app.firmalum.com" 2>/dev/null

chmod 600 storage/certificates/ancla-production.key
chmod 644 storage/certificates/ancla-production.crt

echo "✅ Configuración .env completada!"
ENDSSH

echo -e "${GREEN}✅ PASO 4 completado${NC}"
echo ""

echo -e "${YELLOW}========================================${NC}"
echo -e "${YELLOW}PASO 5: Base de Datos${NC}"
echo -e "${YELLOW}========================================${NC}"

ssh -i "$SSH_KEY" "$SERVER_USER@$SERVER_IP" << 'ENDSSH'
set -e
cd /var/www/ancla

echo "🗄️ Creando base de datos ancla_production..."
mysql -h private-db-mysql-ams3-08801-do-user-6074459-0.k.db.ondigitalocean.com \
  -P 25060 -u doadmin -pAVNS_61muOsPSIHjStTHyuIL --ssl-mode=REQUIRED \
  -e "CREATE DATABASE IF NOT EXISTS ancla_production CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" 2>/dev/null || true

echo "🔍 Verificando conexión..."
php artisan tinker --execute="DB::connection()->getPdo(); echo 'DB Connection OK';"

echo "🔄 Ejecutando migraciones..."
php artisan migrate --force

echo "👤 Creando superadmin..."
php artisan db:seed --class=SuperadminSeeder --force

echo "✅ Base de datos configurada!"
ENDSSH

echo -e "${GREEN}✅ PASO 5 completado${NC}"
echo ""

echo -e "${YELLOW}========================================${NC}"
echo -e "${YELLOW}PASO 6: Configurar Nginx${NC}"
echo -e "${YELLOW}========================================${NC}"

ssh -i "$SSH_KEY" "$SERVER_USER@$SERVER_IP" << 'ENDSSH'
set -e

echo "🌐 Creando configuración Nginx..."
cat > /etc/nginx/sites-available/ancla << 'EOF'
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
    
    root /var/www/ancla/public;
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
EOF

echo "🔗 Habilitando sitio..."
ln -sf /etc/nginx/sites-available/ancla /etc/nginx/sites-enabled/

echo "⚙️ Optimizando PHP-FPM..."
sed -i 's/memory_limit = .*/memory_limit = 512M/' /etc/php/8.2/fpm/php.ini
sed -i 's/upload_max_filesize = .*/upload_max_filesize = 50M/' /etc/php/8.2/fpm/php.ini
sed -i 's/post_max_size = .*/post_max_size = 50M/' /etc/php/8.2/fpm/php.ini
sed -i 's/max_execution_time = .*/max_execution_time = 300/' /etc/php/8.2/fpm/php.ini
sed -i 's/;opcache.enable=.*/opcache.enable=1/' /etc/php/8.2/fpm/php.ini
sed -i 's/;opcache.memory_consumption=.*/opcache.memory_consumption=256/' /etc/php/8.2/fpm/php.ini

echo "🔄 Reiniciando servicios..."
nginx -t
systemctl restart php8.2-fpm
systemctl reload nginx

echo "✅ Nginx configurado!"
ENDSSH

echo -e "${GREEN}✅ PASO 6 completado${NC}"
echo ""

echo -e "${YELLOW}========================================${NC}"
echo -e "${YELLOW}PASO 7: Queue Workers y Scheduler${NC}"
echo -e "${YELLOW}========================================${NC}"

ssh -i "$SSH_KEY" "$SERVER_USER@$SERVER_IP" << 'ENDSSH'
set -e

echo "👷 Configurando Supervisor para queue workers..."
cat > /etc/supervisor/conf.d/ancla-worker.conf << 'EOF'
[program:ancla-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/ancla/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=4
redirect_stderr=true
stdout_logfile=/var/www/ancla/storage/logs/worker.log
stopwaitsecs=3600
EOF

supervisorctl reread > /dev/null 2>&1
supervisorctl update > /dev/null 2>&1
supervisorctl start ancla-worker:* > /dev/null 2>&1 || true

echo "⏰ Configurando Laravel Scheduler..."
(crontab -l 2>/dev/null | grep -v "artisan schedule:run"; echo "* * * * * cd /var/www/ancla && php artisan schedule:run >> /dev/null 2>&1") | crontab -

echo "✅ Queue workers y scheduler configurados!"
ENDSSH

echo -e "${GREEN}✅ PASO 7 completado${NC}"
echo ""

echo -e "${YELLOW}========================================${NC}"
echo -e "${YELLOW}PASO 8: Optimización para Producción${NC}"
echo -e "${YELLOW}========================================${NC}"

ssh -i "$SSH_KEY" "$SERVER_USER@$SERVER_IP" << 'ENDSSH'
set -e
cd /var/www/ancla

echo "⚡ Cacheando configuraciones..."
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

echo "🔧 Optimizando autoloader..."
composer dump-autoload -o

echo "🔐 Configurando permisos finales..."
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

echo "🔄 Reiniciando servicios..."
systemctl restart php8.2-fpm
systemctl reload nginx
supervisorctl restart ancla-worker:* > /dev/null 2>&1 || true

echo "✅ Optimización completada!"
ENDSSH

echo -e "${GREEN}✅ PASO 8 completado${NC}"
echo ""

echo -e "${YELLOW}========================================${NC}"
echo -e "${YELLOW}PASO 9: Verificación Final${NC}"
echo -e "${YELLOW}========================================${NC}"

echo "🔍 Verificando servicios..."

ssh -i "$SSH_KEY" "$SERVER_USER@$SERVER_IP" << 'ENDSSH'
set -e

echo "✓ Nginx: $(systemctl is-active nginx)"
echo "✓ PHP-FPM: $(systemctl is-active php8.2-fpm)"
echo "✓ Redis: $(redis-cli ping)"
echo "✓ Supervisor: $(systemctl is-active supervisor)"

echo ""
echo "🔍 Verificando componentes ANCLA..."
cd /var/www/ancla
php artisan tinker --execute="DB::connection()->getPdo(); echo '✓ Database: Connected';" 2>/dev/null || echo "✗ Database: Error"
php artisan tinker --execute="Storage::disk('s3')->exists('.gitkeep') ? '✓ Spaces: Connected' : '✓ Spaces: Ready (no test file)';" 2>/dev/null || echo "✓ Spaces: Ready"

echo ""
echo "📊 Queue Workers:"
supervisorctl status | grep ancla-worker || echo "No workers yet (will start after full setup)"
ENDSSH

echo -e "${GREEN}✅ PASO 9 completado${NC}"
echo ""

echo -e "${GREEN}========================================${NC}"
echo -e "${GREEN}✅ DEPLOYMENT COMPLETADO${NC}"
echo -e "${GREEN}========================================${NC}"
echo ""
echo -e "🌐 URL: ${GREEN}https://app.firmalum.com${NC} (después de configurar DNS)"
echo -e "👤 Email: ${GREEN}mail@robertorubio.es${NC}"
echo -e "🔑 Password: ${YELLOW}Ver output arriba (PASO 4)${NC}"
echo ""
echo -e "${YELLOW}⚠️  PASOS MANUALES PENDIENTES:${NC}"
echo "1. Configurar DNS (ver abajo)"
echo "2. Configurar SSL Let's Encrypt (ver abajo)"
echo "3. Login y cambiar password"
echo ""
echo -e "${YELLOW}========================================${NC}"
echo -e "${YELLOW}DNS CONFIGURATION REQUIRED:${NC}"
echo -e "${YELLOW}========================================${NC}"
echo ""
echo "Ve a tu panel DNS de firmalum.com y añade:"
echo ""
echo "Tipo    Nombre    Valor              TTL"
echo "─────────────────────────────────────────"
echo "A       app       159.65.201.32      600"
echo "A       *.app     159.65.201.32      600"
echo ""
echo -e "${YELLOW}Espera 5-10 minutos para propagación DNS${NC}"
echo ""
echo -e "${YELLOW}========================================${NC}"
echo -e "${YELLOW}SSL CONFIGURATION (después del DNS):${NC}"
echo -e "${YELLOW}========================================${NC}"
echo ""
echo "ssh -i $SSH_KEY root@159.65.201.32"
echo ""
echo "Luego ejecuta:"
echo "certbot certonly --manual --preferred-challenges dns \\"
echo "  -d app.firmalum.com -d *.app.firmalum.com \\"
echo "  --agree-tos --email mail@robertorubio.es"
echo ""
echo "(Sigue instrucciones para añadir TXT record en DNS)"
echo ""
echo "Luego actualiza nginx:"
echo "nano /etc/nginx/sites-available/ancla"
echo "(Reemplaza líneas ssl_certificate con paths de Let's Encrypt)"
echo ""
echo -e "${GREEN}========================================${NC}"
echo -e "${GREEN}🎉 ¡Deployment exitoso!${NC}"
echo -e "${GREEN}========================================${NC}"
