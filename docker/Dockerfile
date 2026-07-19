# Conteneur applicatif Passerelle — PHP 8.4 + Apache (architecture de déploiement, conception générale §2.2)
FROM php:8.4-apache

# Extensions requises : MySQL (PDO) ; zip/intl utiles à Laravel et dompdf
RUN apt-get update && apt-get install -y --no-install-recommends \
        libzip-dev libicu-dev unzip \
    && docker-php-ext-install pdo_mysql zip intl \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Apache : racine documentaire sur public/ et réécriture d'URL
RUN a2enmod rewrite \
    && sed -ri 's#/var/www/html#/var/www/html/public#g' /etc/apache2/sites-available/000-default.conf

WORKDIR /var/www/html
