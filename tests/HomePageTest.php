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
}