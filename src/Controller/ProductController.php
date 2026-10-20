<?php

namespace App\Controller;

use App\DTO\ProductFilterDto;
use App\Entity\Product;
use App\Enum\ProductCondition;
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
        $filter = ProductFilterDto::fromRequest($request);

        // Support rétrocompatible du paramètre ?recherche= si utilisé à la place de ?q=
        if ($filter->search === null && $request->query->has('recherche')) {
            $legacySearch = trim((string) $request->query->get('recherche'));
            if ($legacySearch !== '') {
                $filter = new ProductFilterDto(
                    search: $legacySearch,
                    categorySlugs: $filter->categorySlugs,
                    brands: $filter->brands,
                    priceMin: $filter->priceMin,
                    priceMax: $filter->priceMax,
                    condition: $filter->condition,
                    inStockOnly: $filter->inStockOnly,
                    sortBy: $filter->sortBy,
                    page: $filter->page,
                    limit: $filter->limit,
                );
            }
        }

        $selectedCategories = !empty($filter->categorySlugs)
            ? $categoryRepository->findBy(['slug' => $filter->categorySlugs])
            : [];

        $paginator = $productRepository->findFilteredPaginated($filter);
        $totalItems = count($paginator);
        $totalPages = (int) ceil($totalItems / $filter->limit);

        $wishlistProductIds = [];
        if ($this->getUser()) {
            foreach ($wishlistManager->getItems($this->getUser()) as $item) {
                $wishlistProductIds[] = $item->getProduct()->getId();
            }
        }

        return $this->render('product/list.html.twig', [
            'products' => $paginator,
            'selectedCategories' => $selectedCategories,
            'selectedBrands' => $filter->brands,
            'allBrands' => $productRepository->findDistinctBrands(),
            'allCategories' => $categoryRepository->findBy([], ['name' => 'ASC']),
            'currentPage' => $filter->page,
            'totalPages' => $totalPages,
            'totalItems' => $totalItems,
            'priceMin' => $filter->priceMin,
            'priceMax' => $filter->priceMax,
            'currentCondition' => $filter->condition?->value,
            'conditions' => ProductCondition::cases(),
            'inStockOnly' => $filter->inStockOnly,
            'currentSort' => $filter->sortBy,
            'searchQuery' => $filter->search,
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
    #[Route('/produits/recherche/live', name: 'app_product_search_live')]
    public function searchApi(Request $request, ProductRepository $productRepository): JsonResponse
    {
        $query = trim((string) $request->query->get('q', ''));

        if (mb_strlen($query) < 2) {
            return $this->json([]);
        }

        $products = $productRepository->searchSuggestions($query, 8);

        $results = array_map(fn (Product $product) => [
            'id' => $product->getId(),
            'name' => $product->getName(),
            'brand' => $product->getBrand(),
            'sku' => $product->getSku(),
            'price' => $product->getPrice(),
            'formattedPrice' => number_format($product->getPrice(), 0, ',', ' ') . ' XOF',
            'oldPrice' => $product->getOldPrice(),
            'formattedOldPrice' => $product->getOldPrice() ? number_format($product->getOldPrice(), 0, ',', ' ') . ' XOF' : null,
            'slug' => $product->getSlug(),
            'image' => $product->getImageFilename(),
            'inStock' => ($product->getStock() ?? 0) > 0,
            'stock' => $product->getStock() ?? 0,
            'isLowStock' => $product->isLowStock(),
            'condition' => [
                'value' => $product->getCondition()->value,
                'label' => $product->getCondition()->label(),
                'badgeClasses' => $product->getCondition()->badgeClasses(),
            ],
            'category' => $product->getCategory() ? $product->getCategory()->getName() : null,
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