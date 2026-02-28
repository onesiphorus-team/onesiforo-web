# =============================================================================
# FrankenPHP Application Server
# =============================================================================
# PHP 8.4 with FrankenPHP (Caddy-based) on Alpine Linux
# Used for the main web server container
# =============================================================================

FROM composer:2 AS composer

FROM dunglas/frankenphp:1-php8.4-alpine

LABEL maintainer="Dev2Geek Team"

# Install system dependencies
RUN apk add --no-cache \
    git \
    curl \
    unzip \
    icu-dev \
    libzip-dev \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    oniguruma-dev \
    sqlite-dev \
    postgresql-dev \
    linux-headers \
    $PHPIZE_DEPS

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        pdo_sqlite \
        pdo_pgsql \
        gd \
        intl \
        zip \
        bcmath \
        opcache \
        pcntl \
        sockets \
        exif \
        mbstring

# Install Redis extension via PECL
RUN pecl install redis \
    && docker-php-ext-enable redis

# Cleanup build dependencies
RUN apk del $PHPIZE_DEPS linux-headers \
    && rm -rf /tmp/* /var/cache/apk/*

# Copy Composer from multi-stage
COPY --from=composer /usr/bin/composer /usr/bin/composer

# Copy PHP configuration
COPY docker/app/php.ini $PHP_INI_DIR/conf.d/99-app.ini

# Copy Caddyfile
COPY docker/app/Caddyfile /etc/caddy/Caddyfile

# Set working directory
WORKDIR /app

EXPOSE 80

CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile"]
