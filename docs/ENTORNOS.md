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

Testing es una **copia de produccion**, no un entorno inventado:

- **Clon nocturno** de la base de datos de produccion, scriptado y en cron.
- **Anonimizado** al clonar: correos, telefonos y nombres de personas se
  sustituyen. Un entorno de pruebas no puede mandar correos de verdad a
  clientes de verdad.
- **Mismo stack**: misma version de PHP, mismo MySQL, mismos servicios.
- **Nunca** apunta a servicios externos de produccion: ni a la TSA real, ni
  al bucket de produccion, ni a la pasarela de pago real.
  `TSA_MOCK_ENABLED=true` y bucket propio.

## Promocion

1. Desarrollo en local, con la suite en verde.
2. PR con CI verde.
3. Despliegue a testing, automatico al fusionar.
4. **Validacion del usuario en testing.** Es una puerta, no un tramite.
5. Despliegue a produccion con `scripts/deploy.sh` y smoke test, con plan de
   rollback.

Saltarse el paso 4 requiere permiso explicito para ese cambio concreto.
