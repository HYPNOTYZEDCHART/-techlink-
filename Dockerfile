FROM php:8.2-apache

# Installer les dépendances système nécessaires
RUN apt-get update && apt-get install -y \
    libicu-dev \
    libpq-dev \
    libzip-dev \
    unzip \
    git \
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

# Configuration Apache pour Symfony (Pointer vers le dossier public et autoriser le routage)
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf
RUN sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

# Installer Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copier les fichiers du projet
COPY . .

# Autoriser Composer en tant que root pour l'installation
ENV COMPOSER_ALLOW_SUPERUSER=1
ENV APP_ENV=prod

# Installer les dépendances Composer pour la production
RUN composer install --no-dev --optimize-autoloader

# Donner les bons droits à Apache pour les dossiers inscriptibles
RUN mkdir -p var public/uploads \
    && chown -R www-data:www-data var public/uploads

# Compiler les assets pour la production (Tailwind + AssetMapper)
RUN php bin/console tailwind:build --minify \
    && php bin/console asset-map:compile

EXPOSE 80

# Script de démarrage inline (évite les problèmes CRLF Windows/Linux)
RUN printf '#!/bin/bash\nset -e\necho "=== Mise a jour schema DB..."\nphp bin/console doctrine:schema:update --force --no-interaction\necho "=== Demarrage Apache..."\nexec apache2-foreground\n' > /usr/local/bin/start.sh \
    && chmod +x /usr/local/bin/start.sh

CMD ["/usr/local/bin/start.sh"]
