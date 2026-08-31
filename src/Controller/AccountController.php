<?php

namespace App\Controller;

use App\Repository\CustomerOrderRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use App\Entity\Product;
use App\Service\WishlistManager;

final class AccountController extends AbstractController
{
    #[Route('/mon-compte', name: 'app_account')]
    #[IsGranted('ROLE_USER')]
    public function index(): Response
    {
        return $this->render('account/index.html.twig');
    }

    #[Route('/mon-compte/commandes', name: 'app_account_orders')]
    #[IsGranted('ROLE_USER')]
    public function orders(CustomerOrderRepository $orderRepository): Response
    {
        $orders = $orderRepository->findBy(['user' => $this->getUser()], ['createdAt' => 'DESC']);

        return $this->render('account/orders.html.twig', [
            'orders' => $orders,
        ]);
    }

    #[Route('/mes-favoris', name: 'app_wishlist')]
#[IsGranted('ROLE_USER')]
public function wishlist(WishlistManager $wishlistManager): Response
{
    $items = $wishlistManager->getItems($this->getUser());

    return $this->render('account/wishlist.html.twig', [
        'items' => $items,
    ]);
}

#[Route('/favoris/toggle/{id}', name: 'app_wishlist_toggle', methods: ['POST'])]
#[IsGranted('ROLE_USER')]
public function toggleWishlist(Product $product, WishlistManager $wishlistManager, \Symfony\Component\HttpFoundation\Request $request): Response
{
    if (!$this->isCsrfTokenValid('wishlist_toggle_' . $product->getId(), $request->headers->get('X-CSRF-TOKEN'))) {
        return $this->json(['error' => 'Token CSRF invalide.'], 403);
    }

    $added = $wishlistManager->toggle($this->getUser(), $product);

    return $this->json(['added' => $added]);
}
}