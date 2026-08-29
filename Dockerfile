FROM php:8.2-apache

# Installer les dépendances système nécessaires
RUN apt-get update && apt-get install -y \
    libicu-dev \
    libpq-dev \
    libzip-dev \
    unzip \
    git \
    nodejs \
    npm \
    && rm -rf /var/lib/apt/lists/*

# Installer les extensions PHP
RUN docker-php-ext-configure pgsql -with-pgsql=/usr/local/pgsql \
    && docker-php-ext-install \
    intl \
    pdo_pgsql \
    pgsql \
    zip \
    opcache

# Activer le mod_rewrite pour Apache
RUN a2enmod rewrite

# Configuration Apache pour Symfony
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf \
    && sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

# Installer Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copier les fichiers du projet
COPY . .

# Variables d'environnement pour le build
ENV COMPOSER_ALLOW_SUPERUSER=1
ENV APP_ENV=prod
ENV APP_SECRET=buildsecret

# Installer les dépendances PHP (sans dev, sans scripts post-install qui nécessitent la DB)
RUN composer install --no-dev --optimize-autoloader --no-scripts

# Générer l'autoloader optimisé manuellement (remplace les scripts Composer)
RUN composer dump-autoload --optimize --no-dev

# Installer les assets JS (Stimulus, Turbo, etc.) - remplace le script post-install
RUN php bin/console importmap:install --no-interaction

# Compiler les assets CSS/JS avec Tailwind (pas de DB requise)
RUN php bin/console tailwind:build --minify --no-interaction || true
RUN php bin/console asset-map:compile --no-interaction || true

# Donner les bons droits à Apache pour les dossiers inscriptibles
RUN mkdir -p var/sessions/prod var/sessions/dev var/cache var/log public/uploads \
    && chown -R www-data:www-data var public/uploads \
    && chmod -R 775 var public/uploads

EXPOSE 80

# Script de démarrage : migrations + sessions table + cache + Apache
RUN printf '#!/bin/sh\nset -e\necho "[TechLink] Mise a jour schema DB..."\nphp bin/console doctrine:schema:update --force --no-interaction 2>&1 || true\necho "[TechLink] Creation table sessions..."\nphp bin/console dbal:run-sql "CREATE TABLE IF NOT EXISTS sessions (sess_id VARCHAR(128) NOT NULL PRIMARY KEY, sess_data BYTEA NOT NULL, sess_time INTEGER NOT NULL, sess_lifetime INTEGER NOT NULL)" --no-interaction 2>&1 || true\necho "[TechLink] Cache warmup..."\nphp bin/console cache:warmup --env=prod --no-interaction 2>&1 || true\necho "[TechLink] Apache start..."\nexec apache2-foreground\n' > /usr/local/bin/start.sh \
    && chmod +x /usr/local/bin/start.sh

CMD ["/usr/local/bin/start.sh"]
