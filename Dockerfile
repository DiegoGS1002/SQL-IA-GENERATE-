FROM composer:2 AS php-dependencies

WORKDIR /app

COPY . .

RUN composer install \
    --no-interaction \
    --no-progress \
    --prefer-dist \
    --optimize-autoloader

FROM node:22-bookworm-slim AS frontend-assets

WORKDIR /app

COPY . .

RUN npm ci --ignore-scripts && npm run build

FROM php:8.4-cli-bookworm

RUN docker-php-ext-install bcmath pdo_mysql

WORKDIR /var/www/html

COPY --from=php-dependencies /app /var/www/html
COPY --from=frontend-assets /app/public/build /var/www/html/public/build

RUN chown -R www-data:www-data storage bootstrap/cache

USER www-data

EXPOSE 8000

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
