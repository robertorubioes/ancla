# Staging Deployment Guide - Firmalum MVP

> 📅 **Fecha**: 2025-12-30  
> 🎯 **Objetivo**: Deploy Sprint 1-5 completo a staging para testing  
> 📦 **Version**: MVP 82% (23/28 historias)  
> ✅ **Status**: READY FOR STAGING DEPLOYMENT

---

## 📊 Estado del Deploy

### Funcionalidad Incluida (Sprint 1-5)

✅ **Core Features Operativas**:
- Autenticación completa (Login, 2FA, recuperación)
- Multi-tenant base con scopes
- Sistema de evidencias (TSA, hash, audit trail, fingerprint, geo, IP, consent)
- Conservación 5+ años con re-sellado TSA
- Verificación pública sin autenticación
- Flujo completo de firma E2E (crear, enviar, firmar, PAdES-B-LT)
- Generación documento final con todas las firmas
- Envío automático de copia a firmantes
- Descarga de PDF y dossier
- Cancelación de procesos
- Dashboard de procesos

✅ **Sprint 6 Parcial**:
- E0-001: Panel superadmin + CRUD tenants (25/25 tests ✅)

⚠️ **Limitaciones Conocidas (Staging Only)**:
- Gestión de usuarios tiene bugs (E0-002 con 3 HIGH issues)
- Sin encriptación at-rest (E2-003 pendiente)
- Invitaciones de usuarios no funcionan completamente

**Tests**: 228 passing (203 Sprint 1-5 + 25 E0-001)

---

## 🚀 Pre-requisitos del Servidor

### Requisitos Mínimos

**Hardware**:
- CPU: 2 cores
- RAM: 4 GB
- Disk: 20 GB SSD
- Bandwidth: 100 Mbps

**Software**:
- Ubuntu 22.04 LTS (o similar)
- PHP 8.2+
- MySQL 8.0+ / MariaDB 10.6+
- Redis 7.0+
- Nginx 1.24+ / Apache 2.4+
- Composer 2.6+
- Node.js 20+ / npm 10+
- Supervisor (para queues)
- Git 2.40+

### Extensiones PHP Requeridas

```bash
sudo apt install -y php8.2-cli php8.2-fpm php8.2-mysql php8.2-redis \
  php8.2-mbstring php8.2-xml php8.2-bcmath php8.2-curl \
  php8.2-zip php8.2-gd php8.2-intl php8.2-soap
```

---

## 📦 Instalación Paso a Paso

### 1. Preparar el Servidor

```bash
# Actualizar sistema
sudo apt update && sudo apt upgrade -y

# Instalar dependencias base
sudo apt install -y git curl wget unzip supervisor nginx mysql-server redis-server

# Instalar PHP 8.2
sudo apt install -y software-properties-common
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update
sudo apt install -y php8.2-fpm php8.2-cli php8.2-mysql php8.2-redis \
  php8.2-mbstring php8.2-xml php8.2-bcmath php8.2-curl php8.2-zip \
  php8.2-gd php8.2-intl php8.2-soap

# Instalar Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# Instalar Node.js 20
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs
```

### 2. Clonar Repositorio

```bash
# Crear directorio de aplicación
sudo mkdir -p /var/www
cd /var/www

# Clonar repositorio (ajustar URL)
sudo git clone https://github.com/your-org/ancla-app.git firmalum-staging
cd firmalum-staging

# Checkout a develop branch (Sprint 1-5 + E0-001)
sudo git checkout develop

# Permisos
sudo chown -R www-data:www-data /var/www/firmalum-staging
sudo chmod -R 755 /var/www/firmalum-staging
sudo chmod -R 775 /var/www/firmalum-staging/storage
sudo chmod -R 775 /var/www/firmalum-staging/bootstrap/cache
```

### 3. Configurar Base de Datos

```bash
# Login a MySQL
sudo mysql -u root

# Crear base de datos y usuario
CREATE DATABASE firmalum_staging CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'firmalum_staging'@'localhost' IDENTIFIED BY 'StrongPasswordHere123!';
GRANT ALL PRIVILEGES ON firmalum_staging.* TO 'firmalum_staging'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

### 4. Configurar Variables de Entorno

```bash
cd /var/www/firmalum-staging

# Copiar .env de ejemplo
sudo cp .env.example .env

