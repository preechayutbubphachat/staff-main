#!/usr/bin/env bash
set -euo pipefail

BRANCH="codex/strict-monthly-matrix-mapping"

echo "Deploying ${BRANCH}"

git fetch origin
git checkout "${BRANCH}"
git pull origin "${BRANCH}"

if [ -f package-lock.json ]; then
  npm ci
elif [ -f pnpm-lock.yaml ]; then
  pnpm install --frozen-lockfile
elif [ -f yarn.lock ]; then
  yarn install --frozen-lockfile
else
  npm install
fi

if npm run | grep -q "build:tailwind"; then
  npm run build:tailwind
elif npm run | grep -q "build"; then
  npm run build
else
  echo "No frontend build script found; skipping frontend build."
fi

if [ -f artisan ]; then
  php artisan optimize:clear
  php artisan view:clear
  php artisan route:clear
  php artisan config:clear
fi

echo "Deploy completed"
