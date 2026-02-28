#!/bin/sh
set -e

echo "============================================"
echo "  Onesiforo - Docker Entrypoint"
echo "============================================"

# -----------------------------------------------
# 0. Load .env into shell (for entrypoint use only)
# -----------------------------------------------
# Docker's env_file is intentionally NOT used on the app container
# so that PHPUnit/Pest tests can override env vars via phpunit.xml.
# We source the values we need here for the entrypoint logic.
if [ -f /app/.env ]; then
    export $(grep -v '^#' /app/.env | grep -v '^\s*$' | sed 's/=\${.*}//;s/"//g' | grep -E '^(DB_CONNECTION|DB_HOST|DB_PORT|DB_USERNAME|DB_PASSWORD|APP_KEY)=' | xargs)
fi

# -----------------------------------------------
# 1. Wait for MariaDB to be healthy
# -----------------------------------------------
if [ "$DB_CONNECTION" = "mariadb" ] || [ "$DB_CONNECTION" = "mysql" ]; then
    echo "Waiting for MariaDB..."
    max_attempts=30
    attempt=0
    until php -r "
        try {
            new PDO(
                'mysql:host=${DB_HOST};port=${DB_PORT:-3306}',
                '${DB_USERNAME}',
                '${DB_PASSWORD}'
            );
            echo 'connected';
        } catch (Exception \$e) {
            exit(1);
        }
    " 2>/dev/null; do
        attempt=$((attempt + 1))
        if [ "$attempt" -ge "$max_attempts" ]; then
            echo "ERROR: MariaDB not available after ${max_attempts} attempts"
            exit 1
        fi
        echo "  Attempt $attempt/$max_attempts..."
        sleep 2
    done
    echo "MariaDB is ready."
fi

# -----------------------------------------------
# 3. Install Composer dependencies if needed
# -----------------------------------------------
if [ ! -d /app/vendor/laravel ]; then
    echo "Installing Composer dependencies..."
    composer install --no-interaction --prefer-dist --optimize-autoloader
fi

# -----------------------------------------------
# 4. Generate app key if missing
# -----------------------------------------------
CURRENT_KEY=$(grep '^APP_KEY=' /app/.env | cut -d'=' -f2-)
if [ -z "$CURRENT_KEY" ]; then
    echo "Generating application key..."
    php artisan key:generate --no-interaction --force
fi

# -----------------------------------------------
# 5. Run migrations
# -----------------------------------------------
echo "Running migrations..."
php artisan migrate --no-interaction --force

# -----------------------------------------------
# 6. Clear stale caches (dev mode — no caching)
# -----------------------------------------------
echo "Clearing caches..."
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan icons:cache

# -----------------------------------------------
# 7. Fix storage permissions
# -----------------------------------------------
echo "Fixing storage permissions..."
find /app/storage /app/bootstrap/cache -type d -exec chmod 775 {} \;
find /app/storage /app/bootstrap/cache -type f -not -name '.gitignore' -exec chmod 664 {} \;
chown -R www-data:www-data /app/storage /app/bootstrap/cache

# -----------------------------------------------
# 8. Create storage link if missing
# -----------------------------------------------
if [ ! -L /app/public/storage ]; then
    php artisan storage:link --no-interaction
fi

echo "============================================"
echo "  Onesiforo is ready!"
echo "============================================"

# -----------------------------------------------
# 9. Start FrankenPHP
# -----------------------------------------------
exec frankenphp run --config /etc/caddy/Caddyfile
