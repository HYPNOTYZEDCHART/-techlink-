<?php

namespace App\Controller;

use App\Entity\CartItem;
use App\Entity\Product;
use App\Service\CartManager;
use App\Entity\CustomerOrder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class CartController extends AbstractController
{
    #[Route('/panier', name: 'app_cart')]
    #[IsGranted('ROLE_USER')]
    public function index(CartManager $cartManager): Response
    {
        $items = $cartManager->getItems($this->getUser());
        $total = $cartManager->getTotalPrice($this->getUser());

        return $this->render('cart/index.html.twig', [
            'items' => $items,
            'total' => $total,
        ]);
    }

    #[Route('/panier/ajouter/{id}', name: 'app_cart_add', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function add(Product $product, Request $request, CartManager $cartManager): Response
    {
        $color = $request->request->get('color');
        $cartManager->addProduct($this->getUser(), $product, 1, $color);

        $this->addFlash('success', $product->getName() . ' ajouté au panier');

        return $this->redirectToRoute('app_product_show', ['slug' => $product->getSlug()]);
    }

    #[Route('/panier/modifier/{id}', name: 'app_cart_update', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function update(CartItem $cartItem, Request $request, CartManager $cartManager): Response
    {
        $quantity = (int) $request->request->get('quantity', 1);
        $cartManager->updateQuantity($cartItem, $quantity);

        return $this->redirectToRoute('app_cart');
    }

    #[Route('/panier/supprimer/{id}', name: 'app_cart_remove', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function remove(CartItem $cartItem, CartManager $cartManager): Response
    {
        $cartManager->removeItem($cartItem);

        return $this->redirectToRoute('app_cart');
    }

    #[Route('/panier/commander', name: 'app_cart_checkout', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function checkout(Request $request, CartManager $cartManager): Response
    {
        $items = $cartManager->getItems($this->getUser());

        if (empty($items)) {
            return $this->redirectToRoute('app_cart');
        }

        if ($request->isMethod('POST')) {
            $address = $request->request->get('address');
            $phone = $request->request->get('phone');

            $order = $cartManager->checkout($this->getUser(), $address, $phone);

            return $this->redirectToRoute('app_order_confirmation', ['id' => $order->getId()]);
        }

        return $this->render('cart/checkout.html.twig', [
            'items' => $items,
            'total' => $cartManager->getTotalPrice($this->getUser()),
        ]);
    }

    #[Route('/commande/{id}/confirmation', name: 'app_order_confirmation')]
    #[IsGranted('ROLE_USER')]
    public function confirmation(CustomerOrder $order): Response
    {
        if ($order->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        return $this->render('cart/confirmation.html.twig', [
            'order' => $order,
        ]);
    }
}