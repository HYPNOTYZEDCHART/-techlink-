<?php

namespace App\Tests;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class HomePageTest extends WebTestCase
{
    public function testHomePageLoadsSuccessfully(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'La tech pro');
    }

    public function testCatalogPageLoadsSuccessfully(): void
    {
        $client = static::createClient();
        $client->request('GET', '/produits');

        $this->assertResponseIsSuccessful();
    }

    public function testSitemapLoadsSuccessfully(): void
    {
        $client = static::createClient();
        $client->request('GET', '/sitemap.xml');

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('text/xml', $client->getResponse()->headers->get('Content-Type'));
        $this->assertStringContainsString('<urlset', $client->getResponse()->getContent());
    }
}