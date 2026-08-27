<?php

namespace App\Controller;

use App\Repository\ProductRepository;
use App\Service\WishlistManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(ProductRepository $productRepository, WishlistManager $wishlistManager): Response
    {
        $featuredProducts = $productRepository->findBy(
            ['isActive' => true],
            ['createdAt' => 'DESC'],
            4
        );

        $wishlistProductIds = [];
        if ($this->getUser()) {
            foreach ($wishlistManager->getItems($this->getUser()) as $item) {
                $wishlistProductIds[] = $item->getProduct()->getId();
            }
        }

        return $this->render('home/index.html.twig', [
            'featuredProducts' => $featuredProducts,
            'wishlistProductIds' => $wishlistProductIds,
        ]);
    }
}