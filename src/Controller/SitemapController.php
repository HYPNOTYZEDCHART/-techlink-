<?php

namespace App\Controller;

use App\Repository\CategoryRepository;
use App\Repository\ProductRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class SitemapController extends AbstractController
{
    #[Route('/sitemap.xml', name: 'app_sitemap')]
    public function index(
        ProductRepository $productRepository,
        CategoryRepository $categoryRepository
    ): Response {
        $urls = [];

        // 1. Pages statiques
        $urls[] = ['loc' => $this->generateUrl('app_home', [], UrlGeneratorInterface::ABSOLUTE_URL),          'changefreq' => 'daily',   'priority' => '1.0'];
        $urls[] = ['loc' => $this->generateUrl('app_product_index', [], UrlGeneratorInterface::ABSOLUTE_URL), 'changefreq' => 'daily',   'priority' => '0.9'];
        $urls[] = ['loc' => $this->generateUrl('app_contact', [], UrlGeneratorInterface::ABSOLUTE_URL),       'changefreq' => 'monthly', 'priority' => '0.5'];
        $urls[] = ['loc' => $this->generateUrl('app_terms', [], UrlGeneratorInterface::ABSOLUTE_URL),         'changefreq' => 'yearly',  'priority' => '0.3'];
        $urls[] = ['loc' => $this->generateUrl('app_privacy', [], UrlGeneratorInterface::ABSOLUTE_URL),       'changefreq' => 'yearly',  'priority' => '0.3'];

        // 2. Produits actifs ET en stock (slugs scalaires uniquement — aucune entité complète chargée)
        foreach ($productRepository->findSlugsForSitemap() as $slug) {
            $urls[] = [
                'loc'        => $this->generateUrl('app_product_show', ['slug' => $slug], UrlGeneratorInterface::ABSOLUTE_URL),
                'changefreq' => 'weekly',
                'priority'   => '0.8',
            ];
        }

        // 3. Catégories (slugs scalaires uniquement — aucune collection $products chargée)
        foreach ($categoryRepository->findSlugsForSitemap() as $slug) {
            $urls[] = [
                'loc'        => $this->generateUrl('app_product_index', ['categorie' => $slug], UrlGeneratorInterface::ABSOLUTE_URL),
                'changefreq' => 'weekly',
                'priority'   => '0.7',
            ];
        }

        $response = new Response(
            $this->renderView('sitemap/index.html.twig', ['urls' => $urls]),
            Response::HTTP_OK
        );
        $response->headers->set('Content-Type', 'text/xml; charset=UTF-8');

        return $response;
    }
}
