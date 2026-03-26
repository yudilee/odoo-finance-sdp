#!/bin/sh
set -e

# Ensure storage and cache directories exist and have proper permissions
mkdir -p /app/storage/framework/{cache,sessions,testing,views}
mkdir -p /app/storage/logs
touch /app/storage/database.sqlite
chown -R www-data:www-data /app/storage /app/bootstrap/cache
chmod -R 775 /app/storage /app/bootstrap/cache

# Clear any cached config/routes/views that might have leaked from build or local
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Run migrations if the database exists
if [ -f "/app/storage/database.sqlite" ]; then
    echo "Running migrations..."
    php artisan migrate --force
fi

# Execute the main container process
exec "$@"
