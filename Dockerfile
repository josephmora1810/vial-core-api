# ==============================================================================
# Stage 1: Servidor de Rutas OSRM (Open Source Routing Machine - Venezuela)
# ==============================================================================
FROM osrm/osrm-backend:latest AS osrm

WORKDIR /data

# Script de arranque: si el mapa compilado no existe en el volumen /data,
# descarga el PBF de Venezuela (Geofabrik), extrae/particiona/customiza (MLD)
# y levanta el motor HTTP en el puerto 5000.
CMD ["sh", "-c", "\
    if [ ! -f /data/venezuela-latest.osrm ]; then \
        echo '[OSRM] Descargando mapa de Venezuela (Geofabrik)...' && \
        wget -q -O /data/venezuela-latest.osm.pbf https://download.geofabrik.de/south-america/venezuela-latest.osm.pbf && \
        osrm-extract -p /opt/car.lua /data/venezuela-latest.osm.pbf && \
        osrm-partition /data/venezuela-latest.osrm && \
        osrm-customize /data/venezuela-latest.osrm && \
        rm -f /data/venezuela-latest.osm.pbf; \
    fi && \
    exec osrm-routed --algorithm mld /data/venezuela-latest.osrm \
"]

EXPOSE 5000

# ==============================================================================
# Stage 2: Core API (PHP Apache - Target por defecto)
# ==============================================================================
FROM php:8.4-apache AS app

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
