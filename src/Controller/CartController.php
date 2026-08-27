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
use App\Service\InvoiceGenerator;

final class CartController extends AbstractController
{
    #[Route('/panier', name: 'app_cart')]
    public function index(CartManager $cartManager, Request $request): Response
    {
        $session = $request->getSession();
        $sessionId = $session->getId();
        
        $items = $cartManager->getItems($this->getUser());
        $total = $cartManager->getTotalPrice($this->getUser());

        return $this->render('cart/index.html.twig', [
            'items' => $items,
            'total' => $total,
            'debug_session_id' => $sessionId,
        ]);
    }

    #[Route('/panier/ajouter/{id}', name: 'app_cart_add', methods: ['POST'])]
    public function add(Product $product, Request $request, CartManager $cartManager): Response
{
    $color = $request->request->get('color');
    $quantity = max(1, (int) $request->request->get('quantity', 1));

    if ($quantity > $product->getStock()) {
        $this->addFlash('error', 'Stock insuffisant : il ne reste que ' . $product->getStock() . ' unité(s).');
        return $this->redirectToRoute('app_product_show', ['slug' => $product->getSlug()]);
    }

    $cartManager->addProduct($this->getUser(), $product, $quantity, $color);

    if ($request->isXmlHttpRequest()) {
        return $this->json([
            'success' => true,
            'message' => $product->getName() . ' ajouté au panier',
            'cartCount' => $cartManager->getTotalItemCount($this->getUser()),
        ]);
    }

    $this->addFlash('success', $product->getName() . ' ajouté au panier');
    return $this->redirectToRoute('app_product_show', ['slug' => $product->getSlug()]);
}

    private function checkCartItemAccess(CartItem $cartItem, Request $request): void
    {
        $session = $request->getSession();
        $isOwner = false;
        
        if ($this->getUser() && $cartItem->getUser() === $this->getUser()) {
            $isOwner = true;
        } elseif (!$this->getUser() && !$cartItem->getUser() && $cartItem->getSessionId() === $session->getId()) {
            $isOwner = true;
        }

        if (!$isOwner) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas modifier ce panier.');
        }
    }

    #[Route('/panier/modifier/{id}', name: 'app_cart_update', methods: ['POST'])]
    public function update(CartItem $cartItem, Request $request, CartManager $cartManager): Response
    {
        if (!$this->isCsrfTokenValid('cart_update_' . $cartItem->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF invalide.');
        }

        $this->checkCartItemAccess($cartItem, $request);

        $quantity = (int) $request->request->get('quantity', 1);
        $cartManager->updateQuantity($cartItem, $quantity);

        return $this->redirectToRoute('app_cart');
    }

    #[Route('/panier/supprimer/{id}', name: 'app_cart_remove', methods: ['POST'])]
    public function remove(CartItem $cartItem, Request $request, CartManager $cartManager): Response
    {
        if (!$this->isCsrfTokenValid('cart_remove_' . $cartItem->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF invalide.');
        }

        $this->checkCartItemAccess($cartItem, $request);
        
        $cartManager->removeItem($cartItem);

        return $this->redirectToRoute('app_cart');
    }

    #[Route('/panier/commander', name: 'app_cart_checkout', methods: ['GET', 'POST'])]
    public function checkout(Request $request, CartManager $cartManager): Response
    {
        $items = $cartManager->getItems($this->getUser());

        if (empty($items)) {
            return $this->redirectToRoute('app_cart');
        }

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('cart_checkout', $request->request->get('_token'))) {
                throw $this->createAccessDeniedException('Token CSRF invalide.');
            }

            $address = $request->request->get('address');
            $phone = $request->request->get('phone');
            $deliveryFee = (int) $request->request->get('delivery_fee', 0);
            
            $guestEmail = $request->request->get('guest_email');
            $guestFirstName = $request->request->get('guest_first_name');
            $guestLastName = $request->request->get('guest_last_name');

            $order = $cartManager->checkout($this->getUser(), $address, $phone, $guestEmail, $guestFirstName, $guestLastName, $deliveryFee);

            return $this->redirectToRoute('app_order_confirmation', ['id' => $order->getId()]);
        }

        return $this->render('cart/checkout.html.twig', [
            'items' => $items,
            'total' => $cartManager->getTotalPrice($this->getUser()),
        ]);
    }

    #[Route('/commande/{id}/confirmation', name: 'app_order_confirmation')]
    public function confirmation(CustomerOrder $order, Request $request): Response
    {
        // Allow if user is order's user OR if the session ID matches
        $session = $request->getSession();
        $isGuestOwner = !$order->getUser() && $order->getSessionId() === $session->getId();
        
        if ($order->getUser() !== $this->getUser() && !$isGuestOwner) {
            throw $this->createAccessDeniedException();
        }

        return $this->render('cart/confirmation.html.twig', [
            'order' => $order,
        ]);
    }

    #[Route('/commande/{id}/facture', name: 'app_order_invoice')]
    public function invoice(CustomerOrder $order, InvoiceGenerator $invoiceGenerator, Request $request): Response
    {
        // Allow if user is order's user OR if the session ID matches
        $session = $request->getSession();
        $isGuestOwner = !$order->getUser() && $order->getSessionId() === $session->getId();
        
        if ($order->getUser() !== $this->getUser() && !$isGuestOwner) {
            throw $this->createAccessDeniedException();
        }

    $pdf = $invoiceGenerator->generate($order);

    return new Response($pdf, 200, [
        'Content-Type' => 'application/pdf',
        'Content-Disposition' => 'attachment; filename="facture-' . $order->getId() . '.pdf"',
    ]);
}
}