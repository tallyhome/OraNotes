#!/usr/bin/env bash
# Construit un zip distributable OraNotes (sans secrets, sans node_modules).
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"
VERSION="$(tr -d '[:space:]' < VERSION)"
STAGING="$(mktemp -d)"
NAME="oranotes-${VERSION}"
DEST="${ROOT}/dist/${NAME}"

echo "==> Composer --no-dev"
composer install --no-dev --optimize-autoloader --no-interaction

echo "==> Assets frontend (Node requis uniquement ici)"
npm ci
npm run build

echo "==> Staging ${DEST}"
rm -rf "${ROOT}/dist"
mkdir -p "${DEST}"

rsync -a \
  --exclude '.git' \
  --exclude '.env' \
  --exclude '.env.*' \
  --exclude 'node_modules' \
  --exclude 'dist' \
  --exclude 'storage/logs/*' \
  --exclude 'storage/framework/cache/data/*' \
  --exclude 'storage/framework/sessions/*' \
  --exclude 'storage/framework/views/*' \
  --exclude 'storage/app/private/*' \
  --exclude 'database/*.sqlite' \
  --exclude '.phpunit.result.cache' \
  --exclude 'tests/e2e' \
  "${ROOT}/" "${DEST}/"

# Garder les dossiers storage vides
mkdir -p "${DEST}/storage/logs" "${DEST}/storage/framework/cache/data" \
  "${DEST}/storage/framework/sessions" "${DEST}/storage/framework/views" \
  "${DEST}/storage/app/public"
touch "${DEST}/storage/logs/.gitkeep"

# Le zip DOIT contenir public/build (Node inutile ensuite)
if [[ ! -f "${DEST}/public/build/manifest.json" ]]; then
  echo "ERREUR: public/build manquant — le paquet ne peut pas tourner sans Node." >&2
  exit 1
fi

mkdir -p "${ROOT}/dist"
(cd "${ROOT}/dist" && zip -qr "${NAME}.zip" "${NAME}")
echo "OK ${ROOT}/dist/${NAME}.zip"
echo "Le destinataire: extraire, copier .env.example → .env ou /install, document root = public/"
