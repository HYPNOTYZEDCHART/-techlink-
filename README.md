# TechLink — Boutique e-commerce électronique

Site e-commerce complet développé avec Symfony, pour la vente de produits électroniques (téléphones, ordinateurs, accessoires) au Sénégal.

## Stack technique

- **Backend** : Symfony 7, Doctrine ORM, MariaDB
- **Frontend** : Twig, Tailwind CSS, Symfony UX (Turbo + Stimulus)
- **Admin** : EasyAdmin
- **Upload d'images** : VichUploaderBundle
- **Auth** : Symfony Security

## Fonctionnalités

- Catalogue produits avec filtres (catégorie, marque), recherche avec autocomplete, pagination
- Fiches produits avec galerie d'images, choix de couleur
- Comptes clients (inscription/connexion), historique des commandes
- Panier et système de commande complet
- Back-office admin avec statistiques (produits, catégories, commandes, chiffre d'affaires)
- Commande directe via WhatsApp
- Newsletter

## Installation

```bash
composer install
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate
php bin/console tailwind:build
symfony server:start
```

Configurer `.env.local` avec votre `DATABASE_URL`.

## Auteur

Becaye Doumbouya — étudiant en Licence Informatique de Gestion, UCAO ISG Saint Michel, Dakar.
