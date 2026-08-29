#!/bin/bash
set -e

echo "=== Démarrage TechLink ==="
echo "1. Mise à jour du schéma de base de données..."
php bin/console doctrine:schema:update --force --no-interaction

echo "2. Nettoyage du cache de production..."
php bin/console cache:warmup --env=prod --no-interaction || true

echo "3. Démarrage du serveur Apache..."
exec apache2-foreground
