FROM php:8.2-apache

# Habilitar mod_rewrite de Apache para URLs amigables
RUN a2enmod rewrite

# Instalar dependencias del sistema y extensiones de PHP (PostgreSQL, unzip para Composer)
RUN apt-get update && apt-get install -y libpq-dev unzip git libzip-dev \
    && docker-php-ext-install pdo pdo_pgsql zip

# Instalar Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copiar el código del proyecto al directorio público de Apache
COPY . /var/www/html/

# Cambiar al directorio de trabajo
WORKDIR /var/www/html/

# Instalar dependencias de Composer si existe el archivo
RUN if [ -f "composer.json" ]; then composer install --no-dev --optimize-autoloader; fi

# Dar permisos correctos a los archivos para Apache
RUN chown -R www-data:www-data /var/www/html/ \
    && chmod -R 755 /var/www/html/
