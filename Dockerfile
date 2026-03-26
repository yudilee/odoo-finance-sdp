# Build Stage 1: PHP Dependencies
FROM composer:2.7 AS php-deps
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-interaction --prefer-dist

# Build Stage 2: Node Dependencies & Assets
FROM node:20-alpine AS node-deps
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY . .
RUN npm run build

# Stage 3: Final Production Image
FROM dunglas/frankenphp:1-php8.3-alpine AS final

# Set PHP Production settings
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && sed -i 's/memory_limit = 128M/memory_limit = 512M/g' "$PHP_INI_DIR/php.ini" \
    && sed -i 's/max_execution_time = 30/max_execution_time = 300/g' "$PHP_INI_DIR/php.ini"

# Install PHP extensions required by Laravel & Odoo interaction
RUN install-php-extensions \
    bcmath \
    gd \
    intl \
    zip \
    opcache \
    pcntl \
    pdo_sqlite

# Set working directory
WORKDIR /app

# Set environment variables
ENV APP_ENV=production
ENV APP_DEBUG=false
ENV APP_URL=http://localhost

# Copy PHP dependencies from Stage 1
COPY --from=php-deps /app/vendor ./vendor

# Copy Built Assets from Stage 2
COPY --from=node-deps /app/public/build ./public/build

# Copy the rest of the application
COPY . .

# Create necessary directories and set permissions
RUN mkdir -p storage/framework/{cache,sessions,testing,views} \
    && mkdir -p storage/logs \
    && chown -R www-data:www-data /app/storage /app/bootstrap/cache \
    && chmod -R 775 /app/storage /app/bootstrap/cache

# Setup initial SQLite database if it doesn't exist (optional, as entrypoint handles it)
# We now use the storage directory which is already set up and persistent

# Copy and setup entrypoint
COPY docker/entrypoint.sh /usr/local/bin/docker-entrypoint
RUN chmod +x /usr/local/bin/docker-entrypoint

ENTRYPOINT ["docker-entrypoint"]

# Expose ports
EXPOSE 80 443 2019

# The default FrankenPHP entrypoint will handle running the server
CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile"]
