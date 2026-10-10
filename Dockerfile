FROM php:8.4-apache

# Habilitar mod_rewrite para el .htaccess
RUN a2enmod rewrite

# Instalar dependencias del sistema y extensiones PHP
RUN apt-get update && apt-get install -y \
    libzip-dev \
    unzip \
    && docker-php-ext-install pdo_mysql zip

# Instalar extension de Redis
RUN pecl install redis \
    && docker-php-ext-enable redis

# Cambiar el DocumentRoot a la carpeta public
ENV APACHE_DOCUMENT_ROOT /var/www/html/public

RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

WORKDIR /var/www/html

# Dar permisos
RUN chown -R www-data:www-data /var/www/html
