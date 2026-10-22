# Rotacion de credenciales

## Superadministrador

La contrasena se rota con un comando de artisan, no con `tinker`: asi queda
verificado por tests y no depende de un one-liner escrito a mano en
produccion.

```bash
# En el servidor
cd /var/www/ancla
php artisan superadmin:rotate-password
```

Genera una contrasena de 24 caracteres, la aplica y **la muestra una sola
vez**. Nunca se pasa por argumento, de modo que no queda en el historial del
shell.

| Opcion | Para que |
|---|---|
| `--email=` | Otra cuenta. Por defecto, `SUPERADMIN_EMAIL`. |
| `--ask` | Pedir la contrasena por prompt oculto en lugar de generarla. Minimo 12 caracteres, con confirmacion. |
| `--length=` | Longitud de la generada. Minimo 16. |
| `--force` | No pedir confirmacion. Para automatizaciones. |

El comando aborta si la cuenta no existe o si su rol no es `super_admin`.

> Las sesiones ya abiertas siguen siendo validas hasta que caduquen. Para
> cerrarlas todas, cambia `APP_KEY`... lo que invalida tambien todo lo cifrado
> con ella. En la practica, basta con esperar a que caduque la sesion.

### Cuando hay que rotarla

- Al recibir el proyecto (la contrasena inicial estuvo en el codigo, en un
  repositorio publico, y **sigue en el historico de git**).
- Ante cualquier sospecha de filtracion.
- Al dar de baja a alguien con acceso.

## Variables de S3

`config/filesystems.php` lee los nombres estandar de Laravel:

```bash
AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=
AWS_BUCKET=
AWS_ENDPOINT=                 # DigitalOcean Spaces y compatibles
```

**No** `AWS_REGION`, `AWS_S3_KEY` ni `AWS_S3_SECRET`: con esos nombres las
credenciales no se recogen y toda operacion con S3 falla, incluida la subida
y la lectura de documentos.

### Correo por SES

`config/services.php` acepta credenciales propias para SES, porque el disco
`s3` puede apuntar a otro proveedor:

```bash
AWS_SES_KEY=                  # si se dejan vacias, se usan las AWS_ de arriba
AWS_SES_SECRET=
AWS_SES_REGION=
```

Sin esto, configurar SES y Spaces a la vez es imposible: comparten las mismas
tres variables y la ultima definida gana.

## Niveles de archivo

Los discos `s3-glacier` y `s3-deep-archive` estan declarados en
`config/filesystems.php` y usan el bucket general salvo que se indique otro:

```bash
ARCHIVE_COLD_STORAGE_BUCKET=
ARCHIVE_COLD_STORAGE_REGION=
ARCHIVE_DEEP_STORAGE_BUCKET=
ARCHIVE_DEEP_STORAGE_REGION=
```

## Dominio base de los tenants

```bash
APP_BASE_DOMAIN=firmalum.com
```

Debe fijarse **explicitamente** en cada entorno. De el dependen la resolucion
de tenants por subdominio (`{slug}.{APP_BASE_DOMAIN}`) y las URL que se
generan en los correos.
