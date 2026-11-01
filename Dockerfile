# --- Étape 1 : L'application et les dépendances Composer ---
FROM dunglas/frankenphp:1-php8.5-alpine AS app-base

ARG APP_VERSION=local-dev

# Rattache l'image publiée sur GHCR au dépôt GitHub (page du package, droits, licence).
LABEL org.opencontainers.image.source="https://github.com/pokertour/Deepsky" \
      org.opencontainers.image.description="Deepsky: self-hosted astrophotography portfolio and NightScope cockpit" \
      org.opencontainers.image.licenses="MIT" \
      org.opencontainers.image.version="${APP_VERSION}"

# Configuration globale pour Octane et Redis (Baked-in)
ENV OCTANE_SERVER=frankenphp \
    REDIS_HOST=redis \
    CACHE_STORE=redis \
    SESSION_DRIVER=redis \
    QUEUE_CONNECTION=redis \
    APP_ENV=production \
    APP_VERSION=${APP_VERSION} \
    APP_RUNTIME_CACHE=true \
    IMAGE_DRIVER=imagick

# Supervisor + délégués ImageMagick (PNG est dans le paquet de base, pas un sous-paquet)
RUN apk add --no-cache \
    supervisor \
    git \
    imagemagick \
    imagemagick-jpeg \
    imagemagick-tiff \
    imagemagick-webp

# Installation des extensions via l'installeur pré-inclus
RUN install-php-extensions \
    gd \
    exif \
    pdo_mysql \
    pdo_pgsql \
    zip \
    intl \
    bcmath \
    opcache \
    mbstring \
    imagick \
    redis \
    pcntl \
    posix

# Optimisations PHP 8.5 (JIT)
RUN echo "upload_max_filesize=300M" > /usr/local/etc/php/conf.d/prod.ini && \
    echo "post_max_size=300M" >> /usr/local/etc/php/conf.d/prod.ini && \
    echo "memory_limit=1024M" >> /usr/local/etc/php/conf.d/prod.ini && \
    echo "opcache.enable_cli=1" >> /usr/local/etc/php/conf.d/prod.ini && \
    echo "opcache.jit=tracing" >> /usr/local/etc/php/conf.d/prod.ini

COPY docker/imagemagick-policy.xml /etc/ImageMagick-7/policy.xml

# Config Supervisor
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Script d'entrée (Entrypoint)
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Installation de Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copie du code
COPY . .

# Installation propre des dépendances
RUN composer install --no-dev --optimize-autoloader

# --- Étape 2 : Compilation des assets avec le même Livewire que l'application ---
FROM node:22-alpine AS assets-builder
WORKDIR /app
COPY package*.json ./
RUN npm ci
COPY . .
COPY --from=app-base /var/www/html/vendor/livewire/livewire ./vendor/livewire/livewire
RUN npm run build

# --- Étape 3 : Image finale Octane + FrankenPHP ---
FROM app-base AS app
COPY --from=assets-builder /app/public/build ./public/build

# On prépare les dossiers pour les volumes et Caddy
RUN mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views database /config/caddy /data/caddy \
    && chown -R www-data:www-data /var/www/html /config /data

# FrankenPHP utilise le port 80 par défaut
EXPOSE 80

# /up vérifie Laravel, la base et le cache ; start-period couvre les migrations de l'entrypoint.
HEALTHCHECK --interval=30s --timeout=10s --start-period=120s --retries=3 \
    CMD php /var/www/html/docker/healthcheck.php

# On expose aussi le port 443 si besoin d'HTTPS direct, mais ici Pangolin gère l'amont
# EXPOSE 443

ENTRYPOINT ["entrypoint.sh"]
