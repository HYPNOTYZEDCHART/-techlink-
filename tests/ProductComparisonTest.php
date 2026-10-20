<?php

declare(strict_types=1);

namespace App\Tests;

use App\Entity\Category;
use App\Entity\Product;
use App\Enum\ProductCondition;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ProductComparisonTest extends WebTestCase
{
    private function createSampleProduct(EntityManagerInterface $em, string $name, string $slug, int $price): Product
    {
        $category = $em->getRepository(Category::class)->findOneBy([]) ?? new Category();
        if (!$category->getId()) {
            $category->setName('Laptops Test');
            $category->setSlug('laptops-test');
            $em->persist($category);
        }

        $product = new Product();
        $product->setName($name);
        $product->setSlug($slug);
        $product->setSku('SKU-TEST-' . uniqid());
        $product->setBrand('Apple');
        $product->setDescription('Description test matériel haute performance.');
        $product->setPrice($price);
        $product->setStock(10);
        $product->setIsActive(true);
        $product->setCategory($category);
        $product->setCondition(ProductCondition::NEW_SEALED);
        $product->setWarrantyMonths(24);
        $product->setLowStockThreshold(3);
        $product->setSpecifications(['Processeur' => 'Apple M3 Pro', 'RAM' => '18 Go']);

        $em->persist($product);
        $em->flush();

        return $product;
    }

    public function testComparisonPageLoads(): void
    {
        $client = static::createClient();
        $client->request('GET', '/comparateur');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Comparateur Haute Précision');
    }

    public function testComparisonStateInitiallyEmpty(): void
    {
        $client = static::createClient();
        $client->request('GET', '/comparateur/etat');

        $this->assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent() ?: '{}', true);

        $this->assertSame(0, $data['count']);
        $this->assertEmpty($data['products']);
    }

    public function testAddAndRemoveFromComparison(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get('doctrine.orm.entity_manager');

        $product = $this->createSampleProduct($em, 'MacBook Test Pro', 'macbook-test-pro-' . uniqid(), 1450000);

        // 1. Ajouter
        $client->request('POST', '/comparateur/ajouter/' . $product->getId());
        $this->assertResponseIsSuccessful();
        $addData = json_decode($client->getResponse()->getContent() ?: '{}', true);
        $this->assertTrue($addData['success']);
        $this->assertSame(1, $addData['count']);

        // 2. Vérifier l'état
        $client->request('GET', '/comparateur/etat');
        $this->assertResponseIsSuccessful();
        $stateData = json_decode($client->getResponse()->getContent() ?: '{}', true);
        $this->assertSame(1, $stateData['count']);
        $this->assertSame($product->getId(), $stateData['products'][0]['id']);

        // 3. Retirer
        $client->request('POST', '/comparateur/retirer/' . $product->getId());
        $this->assertResponseIsSuccessful();
        $removeData = json_decode($client->getResponse()->getContent() ?: '{}', true);
        $this->assertTrue($removeData['success']);
        $this->assertSame(0, $removeData['count']);
    }

    public function testLiveSearchEndpoint(): void
    {
        $client = static::createClient();
        $client->request('GET', '/produits/recherche/live?q=test');

        $this->assertResponseIsSuccessful();
        $this->assertJson($client->getResponse()->getContent() ?: '');
    }
}
