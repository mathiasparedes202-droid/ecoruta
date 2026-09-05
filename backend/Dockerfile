FROM php:8.2-apache

# Build marker: auth fallback for Render deployment.
ENV ECORUTA_AUTH_FALLBACK=enabled

# Instalar dependencias del sistema y extensiones de PHP requeridas
RUN apt-get update && apt-get install -y \
    ca-certificates \
    unzip \
    git \
    libzip-dev \
    && docker-php-ext-install pdo pdo_mysql zip \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Instalar Composer desde la imagen oficial
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Habilitar el módulo rewrite de Apache
RUN a2enmod rewrite

# Permitir directivas de .htaccess en Apache
RUN sed -ri -e 's!AllowOverride None!AllowOverride All!g' /etc/apache2/apache2.conf

WORKDIR /var/www/html

# Copiar archivos de dependencias de Composer primero para optimizar la caché
COPY composer.json composer.lock* ./
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts || true

# Copiar el resto del código del backend
COPY . /var/www/html/

# Generar autoload optimizado
RUN composer dump-autoload --optimize --no-dev

# Asignar permisos al usuario de Apache
RUN chown -R www-data:www-data /var/www/html

# Script de inicio compatible con el puerto dinámico de Render ($PORT)
RUN echo '#!/bin/bash\n\
    PORT="${PORT:-80}"\n\
    sed -i "s/Listen 80/Listen $PORT/g" /etc/apache2/ports.conf\n\
    sed -i "s/:80/:$PORT/g" /etc/apache2/sites-available/000-default.conf\n\
    exec apache2-foreground' > /usr/local/bin/run-app.sh && chmod +x /usr/local/bin/run-app.sh

EXPOSE 80 10000

CMD ["/usr/local/bin/run-app.sh"]
