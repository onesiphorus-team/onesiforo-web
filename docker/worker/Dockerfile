# =============================================================================
# PHP CLI Worker
# =============================================================================
# PHP 8.4 CLI on Alpine Linux
# Shared image for queue, scheduler, and reverb containers
# =============================================================================

FROM composer:2 AS composer

FROM php:8.4-cli-alpine

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

# Install PHP extensions (same as app container)
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
COPY docker/app/php.ini /usr/local/etc/php/conf.d/99-app.ini

WORKDIR /app
