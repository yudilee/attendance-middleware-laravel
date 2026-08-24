FROM dunglas/frankenphp:1-php8.3-alpine

WORKDIR /app

# Install system utilities and nodejs
RUN apk add --no-cache curl git nodejs npm

# Install PHP extensions using FrankenPHP's built-in helper
RUN install-php-extensions pdo_pgsql pgsql gd zip pcntl redis

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copy application files
COPY . .

# Install PHP & Node dependencies and build assets
RUN composer install --no-dev --optimize-autoloader
RUN npm install && npm run build

# Clear build-time caches so runtime env vars work
RUN php artisan config:clear && \
    php artisan route:clear && \
    php artisan view:clear

EXPOSE 8999

ENV SERVER_NAME=":8999"
CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile"]
