<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Category;
use App\Entity\Coupon;
use App\Entity\Product;
use App\Entity\ProductColor;
use App\Entity\User;
use App\Repository\CategoryRepository;
use App\Repository\CouponRepository;
use App\Repository\ProductRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:seed',
    description: 'Peuple la base de données avec le catalogue initial, catégories, admin et codes promo.',
)]
class SeedDatabaseCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CategoryRepository $categoryRepository,
        private readonly ProductRepository $productRepository,
        private readonly UserRepository $userRepository,
        private readonly CouponRepository $couponRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Initialisation du catalogue TechLink...');

        // 1. Catégories
        $categoriesData = [
            'telephones' => 'Téléphones',
            'ordinateurs-portables' => 'Ordinateurs Portables',
            'accessoires' => 'Accessoires',
            'audio' => 'Audio',
            'tablettes' => 'Tablettes',
            'ecrans-moniteurs' => 'Écrans & Moniteurs',
        ];

        $categories = [];
        foreach ($categoriesData as $slug => $name) {
            $cat = $this->categoryRepository->findOneBy(['slug' => $slug]);
            if (!$cat) {
                $cat = new Category();
                $cat->setName($name);
                $cat->setSlug($slug);
                $this->entityManager->persist($cat);
                $io->writeln(sprintf('  + Catégorie créée : %s', $name));
            }
            $categories[$slug] = $cat;
        }
        $this->entityManager->flush();

        // 2. Produits
        $productsData = [
            [
                'name' => 'iPhone 15 Pro Max',
                'slug' => 'iphone-15-pro-max',
                'price' => 850000,
                'stock' => 37,
                'brand' => 'Apple',
                'category' => 'telephones',
                'description' => 'Le nec plus ultra d\'Apple avec puce A17 Pro, design en titane et téléobjectif 5x exceptionnel.',
                'specifications' => [
                    'Écran' => '6.7" Super Retina XDR OLED 120Hz',
                    'Puce' => 'A17 Pro (3nm)',
                    'Appareil Photo' => '48 MP principal + 12 MP ultra-grand-angle + 12 MP 5x',
                    'Stockage' => '256 Go',
                    'Batterie' => '4422 mAh — Autonomie toute la journée',
                ],
                'colors' => [
                    ['name' => 'Titane Naturel', 'hex' => '#9e9992', 'stock' => 15],
                    ['name' => 'Titane Noir', 'hex' => '#2f3032', 'stock' => 12],
                    ['name' => 'Titane Bleu', 'hex' => '#2a333f', 'stock' => 10],
                ],
            ],
            [
                'name' => 'Samsung Galaxy S24 Ultra',
                'slug' => 'samsung-galaxy-s24-ultra',
                'price' => 820000,
                'stock' => 20,
                'brand' => 'Samsung',
                'category' => 'telephones',
                'description' => 'Le fleuron Galaxy avec Galaxy AI intégré, cadre en titane, écran antireflet ultra-lumineux et S-Pen inclus.',
                'specifications' => [
                    'Écran' => '6.8" Dynamic AMOLED 2X 120Hz 2600 nits',
                    'Puce' => 'Snapdragon 8 Gen 3 for Galaxy',
                    'Appareil Photo' => '200 MP + 50 MP téléobjectif 5x + 12 MP',
                    'Stockage' => '512 Go',
                    'Batterie' => '5000 mAh — Charge rapide 45W',
                ],
                'colors' => [
                    ['name' => 'Titane Gris', 'hex' => '#787673', 'stock' => 10],
                    ['name' => 'Titane Noir', 'hex' => '#232323', 'stock' => 10],
                ],
            ],
            [
                'name' => 'MacBook Pro 14" M3',
                'slug' => 'macbook-pro-14-m3',
                'price' => 1250000,
                'stock' => 12,
                'brand' => 'Apple',
                'category' => 'ordinateurs-portables',
                'description' => 'Puissance phénoménale pour les créateurs et développeurs avec la puce M3, écran Liquid Retina XDR et 18h d\'autonomie.',
                'specifications' => [
                    'Écran' => '14.2" Liquid Retina XDR 120Hz Promotion',
                    'Processeur' => 'Puce Apple M3 Pro (11 cœurs CPU / 14 cœurs GPU)',
                    'RAM' => '18 Go unifiée',
                    'Stockage' => '512 Go SSD haute vitesse',
                    'Autonomie' => 'Jusqu\'à 18h d\'utilisation vidéo',
                ],
                'colors' => [
                    ['name' => 'Noir Sidéral', 'hex' => '#2e3034', 'stock' => 7],
                    ['name' => 'Argent', 'hex' => '#e2e4e6', 'stock' => 5],
                ],
            ],
            [
                'name' => 'Dell XPS 15 (2024)',
                'slug' => 'dell-xps-15-2024',
                'price' => 1100000,
                'stock' => 15,
                'brand' => 'Dell',
                'category' => 'ordinateurs-portables',
                'description' => 'Finition premium en aluminium usiné CNC et repose-poignets en fibre de carbone. Écran OLED tactile immersif.',
                'specifications' => [
                    'Écran' => '15.6" OLED 3.5K Touch InfinityEdge',
                    'Processeur' => 'Intel Core i7-13700H (14 cœurs)',
                    'RAM' => '32 Go DDR5 5200MHz',
                    'Stockage' => '1 To SSD NVMe PCIe 4.0',
                    'Carte Graphique' => 'NVIDIA GeForce RTX 4060 8 Go GDDR6',
                ],
                'colors' => [
                    ['name' => 'Platinum Silver', 'hex' => '#d4d8db', 'stock' => 15],
                ],
            ],
            [
                'name' => 'Sony WH-1000XM5',
                'slug' => 'sony-wh-1000xm5',
                'price' => 250000,
                'stock' => 25,
                'brand' => 'Sony',
                'category' => 'audio',
                'description' => 'La référence absolue du casque audio nomade à réduction de bruit active leader du marché avec processeur V1.',
                'specifications' => [
                    'Type' => 'Casque Circumaural Sans-fil Bluetooth 5.2 / LDAC',
                    'Réduction de Bruit' => 'Double processeur HD QN1 & V1 (ANC actif)',
                    'Autonomie' => '30 heures avec ANC / Charge rapide 3 min = 3h',
                    'Microphone' => '4 micros beamforming pour appels cristallins',
                ],
                'colors' => [
                    ['name' => 'Noir', 'hex' => '#1a1a1a', 'stock' => 15],
                    ['name' => 'Argent Platine', 'hex' => '#e5e3dc', 'stock' => 10],
                ],
            ],
            [
                'name' => 'AirPods Pro 2',
                'slug' => 'airpods-pro-2',
                'price' => 185000,
                'stock' => 30,
                'brand' => 'Apple',
                'category' => 'audio',
                'description' => 'Écouteurs sans-fil avec puce H2, réduction de bruit jusqu\'à 2x supérieure et audio spatial personnalisé.',
                'specifications' => [
                    'Puce' => 'Apple H2 dans les écouteurs, U1 dans le boîtier',
                    'Autonomie' => 'Jusqu\'à 6h par charge / 30h avec le boîtier MagSafe USB-C',
                    'Résistance' => 'Certification IP54 poussière et eau',
                ],
                'colors' => [
                    ['name' => 'Blanc', 'hex' => '#ffffff', 'stock' => 30],
                ],
            ],
            [
                'name' => 'HP EliteBook x360 1030 G5',
                'slug' => 'hp-elitebook-x360-1030-g5',
                'price' => 450000,
                'stock' => 10,
                'brand' => 'HP',
                'category' => 'ordinateurs-portables',
                'description' => 'PC convertible 2-en-1 ultra-fin pour professionnels exigeants avec écran tactile et sécurité HP Sure Start.',
                'specifications' => [
                    'Écran' => '13.3" Full HD IPS Tactile 360°',
                    'Processeur' => 'Intel Core i7 10ème Génération',
                    'RAM' => '16 Go DDR4',
                    'Stockage' => '512 Go SSD NVMe',
                ],
                'colors' => [
                    ['name' => 'Argent', 'hex' => '#c0c0c0', 'stock' => 10],
                ],
            ],
            [
                'name' => 'Google Pixel 8 Pro',
                'slug' => 'google-pixel-8-pro',
                'price' => 650000,
                'stock' => 14,
                'brand' => 'Google',
                'category' => 'telephones',
                'description' => 'L\'expérience Android pure avec Google Tensor G3, thermomètre intégré et les meilleures photos de nuit au monde.',
                'specifications' => [
                    'Écran' => '6.7" Super Actua OLED 1-120Hz',
                    'Puce' => 'Google Tensor G3 avec puce Titan M2',
                    'Appareil Photo' => '50 MP + 48 MP ultra grand-angle + 48 MP 5x',
                    'Stockage' => '128 Go',
                ],
                'colors' => [
                    ['name' => 'Baie (Bleu)', 'hex' => '#93b6d8', 'stock' => 7],
                    ['name' => 'Obsidienne (Noir)', 'hex' => '#212121', 'stock' => 7],
                ],
            ],
        ];

        foreach ($productsData as $data) {
            $prod = $this->productRepository->findOneBy(['slug' => $data['slug']]);
            $imageFilename = $data['slug'] . '.jpg';

            if (!$prod) {
                $prod = new Product();
                $prod->setName($data['name']);
                $prod->setSlug($data['slug']);
                $prod->setPrice($data['price']);
                $prod->setStock($data['stock']);
                $prod->setBrand($data['brand']);
                $prod->setDescription($data['description']);
                $prod->setSpecifications($data['specifications']);
                $prod->setCategory($categories[$data['category']]);
                $prod->setIsActive(true);
                $prod->setImageFilename($imageFilename);
                $prod->setSku(strtoupper(substr($data['brand'], 0, 3)) . '-' . rand(1000, 9999));

                $this->entityManager->persist($prod);

                foreach ($data['colors'] as $colorInfo) {
                    $color = new ProductColor();
                    $color->setName($colorInfo['name']);
                    $color->setHexCode($colorInfo['hex']);
                    $color->setStock($colorInfo['stock']);
                    $prod->addProductColor($color);
                    $this->entityManager->persist($color);
                }

                $io->writeln(sprintf('  + Produit créé : %s (%s XOF)', $data['name'], number_format($data['price'], 0, ',', ' ')));
            } else {
                // Mettre à jour l'image si non définie
                if (!$prod->getImageFilename()) {
                    $prod->setImageFilename($imageFilename);
                    $io->writeln(sprintf('  * Image attachée au produit existant : %s -> %s', $data['name'], $imageFilename));
                }
            }
        }
        $this->entityManager->flush();

        // 3. Codes Promo
        $couponsData = [
            [
                'code' => 'DAKAR10',
                'type' => Coupon::TYPE_PERCENTAGE,
                'value' => 10,
                'min' => 15000,
            ],
            [
                'code' => 'TECH5000',
                'type' => Coupon::TYPE_FIXED_XOF,
                'value' => 5000,
                'min' => 30000,
            ],
        ];

        foreach ($couponsData as $cpData) {
            $coupon = $this->couponRepository->findOneBy(['code' => $cpData['code']]);
            if (!$coupon) {
                $coupon = new Coupon();
                $coupon->setCode($cpData['code']);
                $coupon->setDiscountType($cpData['type']);
                $coupon->setDiscountValue($cpData['value']);
                $coupon->setMinOrderAmount($cpData['min']);
                $coupon->setMaxUses(500);
                $coupon->setIsActive(true);
                $this->entityManager->persist($coupon);
                $io->writeln(sprintf('  + Code Promo créé : %s', $cpData['code']));
            }
        }
        $this->entityManager->flush();

        // 4. Utilisateur Admin
        $admin = $this->userRepository->findOneBy(['email' => 'doumbiabecaye7@gmail.com']);
        if ($admin) {
            $roles = $admin->getRoles();
            if (!in_array('ROLE_ADMIN', $roles, true)) {
                $roles[] = 'ROLE_ADMIN';
                $admin->setRoles(array_unique($roles));
                $this->entityManager->flush();
                $io->writeln('  + Rôle ROLE_ADMIN garanti pour doumbiabecaye7@gmail.com');
            }
        } else {
            $admin = new User();
            $admin->setEmail('doumbiabecaye7@gmail.com');
            $admin->setRoles(['ROLE_ADMIN']);
            $admin->setFirstName('Becaye');
            $admin->setLastName('Doumbouya');
            $admin->setPassword($this->passwordHasher->hashPassword($admin, 'TechLink2026!'));
            $this->entityManager->persist($admin);
            $this->entityManager->flush();
            $io->writeln('  + Compte Administrateur créé (doumbiabecaye7@gmail.com / mot de passe par défaut : TechLink2026!)');
        }

        $io->success('Catalogue et données initiales restaurés avec succès !');

        return Command::SUCCESS;
    }
}
