# Production image for the Laravel API (PHP 8.4 + Apache).
#   docker build -t armorhub-api apps/api
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction \
    --ignore-platform-req=ext-intl --ignore-platform-req=ext-bcmath --ignore-platform-req=ext-zip --ignore-platform-req=ext-simplexml

FROM php:8.4-apache
RUN apt-get update && apt-get install -y --no-install-recommends \
        libicu-dev libzip-dev unzip curl \
    && docker-php-ext-install pdo_mysql bcmath intl zip opcache \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public \
    PORT=80
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf \
    && printf '<Directory ${APACHE_DOCUMENT_ROOT}>\n    AllowOverride All\n    Require all granted\n</Directory>\n' > /etc/apache2/conf-available/laravel.conf \
    && sed -ri -e 's/^Listen 80$/Listen ${PORT}/' /etc/apache2/ports.conf \
    && sed -ri -e 's/<VirtualHost \*:80>/<VirtualHost *:${PORT}>/' /etc/apache2/sites-available/000-default.conf \
    && a2enconf laravel \
    && printf 'opcache.enable=1\nopcache.validate_timestamps=0\nopcache.memory_consumption=128\n' > /usr/local/etc/php/conf.d/opcache.ini \
    && printf 'expose_php=Off\n' > /usr/local/etc/php/conf.d/hardening.ini

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/html
COPY --from=vendor /app/vendor ./vendor
COPY . .
RUN composer dump-autoload --optimize --no-dev --no-scripts \
    && mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

HEALTHCHECK --interval=30s --timeout=5s --retries=5 CMD curl -fsS http://localhost:${PORT}/up || exit 1
ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["apache2-foreground"]