# Editar .env
sudo nano .env
```

**Configuración mínima para staging**:

```env
# Application
APP_NAME="Firmalum Staging"
APP_ENV=staging
APP_KEY=  # Se genera con php artisan key:generate
APP_DEBUG=true
APP_URL=https://staging.firmalum.com

# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=firmalum_staging
DB_USERNAME=firmalum_staging
DB_PASSWORD=StrongPasswordHere123!

# Redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

# Cache & Sessions
CACHE_DRIVER=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=database

# Mail (Mailtrap para staging)
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=your_mailtrap_username
MAIL_PASSWORD=your_mailtrap_password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="noreply@staging.firmalum.com"
MAIL_FROM_NAME="Firmalum Staging"

# Filesystem
FILESYSTEM_DISK=local

# TSA (Mock para staging)
TSA_MOCK=true
TSA_URL=http://timestamp.digicert.com
TSA_TIMEOUT=10

# OTP
OTP_LENGTH=6
OTP_EXPIRES_MINUTES=10
OTP_MAX_ATTEMPTS=5
OTP_RATE_LIMIT_HOUR=3

# Signing
SIGNATURE_PADES_LEVEL=B-LT
SIGNATURE_CERT_PATH=storage/certificates/ancla-dev.crt
SIGNATURE_KEY_PATH=storage/certificates/ancla-dev.key
SIGNATURE_KEY_PASSWORD=
SIGNATURE_APPEARANCE_MODE=visible
```

### 5. Instalar Dependencias

```bash
cd /var/www/firmalum-staging

# Instalar dependencias PHP
sudo -u www-data composer install --no-dev --optimize-autoloader

# Instalar dependencias Node
sudo -u www-data npm ci --production

# Build assets
sudo -u www-data npm run build
```

### 6. Ejecutar Migraciones

```bash
cd /var/www/firmalum-staging

# Generar app key
sudo -u www-data php artisan key:generate

# Ejecutar migraciones
sudo -u www-data php artisan migrate --force

# Seed superadmin
sudo -u www-data php artisan db:seed SuperadminSeeder

# Cachear configuración
sudo -u www-data php artisan config:cache
sudo -u www-data php artisan route:cache
sudo -u www-data php artisan view:cache
```

### 7. Configurar Nginx

```bash
sudo nano /etc/nginx/sites-available/firmalum-staging
```

**Configuración Nginx**:

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name staging.firmalum.com;
    
    # Redirect to HTTPS (after SSL setup)
    # return 301 https://$server_name$request_uri;
    
    root /var/www/firmalum-staging/public;
    index index.php index.html;

    charset utf-8;

    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;

    # Increase upload size
    client_max_body_size 50M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
        
        # Timeouts para uploads grandes
        fastcgi_read_timeout 300;
        fastcgi_send_timeout 300;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }

    # Block access to sensitive files
    location ~ /\.(env|git|htaccess) {
        deny all;
    }

    # Cache static assets
    location ~* \.(jpg|jpeg|png|gif|ico|css|js|svg|woff|woff2|ttf|eot)$ {
        expires 30d;
        add_header Cache-Control "public, immutable";
    }
}
```

**Activar sitio**:

```bash
# Crear symlink
sudo ln -s /etc/nginx/sites-available/firmalum-staging /etc/nginx/sites-enabled/

# Remover default
sudo rm /etc/nginx/sites-enabled/default

# Test configuración
sudo nginx -t

# Restart Nginx
sudo systemctl restart nginx
```

### 8. Configurar Queue Worker (Supervisor)

```bash
sudo nano /etc/supervisor/conf.d/firmalum-staging-worker.conf
```

**Configuración Supervisor**:

```ini
[program:firmalum-staging-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/firmalum-staging/artisan queue:work --sleep=3 --tries=3 --max-time=3600 --timeout=60
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/firmalum-staging/storage/logs/worker.log
stopwaitsecs=3600
```

**Iniciar worker**:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start firmalum-staging-worker:*
sudo supervisorctl status
```

### 9. Configurar Cron (Scheduled Tasks)

```bash
sudo crontab -e -u www-data
```

**Agregar línea**:

```cron
* * * * * cd /var/www/firmalum-staging && php artisan schedule:run >> /dev/null 2>&1
```

### 10. SSL con Let's Encrypt (Opcional pero recomendado)

```bash
# Instalar certbot
sudo apt install -y certbot python3-certbot-nginx

