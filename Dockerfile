# ==============================================================================
# Stage 1: Composer Vendor Dependencies
# ==============================================================================
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-scripts \
    --no-autoloader \
    --prefer-dist

# ==============================================================================
# Stage 2: Frontend Asset Build (Vite + Tailwind)
# ==============================================================================
FROM node:20-alpine AS frontend
WORKDIR /app
COPY package.json ./
RUN npm install
COPY vite.config.js ./
COPY resources ./resources
COPY public ./public
RUN npm run build

# ==============================================================================
# Stage 3: Base PHP Runtime + Extensions
# ==============================================================================
FROM php:8.3-cli-alpine AS base

RUN apk add --no-cache \
        curl \
        icu-libs \
        libxml2 \
        oniguruma \
        sqlite-libs \
    && apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS \
        icu-dev \
        libxml2-dev \
        oniguruma-dev \
        sqlite-dev \
    && docker-php-ext-install \
        bcmath \
        intl \
        mbstring \
        pcntl \
        pdo_mysql \
        pdo_sqlite \
        xml \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY . .
COPY --from=vendor /app/vendor ./vendor
COPY --from=frontend /app/public/build ./public/build

RUN composer dump-autoload --optimize --no-dev

# ==============================================================================
# Stage 4: Test Stage (untuk CI/CD Jenkins: docker build --target test)
# ==============================================================================
FROM base AS test
RUN composer install --no-interaction --prefer-dist \
    && cp .env.example .env \
    && php artisan key:generate
RUN php artisan test
CMD ["php", "artisan", "test"]

# ==============================================================================
# Stage 5: Production Stage (untuk Container app & worker)
# ==============================================================================
FROM base AS production

COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN sed -i 's/\r$//' /usr/local/bin/docker-entrypoint.sh \
    && chmod +x /usr/local/bin/docker-entrypoint.sh \
    && chmod -R 775 storage bootstrap/cache

EXPOSE 8000

HEALTHCHECK --interval=10s --timeout=5s --retries=5 --start-period=15s \
    CMD curl -fsS http://localhost:8000/up || exit 1

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000", "--no-reload"]
