# Laravel API image, used by the "app" profile in docker-compose.yml for the
# api, queue worker and scheduler containers (same image, different command).
# Built for local review: it uses `php artisan serve` and keeps dev packages
# (Faker is needed by the seeder). For production you would use PHP-FPM/FrankenPHP
# behind nginx and `composer install --no-dev`.

# ---- Stage 1: install PHP packages with Composer ----
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-scripts --no-autoloader --prefer-dist --no-interaction --no-progress --ignore-platform-reqs
COPY . .
RUN composer dump-autoload --optimize --no-scripts

# ---- Stage 2: the runtime image ----
FROM php:8.4-cli

# PHP extensions the app needs: MySQL driver, intl, and pcntl (lets queue:work stop gracefully).
RUN apt-get update \
    && apt-get install -y --no-install-recommends libicu-dev \
    && docker-php-ext-install pdo_mysql intl pcntl \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www
COPY --from=vendor /app /var/www

RUN php artisan package:discover --ansi \
    && chown -R www-data:www-data storage bootstrap/cache

# Don't run as root.
USER www-data

EXPOSE 8000
ENTRYPOINT ["sh", "/var/www/docker/entrypoint.sh"]
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
