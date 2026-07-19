<?php

namespace App\Controller;

use App\Entity\Product;
use App\Repository\CategoryRepository;
use App\Repository\ProductRepository;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ProductController extends AbstractController
{
    #[Route('/produits', name: 'app_product_index')]
    public function index(Request $request, ProductRepository $productRepository, CategoryRepository $categoryRepository): Response
    {
        $categorySlug = $request->query->get('categorie');
        $brand = $request->query->get('marque');
        $search = $request->query->get('recherche');
        $selectedCategory = null;

        $criteria = ['isActive' => true];

        if ($categorySlug) {
            $selectedCategory = $categoryRepository->findOneBy(['slug' => $categorySlug]);
            if ($selectedCategory) {
                $criteria['category'] = $selectedCategory;
            }
        }

        if ($brand) {
            $criteria['brand'] = $brand;
        }

        $page = max(1, (int) $request->query->get('page', 1));

        if ($search) {
            $paginator = $productRepository->searchPaginated($search, $criteria, $page);
        } else {
            $paginator = $productRepository->findPaginated($criteria, $page);
        }

        $products = $paginator;
        $totalPages = (int) ceil(count($paginator) / 12);

        return $this->render('product/list.html.twig', [
            'products' => $products,
            'selectedCategory' => $selectedCategory,
            'selectedBrand' => $brand,
            'allBrands' => $productRepository->findDistinctBrands(),
            'currentPage' => $page,
            'totalPages' => $totalPages,
        ]);
    }

    #[Route('/produit/{slug}', name: 'app_product_show')]
    public function show(#[MapEntity(mapping: ['slug' => 'slug'])] Product $product): Response
    {
        return $this->render('product/show.html.twig', [
            'product' => $product,
        ]);
    }

    #[Route('/api/recherche-produits', name: 'app_api_product_search')]
    public function searchApi(Request $request, ProductRepository $productRepository): JsonResponse
    {
        $query = $request->query->get('q', '');

        if (strlen($query) < 2) {
            return $this->json([]);
        }

        $products = $productRepository->searchSuggestions($query);

        $results = array_map(fn ($product) => [
            'name' => $product->getName(),
            'brand' => $product->getBrand(),
            'price' => $product->getPrice(),
            'slug' => $product->getSlug(),
            'image' => $product->getImageFilename(),
        ], $products);

        return $this->json($results);
    }
}