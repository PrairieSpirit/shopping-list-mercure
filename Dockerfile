FROM php:8.4-fpm

RUN apt-get update && apt-get install -y     git curl zip unzip libicu-dev libzip-dev     libonig-dev libxml2-dev default-mysql-client     && docker-php-ext-install pdo pdo_mysql intl zip opcache mbstring     && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY docker/php/php.ini /usr/local/etc/php/conf.d/app.ini

WORKDIR /var/www/html

EXPOSE 9000
CMD ["php-fpm"]
