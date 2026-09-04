# syntax=docker/dockerfile:1

# Shared base for local development and future ECR production images.
FROM php:8.3-fpm-alpine AS base

RUN apk add --no-cache \
        libpng-dev \
        libzip-dev \
        oniguruma-dev \
        libjpeg-turbo-dev \
        libwebp-dev \
    && docker-php-ext-configure gd --with-jpeg --with-webp \
    && docker-php-ext-install \
        pdo_mysql \
        mbstring \
        exif \
        pcntl \
        bcmath \
        gd \
        zip

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# zz- prefix ensures this loads after any extension-provided defaults in
# conf.d (php.ini conf.d files are loaded alphabetically). Path is relative
# to this Dockerfile's build context (./docker/php, see docker-compose.yml).
COPY conf.d/app.ini /usr/local/etc/php/conf.d/zz-app.ini

WORKDIR /var/www/html

# --- Local development target ---
# Application code is bind-mounted by docker-compose; nothing is copied here.
FROM base AS dev

# --- Production build target (for future ECR image) ---
FROM base AS build

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-interaction --optimize-autoloader

COPY . .
RUN composer dump-autoload --optimize
