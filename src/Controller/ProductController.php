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
use App\Service\ReviewManager;
use Symfony\Component\Security\Http\Attribute\IsGranted;

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
         

        $priceMin = $request->query->get('prix_min') ? (int) $request->query->get('prix_min') : null;
        $priceMax = $request->query->get('prix_max') ? (int) $request->query->get('prix_max') : null;

$page = max(1, (int) $request->query->get('page', 1));

if ($search) {
    $paginator = $productRepository->searchPaginated($search, $criteria, $page, 12, $priceMin, $priceMax);
} else {
    $paginator = $productRepository->findPaginated($criteria, $page, 12, $priceMin, $priceMax);
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
            'priceMin' => $priceMin,
            'priceMax' => $priceMax,
        ]);
    }

    #[Route('/produit/{slug}', name: 'app_product_show')]
public function show(#[MapEntity(mapping: ['slug' => 'slug'])] Product $product, \App\Service\WishlistManager $wishlistManager, ReviewManager $reviewManager): Response
{
    $isInWishlist = $this->getUser() ? $wishlistManager->isInWishlist($this->getUser(), $product) : false;
    $canReview = $this->getUser() ? !$reviewManager->hasAlreadyReviewed($this->getUser(), $product) : false;
    $averageRating = $reviewManager->getAverageRating($product);

    return $this->render('product/show.html.twig', [
        'product' => $product,
        'isInWishlist' => $isInWishlist,
        'canReview' => $canReview,
        'averageRating' => $averageRating,
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

    #[Route('/produit/{slug}/avis', name: 'app_product_review', methods: ['POST'])]
#[IsGranted('ROLE_USER')]
public function addReview(#[MapEntity(mapping: ['slug' => 'slug'])] Product $product, Request $request, ReviewManager $reviewManager): Response
{
    if ($reviewManager->hasAlreadyReviewed($this->getUser(), $product)) {
        $this->addFlash('error', 'Tu as déjà laissé un avis sur ce produit.');
        return $this->redirectToRoute('app_product_show', ['slug' => $product->getSlug()]);
    }

    $rating = (int) $request->request->get('rating', 5);
    $comment = $request->request->get('comment', '');

    $reviewManager->addReview($this->getUser(), $product, $rating, $comment);

    $this->addFlash('success', 'Merci pour ton avis !');

    return $this->redirectToRoute('app_product_show', ['slug' => $product->getSlug()]);
}
}