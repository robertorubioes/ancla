# SSL Wildcard para Tenants Automáticos

## Resumen

Con SSL Wildcard + DNS Wildcard, los nuevos tenants funcionan **automáticamente** sin configuración adicional.

**Flujo:**
1. Crear tenant en admin con subdomain "acme"
2. El usuario accede a `https://acme.app.firmalum.com`
3. ✅ Funciona automáticamente

## Estado Actual

| Componente | Estado |
|------------|--------|
| DNS Wildcard (`*.app.firmalum.com`) | ✅ Configurado |
| SSL Wildcard | ⚠️ Pendiente |
| Nginx Wildcard | ⚠️ Pendiente |
| Renovación automática | ✅ Cron configurado |

## Procedimiento de Configuración (Una sola vez)

### Paso 1: Conectarse al servidor

```bash
ssh -i ~/.ssh/firmalum root@159.65.201.32
```

### Paso 2: Ejecutar el script de configuración

```bash
/root/setup-wildcard-ssl.sh
```

### Paso 3: Durante la ejecución

El script te pedirá crear un registro TXT en el DNS de DigitalOcean:

1. Ir a: https://cloud.digitalocean.com/networking/domains/firmalum.com
2. Añadir registro:
   - **Tipo:** TXT
   - **Nombre:** `_acme-challenge.app`
   - **Valor:** (el código que muestra certbot)
   - **TTL:** 30
3. Esperar 1-2 minutos para propagación
4. Continuar con certbot (presionar Enter)

### Paso 4: Verificar

```bash
# Verificar certificado
openssl s_client -connect test.app.firmalum.com:443 2>/dev/null | head -20

# Verificar fechas
certbot certificates
```

## Renovación Automática

Ya configurada vía cron:
```
0 3 * * * /usr/bin/certbot renew --quiet --deploy-hook "systemctl reload nginx"
```

## Configuración de Nginx Final

Después de ejecutar el script, Nginx servirá:
- `app.firmalum.com` → Tenant principal (Firmalum Admin)
- `*.app.firmalum.com` → Cualquier subdomain de tenant

```nginx
server {
    listen 443 ssl;
    server_name app.firmalum.com *.app.firmalum.com;
    
    ssl_certificate /etc/letsencrypt/live/app.firmalum.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/app.firmalum.com/privkey.pem;
    
    # ... resto de la config
}
```

## Crear Nuevo Tenant

1. Ir a https://app.firmalum.com/admin/tenants
2. Click "Create Tenant"
3. Llenar formulario:
   - Name: "Acme Corp"
   - Subdomain: "acme" (se genera automáticamente desde el nombre)
   - Admin Email: admin@acme.com
4. El tenant estará disponible inmediatamente en `https://acme.app.firmalum.com`

## Troubleshooting

### El subdomain no resuelve
```bash
dig +short SUBDOMAIN.app.firmalum.com
# Debe devolver: 159.65.201.32
```

### Error SSL en nuevo subdomain
```bash
# Verificar que el certificado wildcard existe
certbot certificates | grep -A5 "app.firmalum.com"
```

### Tenant not found (404)
- Verificar que el tenant existe en la BD con ese subdomain
- El subdomain debe coincidir exactamente (case-sensitive)
