# Dockerfile raíz para Render (usa Root Directory = repo raíz por defecto).
# Construye el backend PHP que vive en backend/ (misma receta que backend/Dockerfile).
FROM php:8.2-apache

# Build marker: auth fallback for Render deployment.
ENV ECORUTA_AUTH_FALLBACK=enabled

# Instalar dependencias del sistema y extensiones de PHP requeridas
# (incluye Node 22 + libs de Chromium para que factura-py genere HTML/PDF)
RUN apt-get update && apt-get install -y \
    ca-certificates \
    curl \
    unzip \
    git \
    libzip-dev \
    libnss3 libnspr4 libatk1.0-0 libatk-bridge2.0-0 libcups2 libdrm2 \
    libxkbcommon0 libxcomposite1 libxdamage1 libxfixes3 libxrandr2 \
    libgbm1 libpango-1.0-0 libcairo2 libasound2 \
    && curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
    && apt-get install -y nodejs \
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
COPY backend/composer.json backend/composer.lock* ./
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts || true

# Copiar el resto del código del backend
COPY backend/ /var/www/html/

# Copiar el motor de facturas (factura-py) junto al backend.
# El PHP lo busca en /var/www/factura-py, /var/www/html/factura-py y la raíz del repo.
COPY factura-py/ /var/www/factura-py/
# Compatibilidad: también disponible dentro del docroot por si el código
# resuelve dirname(__DIR__,2) . '/factura-py'.
RUN mkdir -p /var/www/html/factura-py && cp -r /var/www/factura-py/* /var/www/html/factura-py/ || true

# Dependencias de factura-py (puppeteer). El modo HTML no necesita Chromium,
# el PDF sí: se intenta descargar Chrome sin romper el build si falla.
RUN cd /var/www/factura-py && npm install --no-audit --no-fund \
    && (npx puppeteer browsers install chrome || true)

ENV FACTURA_PY_PATH=/var/www/factura-py
ENV FACTURA_NODE_BIN=node

# Generar autoload optimizado
RUN composer dump-autoload --optimize --no-dev

# Asignar permisos al usuario de Apache
RUN chown -R www-data:www-data /var/www/html /var/www/factura-py

# Script de inicio compatible con el puerto dinámico de Render ($PORT)
RUN echo '#!/bin/bash\n\
    PORT="${PORT:-80}"\n\
    sed -i "s/Listen 80/Listen $PORT/g" /etc/apache2/ports.conf\n\
    sed -i "s/:80/:$PORT/g" /etc/apache2/sites-available/000-default.conf\n\
    exec apache2-foreground' > /usr/local/bin/run-app.sh && chmod +x /usr/local/bin/run-app.sh

EXPOSE 80 10000

CMD ["/usr/local/bin/run-app.sh"]
