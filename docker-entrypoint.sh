#!/bin/sh
# docker-entrypoint.sh
#
# Runs every time the container starts. All steps are idempotent:
#   - composer install   → skips packages already in vendor/
#   - migrations         → skips migrations already applied
#   - firmware import    → skips records already in the database
#   - admin user         → creates or updates the admin account
#
# Environment variables (with defaults):
#   ADMIN_USERNAME   admin
#   ADMIN_PASSWORD   secret1234

set -e

echo "--- [0/5] Checking environment file ---"
# Symfony's post-install scripts (cache:clear) require .env to be present.
# When running via Docker, .env is typically absent from a fresh clone because
# it is listed in .gitignore. Copy .env.local as the baseline so Composer
# scripts have a valid Symfony environment to bootstrap against.
# Guard: skip if .env already exists (e.g. manual local setup or CI injection).
if [ ! -f .env ]; then
    echo "       .env not found — copying from .env.local"
    cp .env.local .env
else
    echo "       .env already present — skipping copy"
fi

echo "--- [1/5] Installing Composer dependencies ---"
composer install --no-interaction --prefer-dist --optimize-autoloader

echo "--- [2/5] Creating database directory and setting permissions ---"
mkdir -p var/data var/cache var/log
chmod -R 777 var/

echo "--- [3/5] Running database migrations ---"
php bin/console doctrine:migrations:migrate --no-interaction

echo "--- [4/5] Importing firmware seed data ---"
# --execute without --force: inserts new records, skips existing ones.
# Safe to run on every start — already-imported records are never duplicated.
php bin/console app:import-firmware-data --execute

echo "--- [5/5] Creating admin user ---"
# Creates the user on first run; updates the password on subsequent runs.
php bin/console app:create-admin-user \
  --username="${ADMIN_USERNAME:-admin}" \
  --password="${ADMIN_PASSWORD:-secret1234}"

echo ""
echo "========================================="
echo "  App ready at http://localhost:8000"
echo "  Admin: http://localhost:8000/admin"
echo "  Username: ${ADMIN_USERNAME:-admin}"
echo "  Password: ${ADMIN_PASSWORD:-secret1234}"
echo "========================================="
echo ""

# Start PHP's built-in web server.
# -S 0.0.0.0:8000  → listen on all interfaces so the port is reachable from the host.
# -t public/       → document root is the Symfony public/ directory.
exec php -S 0.0.0.0:8000 -t public/
