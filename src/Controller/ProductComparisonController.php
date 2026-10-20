<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Product;
use App\Repository\ProductRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/comparateur')]
final class ProductComparisonController extends AbstractController
{
    private const SESSION_KEY = '_compared_product_ids';
    private const MAX_PRODUCTS = 3;

    #[Route('', name: 'app_product_compare', methods: ['GET'])]
    public function index(Request $request, ProductRepository $productRepository): Response
    {
        $session = $request->getSession();
        /** @var list<int> $ids */
        $ids = $session->get(self::SESSION_KEY, []);

        $products = [];
        if (!empty($ids)) {
            $products = $productRepository->findBy(['id' => $ids, 'isActive' => true]);
        }

        // Extraction de toutes les spécifications uniques à travers tous les produits comparés
        $allSpecKeys = [];
        foreach ($products as $product) {
            foreach (array_keys($product->getSpecifications()) as $key) {
                if (!in_array($key, $allSpecKeys, true)) {
                    $allSpecKeys[] = $key;
                }
            }
        }
        sort($allSpecKeys);

        return $this->render('product/compare.html.twig', [
            'products' => $products,
            'specKeys' => $allSpecKeys,
        ]);
    }

    #[Route('/etat', name: 'app_product_compare_state', methods: ['GET'])]
    public function state(Request $request, ProductRepository $productRepository): JsonResponse
    {
        $session = $request->getSession();
        /** @var list<int> $ids */
        $ids = $session->get(self::SESSION_KEY, []);

        $products = [];
        if (!empty($ids)) {
            $entities = $productRepository->findBy(['id' => $ids, 'isActive' => true]);
            $products = array_map(fn (Product $p) => [
                'id' => $p->getId(),
                'name' => $p->getName(),
                'slug' => $p->getSlug(),
                'price' => number_format($p->getPrice(), 0, ',', ' ') . ' XOF',
                'image' => $p->getImageFilename(),
            ], $entities);
        }

        return $this->json([
            'count' => count($products),
            'products' => $products,
        ]);
    }

    #[Route('/ajouter/{id}', name: 'app_product_compare_add', methods: ['POST'])]
    public function add(int $id, Request $request, ProductRepository $productRepository): JsonResponse
    {
        $product = $productRepository->find($id);
        if (!$product || !$product->isActive()) {
            return $this->json(['success' => false, 'message' => 'Produit introuvable.'], 404);
        }

        $session = $request->getSession();
        /** @var list<int> $ids */
        $ids = $session->get(self::SESSION_KEY, []);

        if (in_array($id, $ids, true)) {
            return $this->json([
                'success' => true,
                'count' => count($ids),
                'message' => 'Produit déjà présent dans le comparatif.',
                'alreadyAdded' => true,
            ]);
        }

        if (count($ids) >= self::MAX_PRODUCTS) {
            return $this->json([
                'success' => false,
                'count' => count($ids),
                'message' => sprintf('Vous pouvez comparer un maximum de %d produits simultanément.', self::MAX_PRODUCTS),
            ], 400);
        }

        $ids[] = $id;
        $session->set(self::SESSION_KEY, $ids);

        return $this->json([
            'success' => true,
            'count' => count($ids),
            'message' => sprintf('"%s" ajouté au comparateur (%d/%d).', $product->getName(), count($ids), self::MAX_PRODUCTS),
            'product' => [
                'id' => $product->getId(),
                'name' => $product->getName(),
                'image' => $product->getImageFilename(),
                'price' => number_format($product->getPrice(), 0, ',', ' ') . ' XOF',
            ],
        ]);
    }

    #[Route('/retirer/{id}', name: 'app_product_compare_remove', methods: ['POST'])]
    public function remove(int $id, Request $request): JsonResponse
    {
        $session = $request->getSession();
        /** @var list<int> $ids */
        $ids = $session->get(self::SESSION_KEY, []);

        $ids = array_values(array_filter($ids, fn ($item) => (int) $item !== $id));
        $session->set(self::SESSION_KEY, $ids);

        return $this->json([
            'success' => true,
            'count' => count($ids),
            'message' => 'Produit retiré du comparateur.',
        ]);
    }

    #[Route('/vider', name: 'app_product_compare_clear', methods: ['POST'])]
    public function clear(Request $request): JsonResponse
    {
        $session = $request->getSession();
        $session->remove(self::SESSION_KEY);

        return $this->json([
            'success' => true,
            'count' => 0,
            'message' => 'Le comparateur a été vidé.',
        ]);
    }
}
