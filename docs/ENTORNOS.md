# Entornos

Tres entornos, siempre los mismos y siempre en el mismo orden. Nada llega a
produccion sin haber pasado por testing y haber sido validado alli.

```
local  ──(PR + CI verde)──>  testing  ──(validacion del usuario)──>  produccion
                                ↑
                    clon nocturno de produccion
```

## Dominios

| Entorno | `APP_BASE_DOMAIN` | Aplicacion | Un tenant |
|---|---|---|---|
| local | `firmalum.test` | `app.firmalum.test` | `acme.firmalum.test` |
| testing | `test.firmalum.com` | `app.test.firmalum.com` | `acme.test.firmalum.com` |
| produccion | `firmalum.com` | `app.firmalum.com` | `acme.firmalum.com` |

`app`, `www`, `api` y `admin` son subdominios **de la plataforma**: nunca se
buscan como tenant. En el host principal el tenant sale del usuario
autenticado, no del dominio. Cubierto por
`tests/Feature/Tenancy/HostResolutionTest.php`.

> El dominio local termina en `.test`, nunca en `.local`: ese TLD lo reserva
> mDNS/Bonjour y en macOS provoca resoluciones lentas y erraticas. `.test`
> esta reservado por la RFC 2606 justo para esto.

## Montar el entorno local

### 1. Resolucion DNS, una vez por maquina

```bash
~/.claude/scripts/setup-dominios-locales.sh
```

Instala dnsmasq y hace que **cualquier** dominio `.test` resuelva a
127.0.0.1, comodines incluidos. Sin esto haria falta una linea en
`/etc/hosts` por cada tenant, que para un multi-tenant es la herramienta
equivocada.

### 2. nginx

La configuracion vive en
`/opt/homebrew/etc/nginx/servers/firmalum.conf` y sirve
`firmalum.test` y `*.firmalum.test` desde `public/`.

```bash
sudo nginx -t          # comprobar
sudo brew services restart nginx
```

### 3. El `.env`

```bash
APP_URL=http://app.firmalum.test
APP_BASE_DOMAIN=firmalum.test
```

```bash
php artisan config:clear
```

### 4. Comprobar

```bash
curl -sI http://app.firmalum.test | head -1
```

## Testing

Testing es una **copia de produccion**, no un entorno inventado. Un testing
con datos inventados no prueba nada: no tiene el volumen, ni las formas
raras, ni los casos limite que solo aparecen con datos reales.

- **Mismo stack**: misma version de PHP, mismo MySQL, mismos servicios.
- **Nunca** apunta a servicios externos de produccion: ni a la TSA real, ni
  al bucket de produccion, ni a la pasarela de pago real.
  `TSA_MOCK_ENABLED=true`, bucket propio y `MAIL_MAILER=log`.

### El clon nocturno

```bash
scripts/clone-prod-to-test.sh [--dry-run] [--yes]
```

Corre **en el servidor de testing**, por cron de madrugada. La base de datos
de produccion es gestionada y solo escucha en la red privada, asi que no se
puede lanzar desde fuera.

```cron
0 3 * * * cd /var/www/firmalum-test && scripts/clone-prod-to-test.sh --yes >> storage/logs/clone.log 2>&1
```

Configuracion, en el `.env` de testing:

```bash
CLONE_SOURCE_ENV_FILE=/var/www/firmalum/.env.production
CLONE_PRESERVE_EMAILS=tu@correo.com    # los que NO se anonimizan
CLONE_TARGET_PATTERN=test              # la BD destino debe casar
```

### Que se anonimiza

| Tabla | Que se sustituye |
|---|---|
| `users` | correo y nombre. El hash de la contrasena se conserva, de modo que nadie entra sin conocer la real |
| `signers` | correo, nombre y telefono |
| `user_invitations` | correo y nombre |
| `signed_documents` | nombre firmado |
| `consent_records`, `device_fingerprints`, `ip_resolution_records`, `geolocation_records` | correo del firmante, IP, agente de usuario, coordenadas y direcciones |
| `audit_trail_entries`, `verification_logs` | IP y agente de usuario |
| `otp_codes`, `password_reset_tokens`, `sessions` | se vacian: son credenciales de un solo uso |

Las IP se sustituyen por `203.0.113.1`, del rango que la RFC 5737 reserva
para documentacion.

### Guardas

El script **escribe** en una base de datos, asi que aborta si:

1. el destino tiene `APP_ENV=production`,
2. el nombre de la BD destino no casa con `CLONE_TARGET_PATTERN`,
3. origen y destino son la misma base de datos,
4. falta `CLONE_SOURCE_ENV_FILE`,
5. **tras anonimizar queda algun correo sin sustituir**. Esta ultima es la
   red de seguridad: si alguien anade una tabla con datos personales y se
   olvida del script, el clon se detiene en lugar de exponerlos.

### La cadena de audit trail dara invalido, y es correcto

Reescribir `audit_trail_entries` rompe su encadenado por hash. En testing la
verificacion de cadena reportara invalido: **es la cadena haciendo su
trabajo**, porque los datos se han alterado de verdad. No es un bug.

Si hace falta probar la verificacion de cadena, hay que hacerlo sobre
procesos firmados en el propio testing despues del clon.

## Promocion

1. Desarrollo en local, con la suite en verde.
2. PR con CI verde.
3. Despliegue a testing, automatico al fusionar.
4. **Validacion del usuario en testing.** Es una puerta, no un tramite.
5. Despliegue a produccion con `scripts/deploy.sh` y smoke test, con plan de
   rollback.

Saltarse el paso 4 requiere permiso explicito para ese cambio concreto.
