<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\CartItem;
use App\Entity\CustomerOrder;
use App\Entity\Product;
use App\Repository\CustomerOrderRepository;
use App\Service\CartManager;
use App\Service\CouponManager;
use App\Service\InvoiceGenerator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

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

        if (!$this->isCsrfTokenValid('cart_add_' . $product->getId(), $request->request->get('_token'))) {
            if ($request->isXmlHttpRequest()) {
                return $this->json(['error' => 'Token CSRF invalide.'], 403);
            }
            throw $this->createAccessDeniedException('Token CSRF invalide.');
        }

        if ($quantity > $product->getStock()) {
            $this->addFlash('error', 'Stock insuffisant : il ne reste que ' . $product->getStock() . ' unité(s).');
            return $this->redirectToRoute('app_product_show', ['slug' => $product->getSlug()]);
        }

        try {
            $cartManager->addProduct($this->getUser(), $product, $quantity, $color);
        } catch (\LogicException $e) {
            if ($request->isXmlHttpRequest()) {
                return $this->json(['error' => $e->getMessage()], 400);
            }
            $this->addFlash('error', $e->getMessage());
            return $this->redirectToRoute('app_product_show', ['slug' => $product->getSlug()]);
        }

        if ($request->isXmlHttpRequest()) {
            return $this->json([
                'success' => true,
                'cartCount' => $cartManager->getTotalItemCount($this->getUser()),
                'message' => sprintf('"%s" a été ajouté à votre panier.', $product->getName()),
            ]);
        }

        $this->addFlash('success', 'Produit ajouté au panier.');
        return $this->redirectToRoute('app_cart');
    }

    #[Route('/panier/etat', name: 'app_cart_state', methods: ['GET'])]
    public function state(CartManager $cartManager, CsrfTokenManagerInterface $csrfTokenManager): JsonResponse
    {
        $items = $cartManager->getItems($this->getUser());
        $total = $cartManager->getTotalPrice($this->getUser());
        $count = $cartManager->getTotalItemCount($this->getUser());
        $freeShippingThreshold = 500000; // 500 000 XOF pour livraison offerte
        $remaining = max(0, $freeShippingThreshold - $total);
        $percent = $total >= $freeShippingThreshold ? 100 : (int) round(($total / $freeShippingThreshold) * 100);

        $itemsData = array_map(function (CartItem $item) use ($csrfTokenManager) {
            $product = $item->getProduct();
            $itemTotal = $product->getPrice() * $item->getQuantity();

            return [
                'id' => $item->getId(),
                'productId' => $product->getId(),
                'name' => $product->getName(),
                'slug' => $product->getSlug(),
                'brand' => $product->getBrand(),
                'price' => $product->getPrice(),
                'formattedPrice' => number_format($product->getPrice(), 0, ',', ' ') . ' XOF',
                'quantity' => $item->getQuantity(),
                'selectedColor' => $item->getSelectedColor(),
                'image' => $product->getImageFilename(),
                'stock' => $product->getStock(),
                'itemTotal' => $itemTotal,
                'formattedItemTotal' => number_format($itemTotal, 0, ',', ' ') . ' XOF',
                'updateToken' => $csrfTokenManager->getToken('cart_update_' . $item->getId())->getValue(),
                'removeToken' => $csrfTokenManager->getToken('cart_remove_' . $item->getId())->getValue(),
            ];
        }, $items);

        return $this->json([
            'count' => $count,
            'total' => $total,
            'formattedTotal' => number_format($total, 0, ',', ' ') . ' XOF',
            'freeShippingThreshold' => $freeShippingThreshold,
            'freeShippingRemaining' => $remaining,
            'freeShippingRemainingFormatted' => number_format($remaining, 0, ',', ' ') . ' XOF',
            'freeShippingPercent' => $percent,
            'items' => $itemsData,
        ]);
    }

    #[Route('/panier/modifier/{id}', name: 'app_cart_update', methods: ['POST'])]
    public function update(CartItem $cartItem, Request $request, CartManager $cartManager): Response
    {
        if (!$this->isCsrfTokenValid('cart_update_' . $cartItem->getId(), $request->request->get('_token'))) {
            if ($request->isXmlHttpRequest() || $request->headers->get('Accept') === 'application/json') {
                return $this->json(['error' => 'Token CSRF invalide.'], 403);
            }
            throw $this->createAccessDeniedException('Token CSRF invalide.');
        }

        $this->checkCartItemAccess($cartItem, $request);

        $quantity = (int) $request->request->get('quantity', 1);

        try {
            $cartManager->updateQuantity($cartItem, $quantity);
        } catch (\LogicException $e) {
            if ($request->isXmlHttpRequest() || $request->headers->get('Accept') === 'application/json') {
                return $this->json(['error' => $e->getMessage()], 400);
            }
            $this->addFlash('error', $e->getMessage());
            return $this->redirectToRoute('app_cart');
        }

        if ($request->isXmlHttpRequest() || $request->headers->get('Accept') === 'application/json') {
            return $this->json([
                'success' => true,
                'cartCount' => $cartManager->getTotalItemCount($this->getUser()),
                'total' => $cartManager->getTotalPrice($this->getUser()),
                'formattedTotal' => number_format($cartManager->getTotalPrice($this->getUser()), 0, ',', ' ') . ' XOF',
            ]);
        }

        return $this->redirectToRoute('app_cart');
    }

    #[Route('/panier/supprimer/{id}', name: 'app_cart_remove', methods: ['POST'])]
    public function remove(CartItem $cartItem, Request $request, CartManager $cartManager): Response
    {
        if (!$this->isCsrfTokenValid('cart_remove_' . $cartItem->getId(), $request->request->get('_token'))) {
            if ($request->isXmlHttpRequest() || $request->headers->get('Accept') === 'application/json') {
                return $this->json(['error' => 'Token CSRF invalide.'], 403);
            }
            throw $this->createAccessDeniedException('Token CSRF invalide.');
        }

        $this->checkCartItemAccess($cartItem, $request);

        $cartManager->removeItem($cartItem);

        if ($request->isXmlHttpRequest() || $request->headers->get('Accept') === 'application/json') {
            return $this->json([
                'success' => true,
                'cartCount' => $cartManager->getTotalItemCount($this->getUser()),
                'total' => $cartManager->getTotalPrice($this->getUser()),
                'formattedTotal' => number_format($cartManager->getTotalPrice($this->getUser()), 0, ',', ' ') . ' XOF',
            ]);
        }

        return $this->redirectToRoute('app_cart');
    }

    #[Route('/panier/coupon/verifier', name: 'app_cart_coupon_check', methods: ['POST'])]
    public function checkCoupon(Request $request, CartManager $cartManager, CouponManager $couponManager): JsonResponse
    {
        $code = (string) $request->request->get('code', '');
        $subtotal = $cartManager->getTotalPrice($this->getUser());

        $result = $couponManager->applyCoupon($code, $subtotal);

        return $this->json([
            'valid' => $result['valid'],
            'discount' => $result['discount'],
            'message' => $result['message'],
            'code' => strtoupper(trim($code)),
        ]);
    }

    #[Route('/panier/commander', name: 'app_cart_checkout', methods: ['GET', 'POST'])]
    public function checkout(Request $request, CartManager $cartManager): Response
    {
        $items = $cartManager->getItems($this->getUser());

        if (empty($items)) {
            return $this->redirectToRoute('app_cart');
        }

        $subtotal = $cartManager->getTotalPrice($this->getUser());

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('cart_checkout', $request->request->get('_token'))) {
                throw $this->createAccessDeniedException('Token CSRF invalide.');
            }

            $deliveryMethod = (string) $request->request->get('delivery_method', CustomerOrder::DELIVERY_METHOD_TIAK_TIAK);
            $paymentMethod = (string) $request->request->get('payment_method', CustomerOrder::PAYMENT_METHOD_CASH);
            $phone = (string) $request->request->get('phone');
            $address = (string) $request->request->get('address', '');
            $notes = $request->request->get('notes');
            $couponCode = $request->request->get('coupon_code');

            $deliveryFee = 0;
            if ($deliveryMethod === CustomerOrder::DELIVERY_METHOD_TIAK_TIAK) {
                $deliveryFee = (int) $request->request->get('delivery_fee', 0);
                $allowedFees = [1500, 2000, 2500, 3000, 5000];
                if (!in_array($deliveryFee, $allowedFees, true)) {
                    throw $this->createAccessDeniedException('Zone de livraison invalide.');
                }
            } else {
                $address = 'Retrait en boutique — TechLink Dakar Plateau';
            }

            $guestEmail = $request->request->get('guest_email');
            $guestFirstName = $request->request->get('guest_first_name');
            $guestLastName = $request->request->get('guest_last_name');

            try {
                $order = $cartManager->checkout(
                    user: $this->getUser(),
                    deliveryAddress: $address,
                    deliveryPhone: $phone,
                    guestEmail: $guestEmail ? (string) $guestEmail : null,
                    guestFirstName: $guestFirstName ? (string) $guestFirstName : null,
                    guestLastName: $guestLastName ? (string) $guestLastName : null,
                    deliveryFee: $deliveryFee,
                    paymentMethod: $paymentMethod,
                    deliveryMethod: $deliveryMethod,
                    couponCode: $couponCode ? (string) $couponCode : null,
                    notes: $notes ? (string) $notes : null
                );
            } catch (\RuntimeException $e) {
                $this->addFlash('error', $e->getMessage());
                return $this->redirectToRoute('app_cart_checkout');
            }

            return $this->redirectToRoute('app_order_confirmation', ['id' => $order->getId()]);
        }

        return $this->render('cart/checkout.html.twig', [
            'items' => $items,
            'total' => $subtotal,
        ]);
    }

    #[Route('/commande/{id}/confirmation', name: 'app_order_confirmation')]
    public function confirmation(CustomerOrder $order, Request $request): Response
    {
        $session = $request->getSession();
        $isGuestOwner = !$order->getUser() && $order->getSessionId() === $session->getId();

        if ($order->getUser() !== $this->getUser() && !$isGuestOwner) {
            throw $this->createAccessDeniedException();
        }

        return $this->render('cart/confirmation.html.twig', [
            'order' => $order,
        ]);
    }

    #[Route('/suivi/{trackingNumber}', name: 'app_order_track', methods: ['GET'])]
    public function track(string $trackingNumber, CustomerOrderRepository $orderRepository): Response
    {
        $order = $orderRepository->findOneBy(['trackingNumber' => strtoupper(trim($trackingNumber))]);

        if (!$order) {
            $this->addFlash('error', 'Numéro de suivi introuvable. Veuillez vérifier votre référence de commande.');
            return $this->redirectToRoute('app_home');
        }

        return $this->render('cart/track.html.twig', [
            'order' => $order,
        ]);
    }

    #[Route('/commande/{id}/facture', name: 'app_order_invoice')]
    public function invoice(CustomerOrder $order, InvoiceGenerator $invoiceGenerator, Request $request): Response
    {
        $session = $request->getSession();
        $isGuestOwner = !$order->getUser() && $order->getSessionId() === $session->getId();

        if ($order->getUser() !== $this->getUser() && !$isGuestOwner) {
            throw $this->createAccessDeniedException();
        }

        $pdf = $invoiceGenerator->generate($order);

        return new Response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="facture-' . ($order->getTrackingNumber() ?? (string) $order->getId()) . '.pdf"',
        ]);
    }

    private function checkCartItemAccess(CartItem $cartItem, Request $request): void
    {
        $user = $this->getUser();
        if ($user) {
            if ($cartItem->getUser() !== $user) {
                throw $this->createAccessDeniedException('Accès refusé à cet article.');
            }
        } else {
            $sessionId = $request->getSession()->getId();
            if ($cartItem->getSessionId() !== $sessionId) {
                throw $this->createAccessDeniedException('Accès refusé à cet article.');
            }
        }
    }
}