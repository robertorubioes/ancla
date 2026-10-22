#!/usr/bin/env bash
#
# Genera el certificado autofirmado de desarrollo para la firma PAdES.
#
# Idempotente: si el certificado existe y sigue siendo valido, no hace nada.
# Usa --force para regenerarlo igualmente.
#
# El certificado NO vive en el repositorio (storage/certificates esta
# ignorado): cada entorno de desarrollo genera el suyo. En produccion se usa
# un certificado emitido por una CA, configurado via SIGNATURE_CERT_PATH.
#
set -euo pipefail

cd "$(dirname "$0")/.."

CERT_DIR="storage/certificates"
NAME="${CERT_NAME:-firmalum-dev}"
CRT="${CERT_DIR}/${NAME}.crt"
KEY="${CERT_DIR}/${NAME}.key"
DAYS="${CERT_DAYS:-825}"
SUBJECT="${CERT_SUBJECT:-/C=ES/ST=Madrid/L=Madrid/O=Firmalum Technologies/CN=Firmalum Dev Signature Service}"

FORCE=0
[[ "${1:-}" == "--force" ]] && FORCE=1

mkdir -p "$CERT_DIR"

if [[ $FORCE -eq 0 && -f "$CRT" && -f "$KEY" ]]; then
    if openssl x509 -in "$CRT" -noout -checkend 0 >/dev/null 2>&1; then
        echo "OK: ${CRT} ya existe y es valido hasta $(openssl x509 -in "$CRT" -noout -enddate | cut -d= -f2)"
        exit 0
    fi
    echo "AVISO: ${CRT} existe pero esta caducado, se regenera."
fi

echo "Generando certificado de desarrollo en ${CERT_DIR}/${NAME}.*"

openssl req -x509 -newkey rsa:4096 -sha256 -nodes \
    -keyout "$KEY" \
    -out "$CRT" \
    -days "$DAYS" \
    -subj "$SUBJECT" \
    -addext "keyUsage=critical,digitalSignature,nonRepudiation" \
    -addext "extendedKeyUsage=emailProtection" \
    >/dev/null 2>&1

chmod 600 "$KEY"
chmod 644 "$CRT"

echo "Hecho. Configura en tu .env:"
echo "  SIGNATURE_CERT_PATH=${CRT}"
echo "  SIGNATURE_KEY_PATH=${KEY}"
