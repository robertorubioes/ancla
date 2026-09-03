# Guía de Deployment a Producción - Digital Ocean

> **Versión**: 1.0.0  
> **Fecha**: 2026-01-01  
> **Estado del Proyecto**: MVP 100% Completo (28/28 historias) ✅

## 📋 Tabla de Contenidos

1. [Resumen Ejecutivo](#resumen-ejecutivo)
2. [Información Requerida](#información-requerida)
3. [Arquitectura de Producción](#arquitectura-de-producción)
4. [Configuración del Servidor](#configuración-del-servidor)
5. [Base de Datos](#base-de-datos)
6. [Almacenamiento (Spaces/S3)](#almacenamiento-spacess3)
7. [Email (SMTP/SES)](#email-smtpses)
8. [SSL/HTTPS](#sslhttps)
9. [Variables de Entorno](#variables-de-entorno)
10. [Deployment Steps](#deployment-steps)
11. [Post-Deployment](#post-deployment)
12. [Monitoreo y Mantenimiento](#monitoreo-y-mantenimiento)
13. [Troubleshooting](#troubleshooting)

---

## 🎯 Resumen Ejecutivo

Firmalum es una plataforma SaaS multi-tenant de firma electrónica avanzada con **28 historias completadas** que incluye:

### Funcionalidades Implementadas ✅

**Core Features:**
- ✅ Firma electrónica PAdES-B-LT (eIDAS compliant)
- ✅ Multi-tenant con aislamiento completo
- ✅ RBAC (4 roles: super_admin, admin, operator, viewer)
- ✅ Gestión de organizaciones y usuarios
- ✅ Upload de documentos PDF con validación exhaustiva
- ✅ Proceso de firma completo (secuencial/paralelo)
- ✅ OTP verificación por email
- ✅ 3 tipos de firma (manuscrita, tipográfica, upload)
- ✅ Timestamp TSA cualificado
- ✅ Generación documento final firmado
- ✅ Entrega automática a firmantes
- ✅ Dashboard de monitoreo
- ✅ Cancelación de procesos
- ✅ Descarga de documentos y dossier de evidencias

**Security & Compliance:**
- ✅ Encriptación AES-256-GCM at-rest
- ✅ Backup automático de documentos encriptados
- ✅ API pública de verificación
- ✅ QR codes de verificación
- ✅ Audit trail inmutable (hash-chaining)
- ✅ Captura de evidencias completas (IP, geolocalización, device fingerprint, consent)
- ✅ Conservación 5+ años (tiers: hot/cold/archive)
- ✅ Re-sellado TSA automático

**Tests & Quality:**
- ✅ 246 tests automatizados
- ✅ Laravel Pint: 0 issues
- ✅ Security audits completados
- ✅ Code reviews aprobados

---

## 📝 Información Requerida

Antes de proceder con el deployment, necesito la siguiente información:

### 1. Digital Ocean Access

```yaml
REQUERIDO:
  - Personal Access Token de Digital Ocean
  - Región preferida (ej: nyc3, fra1, ams3)
  - Plan de Droplet (recomendado: Basic 4GB+ para producción)
  - SSH Key para acceso al servidor
```

### 2. Dominio y DNS

```yaml
REQUERIDO:
  - Dominio principal (ej: firmalum.com)
  - Acceso a panel de DNS (Cloudflare, Route53, etc.)
  - Wildcard subdomain support (*.firmalum.com para multi-tenant)
  
OPCIONAL:
  - CDN configurado
  - Cloudflare proxy (recomendado)
```

### 3. Base de Datos

```yaml
OPCIÓN A - Digital Ocean Managed Database (RECOMENDADO):
  - Crear MySQL 8.0+ Managed Database
  - Plan: Basic 1GB+ (escalable)
  - Región: misma que Droplet
  
OPCIÓN B - Base de datos en el mismo Droplet:
  - MySQL 8.0+ en el servidor
  - Solo para testing/staging
```

### 4. Almacenamiento (Digital Ocean Spaces o AWS S3)

```yaml
OPCIÓN A - Digital Ocean Spaces (RECOMENDADO):
  - Crear Space en la misma región
  - Access Key ID
  - Secret Access Key
  - Bucket name (ej: ancla-production)
  - Endpoint (ej: ams3.digitaloceanspaces.com)
  
OPCIÓN B - AWS S3:
  - AWS Access Key ID
  - AWS Secret Access Key
  - Bucket name
  - Región
```

### 5. Email Service

```yaml
OPCIÓN A - Amazon SES (RECOMENDADO para producción):
  - AWS Access Key ID
  - AWS Secret Access Key
  - Región SES
  - Dominio verificado en SES
  - Emails verificados (sandbox) o Production access
  
OPCIÓN B - SMTP Transaccional (Mailgun, SendGrid, Postmark):
  - SMTP Host
  - SMTP Port (587 o 465)
  - SMTP Username
  - SMTP Password
  - From Address verificado
  
OPCIÓN C - Digital Ocean Email:
  - Configurar SPF/DKIM records
  - SMTP directo desde servidor (no recomendado para alto volumen)
```

### 6. Certificados y Keys

```yaml
REQUERIDO:
  - Master Encryption Key (se genera o se proporciona)
  - Certificado X.509 para firmas PAdES:
    * Opción 1: Self-signed (desarrollo/testing)
    * Opción 2: CA-issued (DigiCert, GlobalSign) - RECOMENDADO producción
  
OPCIONAL:
  - TSA Provider credentials (si no usa mock)
```

### 7. Monitoring & Logs (Opcional pero Recomendado)

```yaml
OPCIONES:
  - Sentry DSN (error tracking)
  - New Relic License Key (APM)
  - Papertrail endpoint (log aggregation)
  - Digital Ocean Monitoring (built-in)
```

### 8. Backup Strategy

```yaml
REQUERIDO:
  - Retention policy (días de backup)
  - Backup schedule (diario recomendado)
  - Backup storage location (Spaces/S3)
  
DIGITAL OCEAN:
  - Snapshots automáticos del Droplet (recomendado: semanal)
  - Database backups automáticos (si Managed DB)
```

### 9. Configuraciones de Seguridad

```yaml
REQUERIDO:
  - Firewall rules (puerto 80, 443, 22)
  - SSH Key-only authentication
  - Fail2ban configuration
  - Rate limiting thresholds
  
OPCIONAL:
  - VPC (Virtual Private Cloud)
  - Load Balancer (si alta disponibilidad)
```

### 10. Usuarios y Accesos Iniciales

```yaml
REQUERIDO:
  - Email del super admin inicial
  - Nombre del super admin
  - Password temporal del super admin
  
OPCIONAL:
  - Lista de tenants iniciales para crear
  - Lista de usuarios admin por tenant
```

---

## 🏗️ Arquitectura de Producción

### Opción A: Single Droplet (Pequeña/Mediana Escala)

```
┌─────────────────────────────────────────────┐
│         Digital Ocean Droplet               │
│                                             │
│  ┌─────────────────────────────────────┐   │
│  │     Nginx (Reverse Proxy)           │   │
│  │     SSL: Let's Encrypt              │   │
│  └─────────────────────────────────────┘   │
│                  │                          │
│  ┌───────────────▼──────────────────────┐  │
│  │     PHP 8.2+ FPM                     │  │
│  │     Laravel Queue Worker             │  │
│  │     Laravel Scheduler (cron)         │  │
│  └──────────────────────────────────────┘  │
│                  │                          │
│  ┌───────────────▼──────────────────────┐  │
│  │     Redis (Cache + Queue)            │  │
│  └──────────────────────────────────────┘  │
└─────────────────────────────────────────────┘
                  │
    ┌─────────────┼─────────────┐
    │             │             │
┌───▼───┐   ┌────▼─────┐   ┌──▼────┐
│ MySQL │   │  Spaces  │   │  SES  │
│  DB   │   │ Storage  │   │ Email │
└───────┘   └──────────┘   └───────┘
```

**Specs Recomendadas:**
- Droplet: Basic 4GB RAM, 2 vCPUs, 80GB SSD ($24/mo)
- MySQL: Managed DB 1GB ($15/mo)
- Spaces: 250GB + transfer ($5/mo)
- **Total estimado**: ~$45-50/mo

### Opción B: Alta Disponibilidad (Gran Escala)

```
┌────────────────────────────────────────────┐
│         Load Balancer                      │
│         (Digital Ocean LB)                 │
└────────────┬───────────────────────────────┘
             │
      ┌──────┴──────┐
      │             │
┌─────▼─────┐  ┌───▼──────┐
│ Droplet 1 │  │ Droplet 2│  (Auto-scaling)
│ App Server│  │App Server│
└─────┬─────┘  └────┬─────┘
      │             │
      └──────┬──────┘
             │
    ┌────────┼────────┐
    │        │        │
┌───▼───┐ ┌─▼────┐ ┌─▼────┐
│ MySQL │ │Spaces│ │Redis │
│Cluster│ │ CDN  │ │Cluster│
└───────┘ └──────┘ └──────┘
```

**Specs Recomendadas:**
- Load Balancer: $10/mo
- Droplets: 2x Basic 4GB ($48/mo)
- MySQL: Production 4GB ($60/mo)
- Spaces: 1TB + CDN ($30/mo)
- Redis: Managed 1GB ($15/mo)
- **Total estimado**: ~$165/mo

---

## 🖥️ Configuración del Servidor

### 1. Crear Droplet en Digital Ocean

```bash
# Opción A: Desde Digital Ocean Console
1. Create Droplet
2. Ubuntu 22.04 LTS x64
3. Regular Intel (4GB RAM, 2 vCPUs)
4. Región: ams3 (Amsterdam) o fra1 (Frankfurt) para Europa
5. VPC: Default
6. Add SSH Key
7. Hostname: ancla-production
8. Enable backups (recomendado)

# Opción B: Usando doctl (CLI)
doctl compute droplet create ancla-production \
  --region ams3 \
  --image ubuntu-22-04-x64 \
  --size s-2vcpu-4gb \
  --ssh-keys YOUR_SSH_KEY_ID \
  --enable-backups \
  --enable-monitoring
```

### 2. Configuración Inicial del Servidor

```bash
# Conectar por SSH
ssh root@YOUR_DROPLET_IP

# Actualizar sistema
apt update && apt upgrade -y

# Crear usuario deploy (no usar root)
adduser deploy
usermod -aG sudo deploy
mkdir -p /home/deploy/.ssh
cp ~/.ssh/authorized_keys /home/deploy/.ssh/
chown -R deploy:deploy /home/deploy/.ssh
chmod 700 /home/deploy/.ssh
chmod 600 /home/deploy/.ssh/authorized_keys

# Configurar firewall
ufw allow 22/tcp
ufw allow 80/tcp
ufw allow 443/tcp
ufw --force enable

# Instalar fail2ban
apt install -y fail2ban
systemctl enable fail2ban
systemctl start fail2ban

# Deshabilitar root login
sed -i 's/PermitRootLogin yes/PermitRootLogin no/' /etc/ssh/sshd_config
systemctl restart sshd

# Salir y reconectar como deploy
exit
ssh deploy@YOUR_DROPLET_IP
```

### 3. Instalar Software Stack (LEMP)

```bash
# Instalar Nginx
sudo apt install -y nginx
sudo systemctl enable nginx
sudo systemctl start nginx

# Instalar MySQL Client (si usas Managed DB)
sudo apt install -y mysql-client

# Instalar PHP 8.2+
sudo add-apt-repository -y ppa:ondrej/php
sudo apt update
sudo apt install -y php8.2-fpm php8.2-cli php8.2-common \
  php8.2-mysql php8.2-mbstring php8.2-xml php8.2-bcmath \
  php8.2-curl php8.2-gd php8.2-zip php8.2-redis \
  php8.2-intl php8.2-soap php8.2-imagick

# Instalar Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
sudo chmod +x /usr/local/bin/composer

# Instalar Redis
sudo apt install -y redis-server
sudo systemctl enable redis-server
sudo systemctl start redis-server

# Instalar Node.js (para compilar assets)
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs

# Instalar supervisor (para queue workers)
sudo apt install -y supervisor
sudo systemctl enable supervisor
sudo systemctl start supervisor

# Instalar certbot (Let's Encrypt)
sudo apt install -y certbot python3-certbot-nginx
```

### 4. Configurar Nginx

```bash
# Crear configuración para Firmalum
sudo nano /etc/nginx/sites-available/firmalum
```

```nginx
# /etc/nginx/sites-available/firmalum

# Redirect HTTP to HTTPS
server {
    listen 80;
    listen [::]:80;
    server_name firmalum.com *.firmalum.com;
    
    return 301 https://$host$request_uri;
}

# HTTPS Server
server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name firmalum.com *.firmalum.com;
    
    root /var/www/firmalum/public;
    index index.php index.html;
    
    # SSL Certificates (Let's Encrypt)
    ssl_certificate /etc/letsencrypt/live/firmalum.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/firmalum.com/privkey.pem;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;
    ssl_prefer_server_ciphers on;
    
    # Security Headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
    
    # Logs
    access_log /var/log/nginx/ancla_access.log;
    error_log /var/log/nginx/ancla_error.log;
    
    # Max upload size (para PDFs grandes)
    client_max_body_size 50M;
    
    # PHP-FPM
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
    
    # Deny access to sensitive files
    location ~ /\.(?!well-known).* {
        deny all;
    }
    
    # Cache static assets
    location ~* \.(jpg|jpeg|png|gif|ico|css|js|svg|woff|woff2|ttf|eot)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }
}
```

```bash
# Habilitar sitio
sudo ln -s /etc/nginx/sites-available/firmalum /etc/nginx/sites-enabled/
sudo rm /etc/nginx/sites-enabled/default

# Test y reload
sudo nginx -t
sudo systemctl reload nginx
```

### 5. Configurar PHP-FPM

```bash
# Optimizar PHP-FPM para producción
sudo nano /etc/php/8.2/fpm/php.ini
```

```ini
# Configuraciones recomendadas
memory_limit = 512M
upload_max_filesize = 50M
post_max_size = 50M
max_execution_time = 300
max_input_time = 300
date.timezone = Europe/Madrid
opcache.enable = 1
opcache.memory_consumption = 256
opcache.interned_strings_buffer = 16
opcache.max_accelerated_files = 10000
opcache.validate_timestamps = 0
opcache.revalidate_freq = 0
```

```bash
# Restart PHP-FPM
sudo systemctl restart php8.2-fpm
```

---

## 🗄️ Base de Datos

### Opción A: Digital Ocean Managed Database (Recomendado)

```bash
# 1. Crear Managed Database desde DO Console
# - MySQL 8.0+
# - Plan: Basic 1GB ($15/mo) o superior
# - Región: misma que Droplet
# - Enable automatic backups

# 2. Obtener connection details
# - Host: mysql-cluster-firmalum-do-user-123456-0.db.ondigitalocean.com
# - Port: 25060
# - Username: doadmin
# - Password: [generado por DO]
# - Database: defaultdb

# 3. Crear base de datos para Firmalum
mysql -h mysql-cluster-firmalum-do-user-123456-0.db.ondigitalocean.com \
      -P 25060 -u doadmin -p

CREATE DATABASE ancla_production CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'ancla_user'@'%' IDENTIFIED BY 'STRONG_PASSWORD_HERE';
GRANT ALL PRIVILEGES ON ancla_production.* TO 'ancla_user'@'%';
FLUSH PRIVILEGES;
EXIT;

# 4. Configurar SSL (DO Managed DB incluye SSL)
# Descargar certificado CA desde DO Console
wget https://raw.githubusercontent.com/digitalocean/do-certs/main/ca-certificate.crt -O /home/deploy/ca-certificate.crt
```

### Opción B: MySQL en Droplet (Solo Testing)

```bash
# Instalar MySQL
sudo apt install -y mysql-server

# Securizar instalación
sudo mysql_secure_installation

# Crear base de datos
sudo mysql

CREATE DATABASE ancla_production CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'ancla_user'@'localhost' IDENTIFIED BY 'STRONG_PASSWORD_HERE';
GRANT ALL PRIVILEGES ON ancla_production.* TO 'ancla_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

---

## 📦 Almacenamiento (Spaces/S3)

### Digital Ocean Spaces (Recomendado)

```bash
# 1. Crear Space desde DO Console
# - Name: ancla-production
# - Region: ams3 (misma que Droplet)
# - CDN: Enable (recomendado)
# - File Listing: Restrict

# 2. Generar Access Keys
# Settings → API → Spaces access keys → Generate New Key
# - Name: ancla-production-key
# - Access Key ID: DO00ABCDEFGHIJKLMNOP
# - Secret Access Key: [copiar y guardar seguro]

# 3. Configurar CORS (si API frontend separado)
# En Space settings → CORS Configuration:
```

```xml
<?xml version="1.0" encoding="UTF-8"?>
<CORSConfiguration xmlns="http://s3.amazonaws.com/doc/2006-03-01/">
  <CORSRule>
    <AllowedOrigin>https://firmalum.com</AllowedOrigin>
    <AllowedOrigin>https://*.firmalum.com</AllowedOrigin>
    <AllowedMethod>GET</AllowedMethod>
    <AllowedMethod>POST</AllowedMethod>
    <AllowedMethod>PUT</AllowedMethod>
    <AllowedMethod>DELETE</AllowedMethod>
    <AllowedHeader>*</AllowedHeader>
  </CORSRule>
</CORSConfiguration>
```

### Estructura de Carpetas en Spaces

```
ancla-production/
├── documents/              # PDFs originales
│   ├── {tenant_id}/
│   │   ├── {year}/
│   │   │   └── {month}/
│   │   │       └── {uuid}.pdf
├── signed/                 # PDFs firmados
│   ├── {tenant_id}/
│   │   ├── {year}/
│   │   │   └── {month}/
│   │   │       └── {uuid}_signed.pdf
├── final/                  # Documentos finales completos
│   ├── {tenant_id}/
│   │   ├── {year}/
│   │   │   └── {month}/
│   │   │       └── {uuid}_final.pdf
├── evidence/               # Dossiers de evidencias
│   ├── {tenant_id}/
│   │   ├── {year}/
│   │   │   └── {month}/
│   │   │       └── evidence_{uuid}.pdf
├── backups/                # Backups automáticos encriptados
│   ├── {year}/
│   │   └── {month}/
│   │       └── backup_{date}.tar.gz.enc
└── archive/                # Documentos archivados (>1 año)
    ├── cold/
    └── glacier/
```

---

## 📧 Email (SMTP/SES)

### Opción A: Amazon SES (Recomendado)

```bash
# 1. Configurar en AWS Console
# - Región: eu-west-1 (Irlanda) o us-east-1 (Virginia)
# - Verify domain: firmalum.com
# - Add DNS records (SPF, DKIM, DMARC)

# 2. DNS Records a añadir
```

```dns
# SPF
firmalum.com. IN TXT "v=spf1 include:amazonses.com ~all"

# DKIM (3 records proporcionados por SES)
abcdefghijklmnop._domainkey.firmalum.com. IN CNAME abcdefghijklmnop.dkim.amazonses.com.
xyz123456789._domainkey.firmalum.com. IN CNAME xyz123456789.dkim.amazonses.com.
qwerty123456._domainkey.firmalum.com. IN CNAME qwerty123456.dkim.amazonses.com.

# DMARC
_dmarc.firmalum.com. IN TXT "v=DMARC1; p=quarantine; rua=mailto:dmarc@firmalum.com"
```

```bash
# 3. Crear IAM User para SMTP
# - Create user: firmalum-ses-smtp
# - Attach policy: AmazonSesSendingAccess
# - Generate SMTP credentials

# 4. Variables de entorno (ver sección Variables)
MAIL_MAILER=ses
MAIL_FROM_ADDRESS=noreply@firmalum.com
MAIL_FROM_NAME="Firmalum"
AWS_ACCESS_KEY_ID=AKIA...
AWS_SECRET_ACCESS_KEY=...
AWS_DEFAULT_REGION=eu-west-1
```

### Opción B: SMTP Transaccional (Mailgun/SendGrid)

```bash
# Ejemplo con Mailgun
MAIL_MAILER=smtp
MAIL_HOST=smtp.eu.mailgun.org
MAIL_PORT=587
MAIL_USERNAME=postmaster@mg.firmalum.com
MAIL_PASSWORD=your_mailgun_password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@firmalum.com
MAIL_FROM_NAME="Firmalum"
```

---

## 🔒 SSL/HTTPS

### Let's Encrypt (Gratuito y Automático)

```bash
# 1. Obtener certificado wildcard (para multi-tenant)
sudo certbot certonly --manual --preferred-challenges dns \
  -d firmalum.com -d *.firmalum.com

# 2. Añadir TXT record en DNS
# Certbot pedirá añadir un TXT record _acme-challenge.firmalum.com
# con un valor específico. Añadir en tu panel DNS y esperar propagación.

# 3. Verificar DNS propagation
dig _acme-challenge.firmalum.com TXT

# 4. Continuar con certbot (presionar Enter)
# Certificados se guardan en:
# /etc/letsencrypt/live/firmalum.com/fullchain.pem
# /etc/letsencrypt/live/firmalum.com/privkey.pem

# 5. Auto-renewal (configurar cron)
sudo crontab -e
# Añadir línea:
0 3 * * * certbot renew --quiet --deploy-hook "systemctl reload nginx"

# 6. Test configuración SSL
sudo nginx -t
sudo systemctl reload nginx

# Verificar en: https://www.ssllabs.com/ssltest/
```

---

## 🔐 Variables de Entorno

Crear archivo `.env` de producción en el servidor:

```bash
# En el servidor
cd /var/www/firmalum
nano .env
```

```bash
# .env PRODUCTION

# ============================================
# APP CONFIG
# ============================================
APP_NAME="Firmalum"
APP_ENV=production
APP_KEY=base64:GENERATE_WITH_php_artisan_key:generate
APP_DEBUG=false
APP_URL=https://firmalum.com
APP_TIMEZONE=Europe/Madrid
APP_LOCALE=es

# ============================================
# DATABASE (Digital Ocean Managed DB)
# ============================================
DB_CONNECTION=mysql
DB_HOST=mysql-cluster-firmalum-XXXXX.db.ondigitalocean.com
DB_PORT=25060
DB_DATABASE=ancla_production
DB_USERNAME=ancla_user
DB_PASSWORD=YOUR_STRONG_DATABASE_PASSWORD_HERE
MYSQL_ATTR_SSL_CA=/home/deploy/ca-certificate.crt

# ============================================
# REDIS (Cache + Queue)
# ============================================
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
REDIS_CLIENT=phpredis
CACHE_DRIVER=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

# ============================================
# MAIL (Amazon SES)
# ============================================
MAIL_MAILER=ses
MAIL_FROM_ADDRESS=noreply@firmalum.com
MAIL_FROM_NAME="Firmalum"
# SES lleva sus propias claves: AWS_ACCESS_KEY_ID y companeras las usa el
# disco s3, que aqui apunta a DigitalOcean Spaces con credenciales distintas.
AWS_SES_KEY=AKIA_YOUR_AWS_KEY_ID
AWS_SES_SECRET=YOUR_AWS_SECRET_KEY
AWS_SES_REGION=eu-west-1

# ============================================
# FILESYSTEM (Digital Ocean Spaces)
# ============================================
FILESYSTEM_DISK=s3
AWS_BUCKET=ancla-production
AWS_DEFAULT_REGION=ams3
AWS_ENDPOINT=https://ams3.digitaloceanspaces.com
AWS_USE_PATH_STYLE_ENDPOINT=false
AWS_ACCESS_KEY_ID=DO00_YOUR_SPACES_KEY_ID
AWS_SECRET_ACCESS_KEY=YOUR_SPACES_SECRET_KEY

# ============================================
# ENCRYPTION (AES-256-GCM)
# ============================================
APP_ENCRYPTION_KEY=base64:GENERATE_WITH_php_artisan_encryption:generate
ENCRYPTION_KEY_VERSION=v1
ENCRYPTION_KEY_CACHE_TTL=3600

# ============================================
# BACKUP CONFIG
# ============================================
BACKUP_ENCRYPTION_ENABLED=true
BACKUP_SCHEDULE="0 2 * * *"
BACKUP_RETENTION_DAYS=30
BACKUP_DISK=s3

# ============================================
# SIGNING & CERTIFICATES
# ============================================
SIGNATURE_PADES_LEVEL=B-LT
SIGNATURE_CERT_PATH=storage/certificates/ancla-production.crt
SIGNATURE_KEY_PATH=storage/certificates/ancla-production.key
SIGNATURE_KEY_PASSWORD=YOUR_CERT_PASSWORD_IF_ANY
SIGNATURE_APPEARANCE_MODE=visible
SIGNATURE_PAGE=last
SIGNATURE_TSA_QUALIFIED=true

# ============================================
# TSA (Timestamp Authority)
# ============================================
TSA_MOCK=false
TSA_URL=http://timestamp.digicert.com
TSA_TIMEOUT=30

# ============================================
# OTP CONFIG
# ============================================
OTP_LENGTH=6
OTP_EXPIRES_MINUTES=10
OTP_MAX_ATTEMPTS=5
OTP_RATE_LIMIT_HOUR=3

# ============================================
# LOGGING & MONITORING
# ============================================
LOG_CHANNEL=stack
LOG_STACK=single,daily
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=warning

# Sentry (Error Tracking - OPCIONAL)
SENTRY_LARAVEL_DSN=https://your_sentry_dsn@sentry.io/project_id

# ============================================
# SECURITY & RATE LIMITING
# ============================================
RATE_LIMIT_API_PUBLIC=60,1
RATE_LIMIT_API_PUBLIC_DAILY=1000,1440

# ============================================
# SUPERADMIN
# ============================================
SUPERADMIN_EMAIL=admin@firmalum.com
SUPERADMIN_NAME="Super Admin"
SUPERADMIN_PASSWORD=CHANGE_THIS_STRONG_PASSWORD_IMMEDIATELY

# ============================================
# MISC
# ============================================
BROADCAST_DRIVER=log
VITE_APP_NAME="${APP_NAME}"
```

### Generar Keys de Encriptación

```bash
# En el servidor
cd /var/www/firmalum

# Generar APP_KEY
php artisan key:generate

# Generar ENCRYPTION_KEY (comando custom)
php artisan encryption:generate
# O manualmente:
# APP_ENCRYPTION_KEY=base64:$(openssl rand -base64 32)
```

---

## 🚀 Deployment Steps

### 1. Clonar Repositorio

```bash
# Crear directorio
sudo mkdir -p /var/www/firmalum
sudo chown -R deploy:deploy /var/www/firmalum
cd /var/www

# Clonar desde GitHub (branch main para producción)
git clone -b main https://github.com/robertorubioes/ancla.git firmalum
cd firmalum

# Configurar git para auto-update
git config pull.rebase false
```

### 2. Instalar Dependencias

```bash
cd /var/www/firmalum

# Composer (producción sin dev dependencies)
composer install --optimize-autoloader --no-dev

# NPM (compilar assets)
npm install
npm run build

# Permisos Laravel
sudo chown -R deploy:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
```

### 3. Configurar Entorno

```bash
# Copiar .env
cp .env.production .env
nano .env
# Llenar con los valores reales (ver sección Variables de Entorno)

# Generar keys
php artisan key:generate

# Cache config
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
```

### 4. Ejecutar Migraciones

```bash
# Migrar base de datos
php artisan migrate --force

# Seed inicial (superadmin + retention policies)
php artisan db:seed --class=SuperadminSeeder --force
php artisan db:seed --class=RetentionPolicySeeder --force
```

### 5. Generar Certificado de Firma (Temporal Self-Signed)

```bash
# Crear directorio certificates
mkdir -p storage/certificates

# Generar certificado self-signed (válido 10 años)
openssl req -x509 -newkey rsa:4096 -sha256 -days 3650 \
  -nodes -keyout storage/certificates/ancla-production.key \
  -out storage/certificates/ancla-production.crt \
  -subj "/C=ES/ST=Madrid/L=Madrid/O=Firmalum Production/CN=firmalum.com" \
  -addext "keyUsage=digitalSignature" \
  -addext "extendedKeyUsage=emailProtection"

# Permisos
chmod 600 storage/certificates/ancla-production.key
chmod 644 storage/certificates/ancla-production.crt

# NOTA: Para producción real, reemplazar con certificado CA-issued
# (DigiCert, GlobalSign, etc.)
```

### 6. Configurar Queue Workers (Supervisor)

```bash
# Crear configuración supervisor
sudo nano /etc/supervisor/conf.d/ancla-worker.conf
```

```ini
[program:ancla-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/firmalum/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600 --timeout=300
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=deploy
numprocs=4
redirect_stderr=true
stdout_logfile=/var/www/firmalum/storage/logs/worker.log
stopwaitsecs=3600
```

```bash
# Recargar supervisor
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start ancla-worker:*

# Verificar status
sudo supervisorctl status
```

### 7. Configurar Laravel Scheduler (Cron)

```bash
# Editar crontab de usuario deploy
crontab -e

# Añadir línea:
* * * * * cd /var/www/firmalum && php artisan schedule:run >> /dev/null 2>&1
```

### 8. Optimizar para Producción

```bash
cd /var/www/firmalum

# Cache everything
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# Optimizar autoloader
composer dump-autoload -o

# Optimizar opcache
sudo systemctl restart php8.2-fpm

# Restart queue workers
sudo supervisorctl restart ancla-worker:*
```

### 9. Configurar SSL Wildcard

```bash
# Obtener certificado Let's Encrypt wildcard
sudo certbot certonly --manual --preferred-challenges dns \
  -d firmalum.com -d *.firmalum.com

# Seguir instrucciones para añadir TXT record _acme-challenge

# Actualizar nginx config con paths de certificados
sudo nano /etc/nginx/sites-available/firmalum
# (Ver sección Configurar Nginx)

# Reload nginx
sudo nginx -t
sudo systemctl reload nginx
```

### 10. Deploy Script (Automatización)

```bash
# Crear script de deploy
nano /var/www/firmalum/deploy.sh
```

```bash
#!/bin/bash
# deploy.sh - Firmalum Production Deployment Script

set -e

echo "🚀 Starting Firmalum deployment..."

# Pull latest code
echo "📥 Pulling latest code from Git..."
git pull origin main

# Install/Update dependencies
echo "📦 Installing Composer dependencies..."
composer install --optimize-autoloader --no-dev

echo "📦 Installing NPM dependencies..."
npm install
npm run build

# Clear and rebuild caches
echo "🔄 Clearing caches..."
php artisan down
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Run migrations
echo "🗄️ Running database migrations..."
php artisan migrate --force

# Rebuild caches
echo "⚡ Building production caches..."
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# Restart services
echo "🔄 Restarting services..."
sudo systemctl reload php8.2-fpm
sudo systemctl reload nginx
sudo supervisorctl restart ancla-worker:*

php artisan up

echo "✅ Deployment completed successfully!"
```

```bash
# Dar permisos de ejecución
chmod +x /var/www/firmalum/deploy.sh

# Uso futuro:
# ./deploy.sh
```

---

## ✅ Post-Deployment

### 1. Verificación de Servicios

```bash
# Verificar Nginx
sudo systemctl status nginx
curl -I https://firmalum.com

# Verificar PHP-FPM
sudo systemctl status php8.2-fpm

# Verificar Redis
redis-cli ping
# Debe responder: PONG

# Verificar Queue Workers
sudo supervisorctl status
# Debe mostrar: ancla-worker:ancla-worker_00 RUNNING

# Verificar Cron
crontab -l | grep artisan
# Debe mostrar la línea del scheduler

# Verificar Database Connection
php artisan tinker
>>> DB::connection()->getPdo();
# No debe dar error

# Verificar Storage (Spaces/S3)
php artisan tinker
>>> Storage::disk('s3')->put('test.txt', 'Hello');
>>> Storage::disk('s3')->get('test.txt');
# Debe responder: "Hello"
>>> Storage::disk('s3')->delete('test.txt');
```

### 2. Crear Superadmin Inicial

```bash
cd /var/www/firmalum
php artisan tinker
```

```php
// En tinker
use App\Models\User;
use App\Enums\UserRole;

$admin = User::create([
    'name' => 'Super Admin',
    'email' => 'admin@firmalum.com',
    'password' => bcrypt('ChangeThisPassword123!'),
    'role' => UserRole::SUPER_ADMIN,
    'status' => 'active',
    'email_verified_at' => now(),
]);

echo "Superadmin created: " . $admin->email;
exit
```

```bash
# Login en https://firmalum.com/login
# Email: admin@firmalum.com
# Password: ChangeThisPassword123!

# CAMBIAR PASSWORD INMEDIATAMENTE desde el panel
```

### 3. Crear Tenant de Prueba

```bash
# Acceder como superadmin a:
# https://firmalum.com/admin/tenants

# Crear tenant:
# - Name: Empresa de Prueba
# - Subdomain: demo (accesible en https://demo.firmalum.com)
# - Admin email: demo@firmalum.com
# - Plan: trial (30 días)
# - Max users: 5
# - Max documents/month: 100

# El sistema auto-generará:
# - Retention policy por defecto
# - Usuario admin inicial para el tenant
# - Email de bienvenida con instrucciones
```

### 4. Tests de Integración

```bash
# Test upload documento
# 1. Login como admin del tenant en https://demo.firmalum.com
# 2. Navegar a "Upload Document"
# 3. Subir PDF de prueba
# 4. Verificar en Spaces que se guardó en:
#    documents/{tenant_id}/{year}/{month}/{uuid}.pdf

# Test proceso de firma
# 1. Crear nuevo proceso de firma
# 2. Añadir 2 firmantes (tu email y otro)
# 3. Enviar notificaciones
# 4. Verificar emails recibidos
# 5. Abrir link de firma en modo incógnito
# 6. Completar flujo OTP + Firma
# 7. Verificar documento final generado
# 8. Verificar email de entrega automática

# Test verificación pública
# 1. Ir a https://firmalum.com/verify
# 2. Ingresar código de verificación del documento
# 3. Debe mostrar información completa y QR code

# Test encriptación
php artisan tinker
>>> use App\Services\Document\DocumentEncryptionService;
>>> $service = app(DocumentEncryptionService::class);
>>> $encrypted = $service->encrypt('test data', 1);
>>> $decrypted = $service->decrypt($encrypted, 1);
>>> echo $decrypted; // Debe mostrar: "test data"
```

### 5. Configurar Monitoring

```bash
# Digital Ocean Built-in Monitoring (gratis)
# Ya habilitado con --enable-monitoring al crear droplet
# Ver en: DO Console → Droplets → ancla-production → Graphs

# Configurar alertas en DO:
# - CPU > 80% por 5 minutos
# - Memory > 90% por 5 minutos
# - Disk > 85%
# - Load Average > 4

# Opcional: Sentry para error tracking
# Ya configurado con SENTRY_LARAVEL_DSN en .env
# Verificar en: https://sentry.io/organizations/{org}/projects/{project}/
```

### 6. Documentar Credenciales

Crear archivo seguro con todas las credenciales (NUNCA en repo):

```bash
# En máquina local (NO en servidor)
nano ~/ancla-production-credentials.txt
```

```
Firmalum PRODUCTION CREDENTIALS - CONFIDENTIAL
============================================

SERVER:
- Droplet IP: XXX.XXX.XXX.XXX
- SSH User: deploy
- SSH Key: ~/.ssh/id_rsa_ancla

SUPERADMIN:
- URL: https://firmalum.com/login
- Email: admin@firmalum.com
- Password: [cambiar inmediatamente]

DATABASE:
- Host: mysql-cluster-firmalum-XXXXX.db.ondigitalocean.com
- Port: 25060
- Database: ancla_production
- User: ancla_user
- Password: [password]

SPACES:
- Bucket: ancla-production
- Region: ams3
- Access Key ID: DO00XXXXXXXXXXXX
- Secret: [secret]
- Endpoint: https://ams3.digitaloceanspaces.com

AMAZON SES:
- Region: eu-west-1
- Access Key ID: AKIAXXXXXXXXXXXX
- Secret: [secret]
- From: noreply@firmalum.com

SSL:
- Provider: Let's Encrypt
- Cert Path: /etc/letsencrypt/live/firmalum.com/
- Renewal: Auto (cron 3 AM daily)

SIGNING CERT:
- Type: Self-signed (temporal)
- Path: storage/certificates/ancla-production.crt
- Valid Until: [date]
- TODO: Replace with CA-issued cert

ENCRYPTION:
- Master Key: [en .env - APP_ENCRYPTION_KEY]
- Version: v1
- Algorithm: AES-256-GCM

BACKUPS:
- Schedule: Daily 2 AM
- Retention: 30 days
- Location: Spaces /backups/

MONITORING:
- Digital Ocean: Enabled
- Sentry DSN: [dsn]
- Logs: /var/www/firmalum/storage/logs/
```

```bash
# Cifrar archivo
gpg -c ~/ancla-production-credentials.txt
# Ingresar passphrase fuerte
# Se genera: ancla-production-credentials.txt.gpg

# Eliminar original
rm ~/ancla-production-credentials.txt

# Guardar .gpg en lugar seguro (password manager, bóveda, etc.)
```

---

## 📊 Monitoreo y Mantenimiento

### Logs a Monitorear

```bash
# Laravel Application Logs
tail -f /var/www/firmalum/storage/logs/laravel.log

# Nginx Access Logs
tail -f /var/log/nginx/ancla_access.log

# Nginx Error Logs
tail -f /var/log/nginx/ancla_error.log

# PHP-FPM Errors
tail -f /var/log/php8.2-fpm.log

# Queue Worker Logs
tail -f /var/www/firmalum/storage/logs/worker.log

# System Logs
tail -f /var/log/syslog

# MySQL Slow Queries (si Managed DB)
# Desde DO Console → Databases → Insights
```

### Comandos de Mantenimiento

```bash
# Limpiar logs antiguos (ejecutar mensualmente)
cd /var/www/firmalum
find storage/logs -name "*.log" -mtime +30 -delete

# Limpiar archivos temp antiguos
php artisan temp-files:cleanup --age=7

# Verificar integridad de documentos
php artisan documents:verify-integrity

# Stats de uso
php artisan tinker
>>> DB::table('tenants')->count();
>>> DB::table('users')->count();
>>> DB::table('signing_processes')->count();
>>> DB::table('documents')->sum('file_size') / 1024 / 1024; // MB total

# Re-sellar documentos próximos a expirar
php artisan evidence:reseal --dry-run
php artisan evidence:reseal

# Migrar a tier archive (documentos >1 año)
php artisan evidence:tier-migrate --dry-run
php artisan evidence:tier-migrate

# Cleanup evidencias expiradas
php artisan evidence:cleanup-expired --dry-run
php artisan evidence:cleanup-expired
```

### Backups

```bash
# Backup manual de base de datos
mysqldump -h mysql-cluster-firmalum-XXXXX.db.ondigitalocean.com \
  -P 25060 -u ancla_user -p ancla_production \
  > backup_$(date +%Y%m%d).sql

# Comprimir y encriptar
gzip backup_$(date +%Y%m%d).sql
openssl enc -aes-256-cbc -salt -in backup_$(date +%Y%m%d).sql.gz \
  -out backup_$(date +%Y%m%d).sql.gz.enc -k YOUR_ENCRYPTION_PASSWORD

# Subir a Spaces
aws s3 cp backup_$(date +%Y%m%d).sql.gz.enc \
  s3://ancla-production/backups/ \
  --endpoint-url=https://ams3.digitaloceanspaces.com

# Backup automático (ya configurado en .env y schedule)
# Se ejecuta diario a las 2 AM vía Laravel Scheduler
php artisan documents:backup

# Verificar backups
aws s3 ls s3://ancla-production/backups/ \
  --endpoint-url=https://ams3.digitaloceanspaces.com
```

### Digital Ocean Snapshots

```bash
# Crear snapshot manual del Droplet
doctl compute droplet-action snapshot DROPLET_ID --snapshot-name firmalum-$(date +%Y%m%d)

# Listar snapshots
doctl compute snapshot list

# Programar snapshots automáticos
# Digital Ocean Console → Droplets → ancla-production → Backups
# Enable weekly automated backups
```

### Health Checks

```bash
# Crear script de health check
nano /var/www/firmalum/healthcheck.sh
```

```bash
#!/bin/bash
# healthcheck.sh - Firmalum System Health Check

echo "=== Firmalum HEALTH CHECK ==="
echo "Date: $(date)"
echo ""

# Web Server
echo "1. Nginx Status:"
systemctl is-active nginx
curl -s -o /dev/null -w "%{http_code}" https://firmalum.com

# PHP-FPM
echo "2. PHP-FPM Status:"
systemctl is-active php8.2-fpm

# Redis
echo "3. Redis Status:"
redis-cli ping

# Database
echo "4. Database Connection:"
php /var/www/firmalum/artisan tinker --execute="DB::connection()->getPdo(); echo 'OK';"

# Queue Workers
echo "5. Queue Workers:"
sudo supervisorctl status | grep ancla-worker

# Disk Space
echo "6. Disk Usage:"
df -h / | tail -1 | awk '{print $5 " used"}'

# Memory
echo "7. Memory Usage:"
free -h | grep Mem | awk '{print $3 "/" $2 " used"}'

# Spaces Connection
echo "8. Spaces Storage:"
php /var/www/firmalum/artisan tinker --execute="Storage::disk('s3')->exists('test.txt') ? 'OK' : 'FAIL';"

echo ""
echo "=== END HEALTH CHECK ==="
```

```bash
chmod +x /var/www/firmalum/healthcheck.sh

# Ejecutar manualmente
./healthcheck.sh

# Programar ejecución diaria y enviar por email
crontab -e
# Añadir:
0 8 * * * /var/www/firmalum/healthcheck.sh | mail -s "Firmalum Health Check" admin@firmalum.com
```

---

## 🔧 Troubleshooting

### Problema: 502 Bad Gateway

```bash
# Verificar PHP-FPM
sudo systemctl status php8.2-fpm
sudo tail -f /var/log/php8.2-fpm.log

# Verificar socket
ls -la /var/run/php/php8.2-fpm.sock

# Restart
sudo systemctl restart php8.2-fpm
sudo systemctl reload nginx
```

### Problema: Queue Jobs No Se Procesan

```bash
# Verificar workers
sudo supervisorctl status

# Ver logs
tail -f /var/www/firmalum/storage/logs/worker.log

# Restart workers
sudo supervisorctl restart ancla-worker:*

# Verificar Redis
redis-cli
> KEYS queue:*
> LLEN queue:default
```

### Problema: Emails No Se Envían

```bash
# Verificar configuración SES
php artisan tinker
>>> Mail::raw('Test', function($msg) { $msg->to('test@example.com')->subject('Test'); });
>>> exit

# Verificar logs
tail -f /var/www/firmalum/storage/logs/laravel.log | grep -i mail

# Verificar cola
php artisan queue:work --once

# Verificar SES dashboard en AWS Console
# Sending Statistics y Reputation dashboard
```

### Problema: Errores de Permisos

```bash
# Reset permisos Laravel
cd /var/www/firmalum
sudo chown -R deploy:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
sudo chmod -R 755 public

# Verificar SELinux (si aplica)
getenforce
# Si está en Enforcing, configurar contextos:
sudo semanage fcontext -a -t httpd_sys_rw_content_t "/var/www/firmalum/storage(/.*)?"
sudo restorecon -R /var/www/firmalum/storage
```

### Problema: SSL Certificate Errors

```bash
# Verificar certificados
sudo certbot certificates

# Renovar manualmente
sudo certbot renew --dry-run
sudo certbot renew --force-renewal

# Verificar nginx config
sudo nginx -t

# Verificar DNS
dig firmalum.com
dig demo.firmalum.com

# Test SSL
curl -I https://firmalum.com
openssl s_client -connect firmalum.com:443 -servername firmalum.com
```

### Problema: Out of Memory

```bash
# Verificar uso
free -h
htop

# Verificar procesos PHP
ps aux | grep php

# Aumentar límites PHP
sudo nano /etc/php/8.2/fpm/php.ini
# memory_limit = 512M (aumentar si necesario)

# Restart
sudo systemctl restart php8.2-fpm

# Añadir swap (si necesario)
sudo fallocate -l 2G /swapfile
sudo chmod 600 /swapfile
sudo mkswap /swapfile
sudo swapon /swapfile
echo '/swapfile none swap sw 0 0' | sudo tee -a /etc/fstab
```

### Problema: Database Connection Refused

```bash
# Verificar Managed DB status en DO Console

# Test connection
mysql -h mysql-cluster-firmalum-XXXXX.db.ondigitalocean.com \
  -P 25060 -u ancla_user -p

# Verificar firewall en DO
# Database → Settings → Trusted Sources
# Añadir IP del Droplet si falta

# Verificar SSL
mysql -h ... --ssl-mode=REQUIRED

# Verificar .env
cat /var/www/firmalum/.env | grep DB_
```

---

## 📞 Soporte y Contacto

### Escalation Path

```
Nivel 1: Auto-diagnóstico (esta guía + logs)
    ↓
Nivel 2: Health Check Script + Restart Services
    ↓
Nivel 3: Contactar DevOps Team
    ↓
Nivel 4: Contactar Vendor Support (DO, AWS)
```

### Información para Support Tickets

Siempre incluir:

```bash
# System Info
uname -a
lsb_release -a
df -h
free -h

# Laravel Info
php artisan --version
php -v
composer --version

# Services Status
systemctl status nginx
systemctl status php8.2-fpm
systemctl status redis-server
sudo supervisorctl status

# Recent Logs
tail -100 /var/www/firmalum/storage/logs/laravel.log
tail -100 /var/log/nginx/ancla_error.log

# Environment (sanitized)
cat /var/www/firmalum/.env | grep -v PASSWORD | grep -v KEY | grep -v SECRET
```

---

## ✅ Post-Deployment Checklist

- [ ] Droplet creado y configurado
- [ ] Software stack instalado (Nginx, PHP, Redis, Composer, Node)
- [ ] Managed Database configurado y conectado
- [ ] Spaces configurado y testado
- [ ] Email (SES) configurado y testado
- [ ] SSL wildcard configurado (Let's Encrypt)
- [ ] Repositorio clonado y dependencias instaladas
- [ ] Variables de entorno configuradas (.env)
- [ ] Migraciones ejecutadas
- [ ] Superadmin creado
- [ ] Certificado de firma generado (self-signed inicial)
- [ ] Queue workers configurados (supervisor)
- [ ] Laravel Scheduler configurado (cron)
- [ ] Nginx virtual host configurado
- [ ] Firewall configurado (UFW)
- [ ] Fail2ban instalado
- [ ] Backups automáticos configurados
- [ ] Monitoring habilitado
- [ ] Health check script creado
- [ ] Tenant de prueba creado
- [ ] Flujo end-to-end testado
- [ ] DNS wildcard configurado (*.firmalum.com)
- [ ] Documentación de credenciales encriptada y guardada
- [ ] Deploy script creado
- [ ] Team notificado del deployment

---

## 📚 Recursos Adicionales

### Documentación del Proyecto

- [`README.md`](../../README.md) - Introducción general
- [`docs/kanban.md`](../kanban.md) - Estado del proyecto (28/28 historias)
- [`docs/deployment/environment-variables.md`](environment-variables.md) - Variables detalladas
- [`docs/deployment/encryption-setup-guide.md`](encryption-setup-guide.md) - Guía de encriptación
- [`docs/admin/superadmin-guide.md`](../admin/superadmin-guide.md) - Manual superadmin
- [`docs/admin/user-management-guide.md`](../admin/user-management-guide.md) - Gestión usuarios

### Digital Ocean

- [Droplet Quickstart](https://docs.digitalocean.com/products/droplets/quickstart/)
- [Managed Databases](https://docs.digitalocean.com/products/databases/)
- [Spaces (S3-compatible)](https://docs.digitalocean.com/products/spaces/)
- [Load Balancers](https://docs.digitalocean.com/products/networking/load-balancers/)

### Laravel

- [Deployment Guide](https://laravel.com/docs/10.x/deployment)
- [Queues](https://laravel.com/docs/10.x/queues)
- [Task Scheduling](https://laravel.com/docs/10.x/scheduling)

### Security

- [SSL Labs Test](https://www.ssllabs.com/ssltest/)
- [Let's Encrypt Docs](https://letsencrypt.org/docs/)
- [OWASP Top 10](https://owasp.org/www-project-top-ten/)

---

**Versión**: 1.0.0  
**Última actualización**: 2026-01-01  
**Mantenedor**: DevOps Team  
**Estado**: Production Ready ✅
