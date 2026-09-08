# syntax=docker/dockerfile:1

# ── Stage 1: front-end assets (Vite 7 needs Node >= 22) ─────────────────
FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY vite.config.js tailwind.config.js postcss.config.js jsconfig.json ./
COPY resources ./resources
COPY public ./public
RUN npm run build

# ── Stage 2: composer dependencies ──────────────────────────────────────
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --no-interaction --no-scripts --no-autoloader
COPY . .
RUN composer dump-autoload --optimize --no-dev --no-scripts

# ── Stage 3: runtime ────────────────────────────────────────────────────
FROM serversideup/php:8.3-fpm-nginx AS app

USER root
# pdo_pgsql ships with the image; this is a no-op safety net if a tag drops it
RUN php -m | grep -qi pdo_pgsql || install-php-extensions pdo_pgsql
COPY --chmod=755 docker/entrypoint.d/ /etc/entrypoint.d/

COPY --from=vendor --chown=www-data:www-data /app /var/www/html
COPY --from=assets --chown=www-data:www-data /app/public/build /var/www/html/public/build

USER www-data

# serversideup automations: storage:link, config/route/view cache, migrate --force
ENV AUTORUN_ENABLED=true \
    PHP_OPCACHE_ENABLE=1
