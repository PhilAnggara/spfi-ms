#!/usr/bin/env bash
#
# Idempotent repository bootstrap for the SPFI-MS Cloud Agent environment.
# Runs after the repository is checked out. Safe to run repeatedly.
#
set -euo pipefail

cd "$(dirname "$0")/.."
ROOT="$(pwd)"

echo "==> Installing PHP dependencies"
composer install --no-interaction --prefer-dist --no-progress

echo "==> Installing JS dependencies and building assets"
npm install
npm run build

echo "==> Preparing .env"
if [ ! -f .env ]; then
    cp .env.example .env
fi

# The Cloud Agent has no MySQL/SQL Server available, so pin the app to a local
# SQLite database. DB_PROFILE must be blank so config/database.php falls back to
# DB_CONNECTION instead of selecting the mysql/sqlsrv profiles.
sed -i 's|^DB_PROFILE=.*|DB_PROFILE=|' .env
sed -i 's|^DB_CONNECTION=.*|DB_CONNECTION=sqlite|' .env
sed -i "s|^DB_DATABASE=.*|DB_DATABASE=${ROOT}/database/database.sqlite|" .env

echo "==> Ensuring application key"
if ! grep -q '^APP_KEY=base64:' .env; then
    php artisan key:generate --force
fi

echo "==> Ensuring SQLite database file"
touch database/database.sqlite

echo "==> Running migrations"
php artisan migrate --force

echo "==> Seeding database (only when empty)"
USER_COUNT="$(php artisan tinker --execute='echo \App\Models\User::count();' 2>/dev/null | tail -n1 | tr -dc '0-9')"
if [ -z "${USER_COUNT}" ] || [ "${USER_COUNT}" = "0" ]; then
    php artisan db:seed --force
else
    echo "    Database already seeded (${USER_COUNT} users); skipping."
fi

echo "==> Clearing cached config"
php artisan config:clear

echo "==> Install complete"
