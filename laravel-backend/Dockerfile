# syntax=docker/dockerfile:1
# ============================================================
# Furnib — Laravel 13 backend (admin panel + JSON API), PHP 8.3
# Multi-stage: build (composer + vite assets) → slim php-fpm + nginx runtime.
# Designed to run behind EasyPanel's Traefik (which terminates TLS); this image
# only serves plain HTTP on :80 inside the container.
# ============================================================

# ---------- Stage 1: build (composer deps + front-end assets) ----------
FROM php:8.3-cli-bookworm AS build

# Toolchain: php extensions artisan needs to boot during the asset build,
# plus Node 22 (for Vite) and Composer.
RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip ca-certificates curl gnupg \
        libzip-dev libpng-dev libjpeg-dev libfreetype6-dev libonig-dev libwebp-dev libavif-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp --with-avif \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql gd zip bcmath exif \
    && curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
    && apt-get install -y --no-install-recommends nodejs \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# PHP deps first (layer cache). Scripts/autoloader deferred until the app is in.
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

# JS deps via pnpm 9 (matches the repo's pnpm-lock.yaml; installs devDeps for Vite).
RUN npm install -g pnpm@9
COPY package.json pnpm-lock.yaml pnpm-workspace.yaml ./
RUN pnpm install --frozen-lockfile

# Full application source.
COPY . .

# Wayfinder boots artisan during `vite build`, so it needs a throwaway APP_KEY
# and a sqlite DB it never actually queries. Real config comes from EasyPanel.
ENV APP_ENV=production \
    APP_DEBUG=false \
    APP_KEY=base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA= \
    DB_CONNECTION=sqlite \
    DB_DATABASE=/tmp/build.sqlite

# Optimised autoloader + package discovery (registers the wayfinder command),
# then build the production assets into public/build.
RUN touch /tmp/build.sqlite \
    && composer dump-autoload --optimize --no-dev \
    && php artisan package:discover --ansi \
    && pnpm build \
    && rm -f /tmp/build.sqlite

# ---------- Stage 2: runtime (php-fpm + nginx + supervisor) ----------
FROM php:8.3-fpm-bookworm AS runtime

RUN apt-get update && apt-get install -y --no-install-recommends \
        nginx supervisor \
        libzip-dev libpng-dev libjpeg-dev libfreetype6-dev libonig-dev libwebp-dev libavif-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp --with-avif \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql gd zip bcmath exif opcache \
    && rm -rf /var/lib/apt/lists/*

COPY docker/php.ini /usr/local/etc/php/conf.d/zz-furnib.ini

WORKDIR /var/www/html

# App + vendor + built assets (drop dev-only cruft that bloats the image).
COPY --from=build /app /var/www/html
RUN rm -rf tests node_modules

COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/supervisord.conf /etc/supervisor/conf.d/furnib.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh

RUN chmod +x /usr/local/bin/entrypoint.sh \
    && mkdir -p storage/framework/cache/data storage/framework/sessions \
               storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

EXPOSE 80
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
