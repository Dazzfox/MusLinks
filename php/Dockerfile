FROM php:8.4-apache

# Installation des pré-requis Symfony
RUN apt-get update \
    && apt-get install -y build-essential curl zlib1g-dev g++ libicu-dev zip libzip-dev libpng-dev libjpeg-dev libwebp-dev libfreetype-dev \
    && docker-php-ext-install mysqli pdo pdo_mysql intl opcache \
    && pecl install apcu \
    && docker-php-ext-enable apcu \
    && docker-php-ext-configure zip \
    && docker-php-ext-install zip

# Installation de la gestion des images
RUN docker-php-ext-configure gd --with-freetype --with-webp --with-jpeg \
    && docker-php-ext-configure gd \
    && docker-php-ext-configure exif

RUN a2enmod rewrite && a2enmod socache_shmcb

WORKDIR /var/www/html

# On installe composer et Symfony CLI dans le conteneur
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

RUN curl -sS https://get.symfony.com/cli/installer | bash
RUN mv /root/.symfony5/bin/symfony /usr/local/bin/symfony