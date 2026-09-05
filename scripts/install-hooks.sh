#!/usr/bin/env bash
#
# Instala los hooks locales de git. Idempotente: sobrescribe siempre.
#
#   scripts/install-hooks.sh
#
set -euo pipefail

cd "$(dirname "$0")/.."
HOOKS=".git/hooks"

mkdir -p "$HOOKS"

#------------------------------------------------------------------------------
# pre-commit: formateador + analisis estatico sobre lo que se va a commitear
#------------------------------------------------------------------------------
cat > "$HOOKS/pre-commit" <<'HOOK'
#!/usr/bin/env bash
set -euo pipefail

ROOT="$(git rev-parse --show-toplevel)"
cd "$ROOT"

# Solo ficheros PHP en el indice (no los borrados)
FILES=$(git diff --cached --name-only --diff-filter=ACMR | grep '\.php$' || true)
[[ -z "$FILES" ]] && exit 0

echo "==> Pint"
# shellcheck disable=SC2086
if ! ./vendor/bin/pint --test $FILES; then
    echo
    echo "Pint ha encontrado problemas de formato."
    echo "Ejecuta 'composer pint' y vuelve a anadir los ficheros."
    exit 1
fi

echo "==> PHPStan"
if ! ./vendor/bin/phpstan analyse --memory-limit=1G --no-progress; then
    echo
    echo "PHPStan ha encontrado errores nuevos (fuera del baseline)."
    echo "Arreglalos; no regeneres el baseline para taparlos."
    exit 1
fi
HOOK

#------------------------------------------------------------------------------
# commit-msg: prefijo convencional y mensaje en espanol
#------------------------------------------------------------------------------
cat > "$HOOKS/commit-msg" <<'HOOK'
#!/usr/bin/env bash
set -euo pipefail

MSG_FILE="$1"
SUBJECT=$(head -1 "$MSG_FILE")

# Los merges y los reverts automaticos se dejan pasar
if [[ "$SUBJECT" =~ ^(Merge|Revert|fixup!|squash!) ]]; then
    exit 0
fi

PATTERN='^(feat|fix|refactor|docs|test|chore|style|perf|ci|build)(\([a-z0-9._-]+\))?!?: .+'

if [[ ! "$SUBJECT" =~ $PATTERN ]]; then
    cat >&2 <<'MSG'
Mensaje de commit invalido.

Formato: <tipo>[(ambito)]: <descripcion en espanol>

Tipos: feat, fix, refactor, docs, test, chore, style, perf, ci, build

Ejemplos:
  feat: anade la exportacion del dossier probatorio
  fix(firma): corrige el sellado TSA cuando falla el proveedor primario
MSG
    exit 1
fi

if [[ ${#SUBJECT} -gt 72 ]]; then
    echo "El asunto del commit supera los 72 caracteres (${#SUBJECT})." >&2
    exit 1
fi
HOOK

chmod +x "$HOOKS/pre-commit" "$HOOKS/commit-msg"

echo "Hooks instalados en $HOOKS:"
echo "  pre-commit  -> Pint + PHPStan"
echo "  commit-msg  -> prefijo convencional, asunto <= 72 caracteres"