# Obtener certificado
sudo certbot --nginx -d staging.firmalum.com

# Auto-renovación (ya configurado por certbot)
sudo systemctl status certbot.timer
```

---

## ✅ Verificación Post-Deploy

### 1. Health Check Básico

```bash
# Ver logs
tail -f /var/www/firmalum-staging/storage/logs/laravel.log

# Verificar queue worker
sudo supervisorctl status firmalum-staging-worker:*

# Verificar permisos
ls -la /var/www/firmalum-staging/storage
ls -la /var/www/firmalum-staging/bootstrap/cache
```

### 2. Acceso a la Aplicación

**URLs a probar**:

1. **Homepage**: `https://staging.firmalum.com/`
   - Debe mostrar página de bienvenida o login

2. **Login**: `https://staging.firmalum.com/login`
   - Credenciales superadmin:
     - Email: `superadmin@firmalum.com`
     - Password: `password` (⚠️ cambiar en producción)

3. **Panel Superadmin**: `https://staging.firmalum.com/admin/tenants`
   - Debe mostrar dashboard de organizaciones
   - Debe permitir crear nuevo tenant

4. **API de Verificación**: `https://staging.firmalum.com/api/verify/{code}`
   - Debe retornar JSON con estado de documento

### 3. Test de Flujo Completo

**Escenario: Firma de documento E2E**

1. Login como superadmin
2. Crear organización "Test Corp"
3. Logout y login como admin de "Test Corp"
4. Subir documento PDF
5. Crear proceso de firma con 2 firmantes
6. Verificar emails enviados (Mailtrap)
7. Acceder como firmante 1 (enlace único)
8. Solicitar OTP
9. Verificar código OTP
10. Dibujar firma
11. Firmar documento
12. Repetir con firmante 2
13. Verificar documento final generado
14. Verificar emails de copia enviados
15. Descargar PDF firmado
16. Descargar dossier de evidencias
17. Verificar integridad en API pública

**Tiempo estimado**: 15-20 minutos

### 4. Monitoreo de Performance

```bash
# CPU y memoria
htop

# Nginx logs
sudo tail -f /var/log/nginx/access.log
sudo tail -f /var/log/nginx/error.log

# Laravel logs
tail -f /var/www/firmalum-staging/storage/logs/laravel.log

# Queue worker logs
tail -f /var/www/firmalum-staging/storage/logs/worker.log

# MySQL queries lentas
sudo tail -f /var/log/mysql/slow-query.log
```

---

## 🔧 Troubleshooting

### Issue #1: Permisos de Storage

```bash
sudo chown -R www-data:www-data /var/www/firmalum-staging/storage
sudo chmod -R 775 /var/www/firmalum-staging/storage
sudo chmod -R 775 /var/www/firmalum-staging/bootstrap/cache
```

### Issue #2: Queue Worker No Procesa Jobs

```bash
# Verificar worker
sudo supervisorctl status firmalum-staging-worker:*

# Restart worker
sudo supervisorctl restart firmalum-staging-worker:*

# Ver logs
tail -f /var/www/firmalum-staging/storage/logs/worker.log
```

### Issue #3: Emails No Se Envían

```bash
# Verificar configuración mail
php artisan tinker
>>> Mail::raw('Test email', function($msg) { $msg->to('test@example.com')->subject('Test'); });

# Verificar Mailtrap inbox
# https://mailtrap.io/inboxes
```

### Issue #4: 500 Internal Server Error

```bash
# Ver logs detallados
tail -f /var/www/firmalum-staging/storage/logs/laravel.log
tail -f /var/log/nginx/error.log

# Limpiar cache
cd /var/www/firmalum-staging
sudo -u www-data php artisan cache:clear
sudo -u www-data php artisan config:clear
sudo -u www-data php artisan route:clear
sudo -u www-data php artisan view:clear

# Re-cachear
sudo -u www-data php artisan config:cache
sudo -u www-data php artisan route:cache
sudo -u www-data php artisan view:cache
```

### Issue #5: Migraciones Fallan

```bash
# Rollback y re-ejecutar
cd /var/www/firmalum-staging
sudo -u www-data php artisan migrate:rollback
sudo -u www-data php artisan migrate --force

# Si persiste, fresh install
sudo -u www-data php artisan migrate:fresh --seed --force
```

---

## 📋 Checklist de Deployment

