# Imagen base
FROM php:8.2-apache

# --- FASE 1: Instalación de dependencias ---
RUN apt-get update && apt-get install -y \
    build-essential libzip-dev libfreetype6-dev libjpeg62-turbo-dev \
    libpng-dev libicu-dev unzip git libpcre2-dev gcc make autoconf pkg-config default-mysql-client supervisor \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd intl pdo_mysql zip \
    && pecl install apcu-5.1.21 \
    && docker-php-ext-enable apcu \
    && rm -rf /var/lib/apt/lists/*

# Desactivar OPcache
RUN echo "opcache.enable=0" > /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.enable_cli=0" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.validate_timestamps=1" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.revalidate_freq=0" >> /usr/local/etc/php/conf.d/opcache.ini

# Instalar Node.js
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs \
    && npm install -g npm@10.2.4

# --- FASE 2: Configuración de Apache ---
RUN a2enmod rewrite headers
COPY .docker/apache/000-default.conf /etc/apache2/sites-available/
COPY .docker/apache/apache2.conf /etc/apache2/
COPY .docker/supervisor/messenger-consume.conf /etc/supervisor/conf.d/messenger-consume.conf

# --- FASE 3: Configuración de PHP ---
COPY .docker/php/php.ini /usr/local/etc/php/

# --- FASE 4: Preparación del workspace ---
WORKDIR /var/www/html
COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer





# ---- FASE 5: Dependencias PHP y Node ----
# Copiamos solo los archivos necesarios para aprovechar el cache de Docker
COPY composer.json composer.lock ./
ENV COMPOSER_ALLOW_SUPERUSER=1
RUN composer install --no-scripts --no-autoloader --no-progress --prefer-dist

COPY package.json package-lock.json ./
RUN npm install --legacy-peer-deps
RUN npm install -g expo-cli
RUN npm install expo-dev-client
RUN npx expo install expo-notifications

# --- FASE 6: Copia del código fuente ---
COPY . .

# --- FASE 7: Archivos adicionales ---
COPY public/.htaccess /var/www/html/public/.htaccess

# --- FASE 8: Build final y limpieza ---
# Separamos los comandos para mejor debugging
RUN composer dump-autoload --optimize --no-dev

# Build de assets con Webpack Encore
RUN npm run build

# Limpieza final
RUN npm cache clean --force \
    && rm -rf node_modules

# --- FASE 9: Permisos mejorados ---
RUN mkdir -p /var/www/html/var \
    && mkdir -p /var/www/html/public/uploads \
    && mkdir -p /var/www/html/config/jwt \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 775 /var/www/html/var \
    && chmod -R 775 /var/www/html/public || true \
    && chmod -R 775 /var/www/html/public/uploads || true \
    && chown www-data:www-data /var/www/html/config/jwt \
    && chmod 750 /var/www/html/config/jwt

# --- FASE 10: Entrypoint ---
COPY docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh
ENTRYPOINT ["docker-entrypoint.sh"]