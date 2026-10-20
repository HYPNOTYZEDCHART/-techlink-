<?php

declare(strict_types=1);

namespace App\Tests;

use App\Entity\Category;
use App\Entity\Product;
use App\Enum\ProductCondition;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

class CartDrawerTest extends WebTestCase
{
    private function createSampleProduct(EntityManagerInterface $em, string $name, string $slug, int $price): Product
    {
        $category = $em->getRepository(Category::class)->findOneBy([]) ?? new Category();
        if (!$category->getId()) {
            $category->setName('Laptops Drawer Test');
            $category->setSlug('laptops-drawer-test');
            $em->persist($category);
        }

        $product = new Product();
        $product->setName($name);
        $product->setSlug($slug);
        $product->setSku('SKU-DRAWER-' . uniqid());
        $product->setBrand('Dell');
        $product->setDescription('Station de travail ultra-puissante pour développeurs et créatifs.');
        $product->setPrice($price);
        $product->setStock(10);
        $product->setIsActive(true);
        $product->setCategory($category);
        $product->setCondition(ProductCondition::NEW_SEALED);
        $product->setWarrantyMonths(24);
        $product->setLowStockThreshold(3);
        $product->setSpecifications(['Processeur' => 'Intel Core i9-14900H', 'RAM' => '32 Go DDR5', 'Stockage' => '1 To SSD NVMe']);

        $em->persist($product);
        $em->flush();

        return $product;
    }

    public function testCartStateInitiallyEmpty(): void
    {
        $client = static::createClient();
        $client->request('GET', '/panier/etat');

        $this->assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent() ?: '{}', true);

        $this->assertSame(0, $data['count']);
        $this->assertSame(0, $data['total']);
        $this->assertEmpty($data['items']);
    }

    public function testProductShowPageLoads(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get('doctrine.orm.entity_manager');

        $product = $this->createSampleProduct($em, 'Dell XPS 16 Test', 'dell-xps-16-test-' . uniqid(), 1850000);

        $client->request('GET', '/produit/' . $product->getSlug());
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Dell XPS 16 Test');
    }

    public function testCartAddAndStateWorkflow(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get('doctrine.orm.entity_manager');

        $product = $this->createSampleProduct($em, 'Dell Precision 7780 Test', 'dell-precision-7780-test-' . uniqid(), 2200000);

        // 1. Charger la page produit pour initialiser la session et récupérer le vrai token CSRF du formulaire
        $crawler = $client->request('GET', '/produit/' . $product->getSlug());
        $token = $crawler->filter('form input[name="_token"]')->attr('value');

        // 2. Ajouter au panier en AJAX
        $client->request(
            'POST',
            '/panier/ajouter/' . $product->getId(),
            [
                '_token' => $token,
                'quantity' => 1,
            ],
            [],
            ['HTTP_X-Requested-With' => 'XMLHttpRequest']
        );

        $this->assertResponseIsSuccessful();
        $addData = json_decode($client->getResponse()->getContent() ?: '{}', true);
        $this->assertTrue($addData['success']);
        $this->assertSame(1, $addData['cartCount']);

        // 2. Vérifier l'état du drawer
        $client->request('GET', '/panier/etat');
        $this->assertResponseIsSuccessful();
        $stateData = json_decode($client->getResponse()->getContent() ?: '{}', true);

        $this->assertSame(1, $stateData['count']);
        $this->assertSame(2200000, $stateData['total']);
        $this->assertCount(1, $stateData['items']);
        $this->assertSame('Dell Precision 7780 Test', $stateData['items'][0]['name']);
        $this->assertNotEmpty($stateData['items'][0]['updateToken']);
        $this->assertNotEmpty($stateData['items'][0]['removeToken']);

        $cartItemId = $stateData['items'][0]['id'];
        $updateToken = $stateData['items'][0]['updateToken'];
        $removeToken = $stateData['items'][0]['removeToken'];

        // 3. Modifier la quantité en AJAX
        $client->request(
            'POST',
            '/panier/modifier/' . $cartItemId,
            [
                '_token' => $updateToken,
                'quantity' => 2,
            ],
            [],
            ['HTTP_X-Requested-With' => 'XMLHttpRequest']
        );
        $this->assertResponseIsSuccessful();
        $updateData = json_decode($client->getResponse()->getContent() ?: '{}', true);
        $this->assertTrue($updateData['success']);
        $this->assertSame(2, $updateData['cartCount']);

        // 4. Supprimer l'article en AJAX
        $client->request(
            'POST',
            '/panier/supprimer/' . $cartItemId,
            [
                '_token' => $removeToken,
            ],
            [],
            ['HTTP_X-Requested-With' => 'XMLHttpRequest']
        );
        $this->assertResponseIsSuccessful();
        $removeData = json_decode($client->getResponse()->getContent() ?: '{}', true);
        $this->assertTrue($removeData['success']);
        $this->assertSame(0, $removeData['cartCount']);
    }
}