Pre-Deploy:
- [ ] Servidor preparado con requisitos mínimos
- [ ] PHP 8.2+ instalado con extensiones
- [ ] MySQL/MariaDB configurado
- [ ] Redis instalado y corriendo
- [ ] Nginx/Apache instalado
- [ ] Supervisor instalado
- [ ] DNS apuntando a servidor (staging.firmalum.com)

Deploy:
- [ ] Repositorio clonado en `/var/www/firmalum-staging`
- [ ] Branch `develop` checked out
- [ ] `.env` configurado correctamente
- [ ] Dependencias instaladas (composer + npm)
- [ ] Assets built (npm run build)
- [ ] App key generada
- [ ] Migraciones ejecutadas
- [ ] Superadmin seeded
- [ ] Configuración cacheada
- [ ] Nginx configurado y testeado
- [ ] Queue worker configurado en Supervisor
- [ ] Cron configurado para scheduled tasks
- [ ] SSL configurado (Let's Encrypt)

Post-Deploy:
- [ ] Health check básico pasando
- [ ] Login superadmin funciona
- [ ] Panel de tenants accesible
- [ ] Test de flujo E2E completado
- [ ] Emails en Mailtrap visibles
- [ ] Logs sin errores críticos
- [ ] Queue worker procesando jobs
- [ ] Performance aceptable (<2s response time)

---

## 🚀 Comandos de Deploy Rápido

Para futuras actualizaciones:

```bash
#!/bin/bash
# deploy-staging.sh

cd /var/www/firmalum-staging

# Maintenance mode
sudo -u www-data php artisan down

# Pull latest code
sudo git pull origin develop

# Install dependencies
sudo -u www-data composer install --no-dev --optimize-autoloader
sudo -u www-data npm ci --production
sudo -u www-data npm run build

# Run migrations
sudo -u www-data php artisan migrate --force

# Clear and cache
sudo -u www-data php artisan cache:clear
sudo -u www-data php artisan config:cache
sudo -u www-data php artisan route:cache
sudo -u www-data php artisan view:cache

# Restart services
sudo supervisorctl restart firmalum-staging-worker:*
sudo systemctl reload php8.2-fpm
sudo systemctl reload nginx

# Up
sudo -u www-data php artisan up

echo "✅ Deploy completado"
```

**Permisos**:
```bash
chmod +x deploy-staging.sh
```

**Uso**:
```bash
./deploy-staging.sh
```

---

## 📊 Estado del Sistema

### Funcionalidad Operativa (Staging)

| Feature | Status | Notes |
|---------|--------|-------|
| Autenticación | ✅ | Login, 2FA, recuperación |
| Multi-tenant base | ✅ | 1 tenant operativo |
| Upload documentos | ✅ | Validación completa |
| Proceso de firma | ✅ | Crear, enviar emails |
| OTP verification | ✅ | 6 dígitos, 10 min |
| Firma manuscrita | ✅ | Canvas + touch |
| Firma PAdES | ✅ | B-LT con TSA |
| Documento final | ✅ | Merge + certification page |
| Envío copias | ✅ | Email automático |
| Descarga | ✅ | PDF + dossier + ZIP |
| Verificación pública | ✅ | API REST sin auth |
| Panel superadmin | ✅ | CRUD tenants |
| Gestión usuarios | ⚠️ | Bugs conocidos (E0-002) |
| Encriptación | ❌ | Pendiente E2-003 |

### Métricas de Performance (Target)

- **Response time**: <2s (homepage)
- **Upload time**: <5s (10MB PDF)
- **Firma time**: <3s (PAdES generation)
- **Email delivery**: <30s (via queue)
- **API verification**: <500ms

### Límites Staging

- **Usuarios concurrentes**: 10-20
- **Documentos/día**: 100
- **Storage**: 10 GB
- **Procesos activos**: 50

---

## 📞 Soporte

**En caso de issues**:
1. Revisar logs en `/var/www/firmalum-staging/storage/logs/`
2. Consultar sección Troubleshooting
3. Contactar a Tech Lead del proyecto
4. Documentar issue en GitHub

---

**Deployment realizado por**: Orchestrator Agent  
**Fecha**: 2025-12-30  
**Version**: MVP Sprint 1-5 + E0-001 (82% complete)  
**Next**: Completar Sprint 6 para producción (2-3 días)
