#!/bin/sh
set -e

# Ensure storage and cache directories exist and have proper permissions
mkdir -p /app/storage/framework/{cache,sessions,testing,views}
mkdir -p /app/storage/logs
chown -R www-data:www-data /app/storage /app/bootstrap/cache /app/database

# Run migrations if the database exists
if [ -f "/app/database/database.sqlite" ]; then
    echo "Running migrations..."
    php artisan migrate --force
fi

# Execute the main container process
exec "$@"
