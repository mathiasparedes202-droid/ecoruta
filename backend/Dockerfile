FROM php:8.2-apache

# Build marker: auth fallback for Render deployment.
ENV ECORUTA_AUTH_FALLBACK=enabled

# Instalar dependencias del sistema y extensiones de PHP requeridas
# (incluye Node 22 + Chromium del sistema para factura-py; ver nota abajo)
RUN apt-get update && apt-get install -y \
    ca-certificates \
    curl \
    unzip \
    git \
    libzip-dev \
    chromium \
    libnss3 libnspr4 libatk1.0-0 libatk-bridge2.0-0 libcups2 libdrm2 \
    libxkbcommon0 libxcomposite1 libxdamage1 libxfixes3 libxrandr2 \
    libgbm1 libpango-1.0-0 libcairo2 libasound2 \
    && curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
    && apt-get install -y nodejs \
    && docker-php-ext-install pdo pdo_mysql zip \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# NOTA: este Dockerfile usa como contexto backend/, por lo que el motor
# factura-py se versiona duplicado en backend/factura-py (vendored de la
# copia raíz /factura-py). Si cambiás el motor, actualizá ambas copias.
# El endpoint HTML tiene fallback 100% PHP y funciona aun sin el motor;
# el PDF requiere Node + este motor + Chromium (instalados arriba).
ENV FACTURA_PY_PATH=/var/www/factura-py
ENV FACTURA_NODE_BIN=node
# Chromium del sistema (visible para www-data): el servicio lo usa vía
# executablePath. El caché de Puppeteer queda como respaldo compartido.
ENV PUPPETEER_EXECUTABLE_PATH=/usr/bin/chromium
ENV PUPPETEER_CACHE_DIR=/var/www/.cache/puppeteer

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
# (incluye backend/factura-py, copia vendored del motor factura-py de la raíz:
#  este contexto no puede ver ../factura-py, por eso se versiona duplicado)
COPY . /var/www/html/

# Motor de facturas: llega a /var/www/html/factura-py vía el COPY anterior.
# Se espeja a /var/www/factura-py (ruta de FACTURA_PY_PATH) y se instalan
# dependencias. El HTML no necesita Chromium; el PDF sí (se intenta sin
# romper el build si la descarga falla). Mantener sincronizado con /factura-py.
RUN mkdir -p /var/www/factura-py && cp -r /var/www/html/factura-py/* /var/www/factura-py/ \
    && cd /var/www/factura-py && npm install --no-audit --no-fund \
    && (npx puppeteer browsers install chrome || true)

# Generar autoload optimizado
RUN composer dump-autoload --optimize --no-dev

# Asignar permisos al usuario de Apache (incluye el caché de Puppeteer)
RUN mkdir -p /var/www/.cache/puppeteer \
    && chown -R www-data:www-data /var/www/html /var/www/factura-py /var/www/.cache

# Script de inicio compatible con el puerto dinámico de Render ($PORT)
RUN echo '#!/bin/bash\n\
    PORT="${PORT:-80}"\n\
    sed -i "s/Listen 80/Listen $PORT/g" /etc/apache2/ports.conf\n\
    sed -i "s/:80/:$PORT/g" /etc/apache2/sites-available/000-default.conf\n\
    exec apache2-foreground' > /usr/local/bin/run-app.sh && chmod +x /usr/local/bin/run-app.sh

EXPOSE 80 10000

CMD ["/usr/local/bin/run-app.sh"]
