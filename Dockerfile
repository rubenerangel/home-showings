# syntax=docker/dockerfile:1

# Packages shared by every PHP stage. Composer resolves against the same PHP that ships.
FROM alpine:3.22 AS base
RUN apk add --no-cache \
        nginx \
        php84 php84-fpm php84-opcache \
        php84-ctype php84-curl php84-dom php84-fileinfo php84-iconv php84-intl \
        php84-mbstring php84-openssl php84-pdo_pgsql php84-phar php84-session \
        php84-tokenizer php84-xml php84-xmlwriter \
    && ln -sf php84 /usr/bin/php \
    && adduser -S -D -H -u 10001 -G www-data app
WORKDIR /app

FROM base AS vendor
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
RUN apk add --no-cache php84-zip
COPY composer.json composer.lock ./
RUN --mount=type=cache,target=/root/.composer/cache \
    composer install --no-dev --no-scripts --no-interaction --optimize-autoloader

FROM vendor AS vendor-dev
RUN --mount=type=cache,target=/root/.composer/cache \
    composer install --no-scripts --no-interaction

FROM node:24-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json .npmrc ./
RUN --mount=type=cache,target=/root/.npm npm ci
COPY vite.config.js tsconfig.json ./
COPY resources resources
RUN npm run typecheck
RUN npm test
RUN npm run build

FROM base AS runtime
COPY docker/php.ini /etc/php84/conf.d/99-app.ini
COPY docker/php-fpm.conf /etc/php84/php-fpm.conf
COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY --chmod=755 docker/entrypoint.sh /usr/local/bin/entrypoint
COPY --from=vendor /app/vendor vendor
COPY artisan composer.json ./
COPY bootstrap bootstrap
COPY config config
COPY public public
COPY routes routes
COPY database database
COPY resources/views resources/views
COPY --from=assets /app/public/build public/build
COPY app app
# New named volumes inherit the owner of the directory they are mounted on.
RUN mkdir -p bootstrap/cache storage/app/private storage/app/public storage/logs \
        storage/framework/cache/data storage/framework/sessions storage/framework/views \
        /var/lib/home-showings \
    && ln -s ../storage/app/public public/storage \
    && chown -R app:www-data bootstrap/cache storage /var/lib/home-showings
USER app
EXPOSE 8080
ENTRYPOINT ["entrypoint"]
CMD ["serve"]

FROM runtime AS test
COPY --link --from=vendor-dev /app/vendor vendor
COPY phpunit.xml ./
COPY resources resources
COPY tests tests
CMD ["sh", "-c", "vendor/bin/pint --test && vendor/bin/phpunit"]
