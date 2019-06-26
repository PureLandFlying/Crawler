FROM php:7.3-cli
RUN apt-get update && apt-get install -y --no-install-recommends apt-utils git zip unzip libzip-dev \
    && curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer \
    && composer --version

RUN pecl install xdebug; exit 0
RUN docker-php-ext-enable xdebug; exit 0