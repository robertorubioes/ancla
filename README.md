# Firmalum

Plataforma SaaS de **firma electronica avanzada** conforme a eIDAS,
multi-tenant y de marca blanca. Su diferencial es la generacion, conservacion
y exportacion de **evidencias legales** defendibles ante una auditoria o un
procedimiento judicial.

- Firma **PAdES-B-LT** con sellado de tiempo cualificado (TSA).
- **Verificacion publica** sin registro, por web y por API.
- **Dossier probatorio** exportable en PDF.
- **Multi-tenant** con personalizacion por cliente.

Vision completa: [`docs/SYSTEM_OVERVIEW.md`](docs/SYSTEM_OVERVIEW.md).
Indice de documentacion: [`docs/INDEX.md`](docs/INDEX.md).

## Requisitos

PHP 8.2+ · Composer 2 · Node 20+ · MySQL 8 · Redis

## Puesta en marcha

```bash
git clone git@github.com:robertorubioes/ancla.git firmalum
cd firmalum

composer setup                      # deps, .env, key, migraciones, assets
scripts/install-hooks.sh            # pre-commit (Pint + PHPStan) y commit-msg
scripts/generate-dev-certificate.sh # certificado de firma de desarrollo

composer dev                        # servidor, cola, logs y vite
```

Luego edita el `.env`:

```bash
APP_BASE_DOMAIN=firmalum.local      # dominio base de los subdominios de tenant
SUPERADMIN_EMAIL=tu@correo.com      # el seeder aborta si falta
SUPERADMIN_PASSWORD=                # vacio en local -> genera una aleatoria
TSA_MOCK_ENABLED=true               # sin esto se llama a la TSA real
```

```bash
php artisan db:seed --class=SuperadminSeeder
```

## Calidad

Las tres puertas corren en CI en **modo verificacion**: el CI comprueba, no
reescribe. Reformatear es trabajo del hook local.

```bash
composer pint          # formatea
composer pint:test     # verifica formato (lo que corre el CI)
composer stan          # PHPStan nivel 6 sobre baseline
composer test          # PHPUnit (usa --parallel para medir de verdad)
composer quality       # las tres seguidas
```

`phpstan-baseline.neon` congela la deuda existente: PHPStan solo bloquea
errores **nuevos**. Para reducirla, arregla y regenera con
`composer stan:baseline`. Nunca regeneres el baseline para tapar un error
nuevo.

> **La suite de tests esta actualmente en rojo**: 71 de 657. Ejecutala
> siempre con `--parallel`; en serie una fuga de transaccion contamina el
> resto y la salida es ilegible. El detalle, las causas y el orden en que se
> paga estan en
> [`docs/REFACTORING_AND_TESTING.md`](docs/REFACTORING_AND_TESTING.md).

## Flujo de trabajo

- `main` esta protegido: todo entra por PR con CI verde. Nunca push directo.
- Ramas `feat/…`, `fix/…`, `refactor/…`, `docs/…`.
- Commits en espanol con prefijo convencional (`feat:`, `fix:`, `refactor:`,
  `docs:`, `test:`, `chore:`). El hook `commit-msg` lo verifica.
- Un cambio funcional se documenta en `docs/` **en el mismo commit**.

## Operacion

| Script | Que hace |
|---|---|
| `scripts/install-hooks.sh` | Instala los hooks locales de git. |
| `scripts/generate-dev-certificate.sh` | Genera el certificado de firma de desarrollo. |
| `scripts/backup.sh` | Backup verificado de la BD + `.env` cifrado + manifiesto. |
| `scripts/backup-verify.sh` | Verifica un backup. Un backup no verificado no existe. |
| `scripts/restore.sh` | Restaura un backup, verificandolo antes. |
| `bin/deploy-production.sh` | Despliegue en produccion (anterior al estandar de la casa). |

Backups: [`docs/BACKUPS.md`](docs/BACKUPS.md).
Despliegue: [`docs/deployment/`](docs/deployment/).

## Seguridad

- Los secretos viven solo en `.env`. `.env.example` se mantiene al dia.
- El certificado de firma **no** esta en el repositorio: cada entorno genera
  o instala el suyo (`SIGNATURE_CERT_PATH` / `SIGNATURE_KEY_PATH`).
- Las credenciales del superadmin se leen del entorno; en produccion y
  staging el seeder aborta si `SUPERADMIN_PASSWORD` no esta definido.
