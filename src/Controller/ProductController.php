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
use App\Service\WishlistManager;

final class ProductController extends AbstractController
{
    #[Route('/produits', name: 'app_product_index')]
    public function index(Request $request, ProductRepository $productRepository, CategoryRepository $categoryRepository, WishlistManager $wishlistManager): Response
    {
        $params = $request->query->all();
        
        $catParam = $params['categorie'] ?? [];
        $categorySlugs = is_array($catParam) ? $catParam : [$catParam];
        
        $brandParam = $params['marque'] ?? [];
        $brands = is_array($brandParam) ? $brandParam : [$brandParam];

        $search = $request->query->get('recherche');
        
        $selectedCategories = [];

        $criteria = ['isActive' => true];

        if (!empty($categorySlugs)) {
            $selectedCategories = $categoryRepository->findBy(['slug' => $categorySlugs]);
            if (!empty($selectedCategories)) {
                $criteria['category'] = $selectedCategories;
            }
        }

        if (!empty($brands)) {
            $criteria['brand'] = $brands;
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

        $wishlistProductIds = [];
        if ($this->getUser()) {
            foreach ($wishlistManager->getItems($this->getUser()) as $item) {
                $wishlistProductIds[] = $item->getProduct()->getId();
            }
        }

        return $this->render('product/list.html.twig', [
            'products' => $products,
            'selectedCategories' => $selectedCategories,
            'selectedBrands' => $brands,
            'allBrands' => $productRepository->findDistinctBrands(),
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'priceMin' => $priceMin,
            'priceMax' => $priceMax,
            'wishlistProductIds' => $wishlistProductIds,
        ]);
    }

    #[Route('/produit/{slug}', name: 'app_product_show')]
public function show(#[MapEntity(mapping: ['slug' => 'slug'])] Product $product, Request $request, \App\Service\WishlistManager $wishlistManager, ReviewManager $reviewManager): Response
{
    $isInWishlist = $this->getUser() ? $wishlistManager->isInWishlist($this->getUser(), $product) : false;
    $canReview = $this->getUser() ? !$reviewManager->hasAlreadyReviewed($this->getUser(), $product) : false;
    $averageRating = $reviewManager->getAverageRating($product);

    $reviewsPage = max(1, (int) $request->query->get('avis_page', 1));
    $reviewsPerPage = 5;
    $allReviews = $product->getReviews();
    $totalReviews = count($allReviews);
    $totalReviewPages = (int) ceil($totalReviews / $reviewsPerPage);

    $reviews = array_slice($allReviews->toArray(), ($reviewsPage - 1) * $reviewsPerPage, $reviewsPerPage);

    return $this->render('product/show.html.twig', [
        'product' => $product,
        'isInWishlist' => $isInWishlist,
        'canReview' => $canReview,
        'averageRating' => $averageRating,
        'reviews' => $reviews,
        'reviewsPage' => $reviewsPage,
        'totalReviewPages' => $totalReviewPages,
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

    #[Route('/avis/{id}/supprimer', name: 'app_review_delete', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function deleteReview(Review $review, Request $request, ReviewManager $reviewManager): Response
    {
        if (!$this->isCsrfTokenValid('delete_review_' . $review->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF invalide.');
        }

        $slug = $review->getProduct()->getSlug();
        $reviewManager->deleteReview($this->getUser(), $review);

        $this->addFlash('success', 'Ton avis a été supprimé.');

        return $this->redirectToRoute('app_product_show', ['slug' => $slug]);
    }
}