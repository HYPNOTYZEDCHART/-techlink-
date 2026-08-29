# 🛒 TechLink — Plateforme E-Commerce Moderne

![Symfony](https://img.shields.io/badge/Symfony-7.4-black?style=for-the-badge&logo=symfony)
![PHP](https://img.shields.io/badge/PHP-8.2+-777BB4?style=for-the-badge&logo=php&logoColor=white)
![Tailwind CSS](https://img.shields.io/badge/Tailwind_CSS-v4-38B2AC?style=for-the-badge&logo=tailwind-css&logoColor=white)
![MariaDB](https://img.shields.io/badge/MariaDB-003545?style=for-the-badge&logo=mariadb&logoColor=white)

**TechLink** est une application web e-commerce de pointe, conçue pour la vente de matériel technologique et informatique au Sénégal.  
Construite comme un **projet de portfolio de niveau professionnel**, cette plateforme démontre la capacité à allier des technologies de pointe (Symfony 7, Tailwind v4) avec une logique métier complexe, robuste et hautement sécurisée.

---

## ✨ Fonctionnalités Clés & Logique Métier

### 🛍️ Parcours d'Achat (Checkout) Avancé
- **Achat sans compte (Guest Checkout)** : Un système résilient permettant aux visiteurs d'ajouter au panier et de commander sans friction. La persistance du panier est gérée via des tokens de session sécurisés côté serveur.
- **Gestion Stricte des Stocks par Variantes** : L'inventaire n'est pas seulement global. Les stocks sont décomptés en fonction des déclinaisons choisies (par exemple : "iPhone 15 - *Rouge*").
- **Intégrité Transactionnelle** : La validation des commandes et la décrémentation des stocks s'effectuent au sein de **transactions Doctrine** pour prévenir toute corruption de données ou vente à découvert.
- **Frais de Livraison Dynamiques** : Calcul en temps réel lors du checkout, basé sur la zone géographique choisie.

### 🛡️ Sécurité de Niveau Production
- **Protection CSRF Globale** : Implémentation stricte de jetons anti-falsification (CSRF) sur l'ensemble des routes altérant l'état (panier, suppression d'avis, validation de commande).
- **Prévention IDOR** : Sécurisation de l'accès aux ressources. Il est techniquement impossible d'altérer le panier d'un autre utilisateur ou de consulter une facture PDF qui ne nous appartient pas (vérification par propriété de l'entité et/ou Session ID).
- **Mots de Passe & Autorisations** : Hachage de pointe avec l'algorithme auto-géré par Symfony, et système d'autorisations (RBAC) isolant strictement l'espace d'administration.

### ⚡ Expérience Utilisateur (UX) Dynamique
- **Symfony UX & Stimulus** : Le site offre une sensation de "Single Page Application" (SPA) grâce à des requêtes asynchrones pour l'ajout au panier, la mise en favoris, et la recherche (Autocomplete), sans rechargement complet de la page.
- **Génération de PDF** : Création automatique et à la volée de factures téléchargeables pour chaque commande validée (via *Dompdf*).
- **Avis Vérifiés** : Système d'avis et de notation (1 à 5 étoiles) restreint algorithmiquement aux seuls acheteurs réels du produit.

---

## 🛠️ Stack Technique & Architecture de Déploiement

- **Langage** : PHP 8.2+
- **Framework Backend** : Symfony 7.4
- **ORM & Base de Données** : Doctrine ORM couplé à PostgreSQL (Production) / MariaDB (Développement).
- **Stockage Cloud (S3)** : Supabase Storage (S3-compatible) géré via *Flysystem* et *VichUploader* pour garantir la persistance des images sur un environnement cloud (nécessaire pour les hébergements immuables comme Render).
- **Frontend** : Twig (Moteur de templates), Tailwind CSS v4 (Design system utilitaire).
- **Interactivité** : Symfony UX, Hotwired Stimulus, Turbo.
- **Administration** : EasyAdmin 5 (Dashboard complet pour la gestion CRUD des produits, commandes, et utilisateurs).
- **Infrastructure de Production (Render)** :
  - **Docker** : Conteneurisation de l'application (Apache + PHP 8.2).
  - **Sessions Database** : Persistance des sessions dans PostgreSQL pour éviter les déconnexions sur un environnement stateless.
  - **Proxy de confiance** : Configuration des en-têtes HTTP pour la détection sécurisée du HTTPS derrière le Load Balancer de Render.

---

## 🚀 Installation & Déploiement Local

Assurez-vous d'avoir PHP 8.2+ et Composer installés sur votre machine.

1. **Cloner le dépôt**
   ```bash
   git clone <url-du-depot>
   cd mon-projet
   ```

2. **Installer les dépendances PHP**
   ```bash
   composer install
   ```

3. **Configurer l'environnement (.env.local)**
   Configurez la base de données et les clés d'accès S3 Supabase pour les images :
   ```env
   DATABASE_URL="mysql://root:@127.0.0.1:3306/mon_projet?serverVersion=10.4.32-MariaDB&charset=utf8mb4"
   
   SUPABASE_URL="https://votre-projet.supabase.co"
   SUPABASE_BUCKET="techlink"
   SUPABASE_REGION="eu-west-1"
   SUPABASE_ENDPOINT="https://votre-projet.storage.supabase.co"
   SUPABASE_ACCESS_KEY="votre-access-key"
   SUPABASE_SECRET_KEY="votre-secret-key"
   ```

4. **Initialiser la base de données**
   ```bash
   php bin/console doctrine:database:create
   php bin/console doctrine:migrations:migrate
   ```

5. **Générer le CSS (Tailwind)**
   ```bash
   php bin/console tailwind:build
   ```

6. **Lancer le serveur de développement**
   ```bash
   symfony server:start
   ```
   Rendez-vous sur `http://localhost:8000`.

---

## 👨‍💻 À propos de l'Auteur

**Becaye Doumbouya**  
*Étudiant en Licence Informatique de Gestion, UCAO ISG Saint Michel, Dakar.*

Ce projet a été conçu pour démontrer mon expertise dans le développement d'applications web modernes, ma compréhension des enjeux métiers (logistique, stocks, facturation), ma rigueur concernant la sécurité des données utilisateurs, et ma capacité à déployer des architectures cloud résilientes (Docker, S3, PostgreSQL).

N'hésitez pas à me contacter via GitHub ou LinkedIn pour toute opportunité professionnelle ou question technique concernant l'architecture de ce projet !
