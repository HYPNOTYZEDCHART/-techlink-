<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\CartItem;
use App\Entity\CustomerOrder;
use App\Entity\OrderItem;
use App\Entity\Product;
use App\Entity\User;
use App\Repository\CartItemRepository;
use App\Repository\ProductColorRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

class CartManager
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CartItemRepository $cartItemRepository,
        private readonly MailerInterface $mailer,
        private readonly string $senderEmail,
        private readonly RequestStack $requestStack,
        private readonly ProductColorRepository $productColorRepository,
        private readonly CouponManager $couponManager,
        private readonly OrderLifecycleService $orderLifecycleService,
        private readonly PaymentManager $paymentManager,
    ) {
    }

    public function addProduct(?User $user, Product $product, int $quantity = 1, ?string $selectedColor = null): void
    {
        $sessionId = null;
        if (!$user) {
            $session = $this->requestStack->getSession();
            if (!$session->isStarted()) {
                $session->start();
            }
            $session->set('_cart_initialized', true);
            $sessionId = $session->getId();
        }

        $criteria = ['product' => $product, 'selectedColor' => $selectedColor];
        if ($user) {
            $criteria['user'] = $user;
        } else {
            $criteria['sessionId'] = $sessionId;
        }

        $existingItem = $this->cartItemRepository->findOneBy($criteria);

        $currentQuantity = $existingItem ? $existingItem->getQuantity() : 0;
        $totalQuantity = $currentQuantity + $quantity;

        if ($selectedColor) {
            $colorEntity = $this->productColorRepository->findOneBy(['product' => $product, 'name' => $selectedColor]);
            if ($colorEntity && $totalQuantity > $colorEntity->getStock()) {
                throw new \LogicException('Stock insuffisant pour cette couleur (il en reste ' . $colorEntity->getStock() . ').');
            }
        } elseif ($totalQuantity > $product->getStock()) {
            throw new \LogicException('Stock insuffisant pour ce produit (il en reste ' . $product->getStock() . ').');
        }

        if ($existingItem) {
            $existingItem->setQuantity($totalQuantity);
        } else {
            $cartItem = new CartItem();
            $cartItem->setUser($user);
            $cartItem->setSessionId($sessionId);
            $cartItem->setProduct($product);
            $cartItem->setQuantity($quantity);
            $cartItem->setSelectedColor($selectedColor);
            $cartItem->setCreatedAt(new \DateTimeImmutable());
            $this->entityManager->persist($cartItem);
        }

        $this->entityManager->flush();
    }

    public function removeItem(CartItem $cartItem): void
    {
        $this->entityManager->remove($cartItem);
        $this->entityManager->flush();
    }

    public function updateQuantity(CartItem $cartItem, int $quantity): void
    {
        if ($quantity <= 0) {
            $this->removeItem($cartItem);
            return;
        }

        if ($quantity > $cartItem->getProduct()->getStock()) {
            throw new \LogicException('Stock insuffisant.');
        }

        $cartItem->setQuantity($quantity);
        $this->entityManager->flush();
    }

    /**
     * @return list<CartItem>
     */
    public function getItems(?User $user): array
    {
        if ($user) {
            return $this->cartItemRepository->findBy(['user' => $user]);
        }

        $session = $this->requestStack->getSession();
        $sessionId = $session->getId();

        if (!$sessionId) {
            return [];
        }

        return $this->cartItemRepository->findBy(['sessionId' => $sessionId]);
    }

    public function getTotalItemCount(?User $user): int
    {
        $items = $this->getItems($user);
        $count = 0;
        foreach ($items as $item) {
            $count += $item->getQuantity();
        }

        return $count;
    }

    public function getTotalPrice(?User $user): int
    {
        $items = $this->getItems($user);
        $total = 0;
        foreach ($items as $item) {
            $total += $item->getProduct()->getPrice() * $item->getQuantity();
        }

        return $total;
    }

    public function checkout(
        ?User $user,
        string $deliveryAddress,
        string $deliveryPhone,
        ?string $guestEmail = null,
        ?string $guestFirstName = null,
        ?string $guestLastName = null,
        int $deliveryFee = 0,
        string $paymentMethod = CustomerOrder::PAYMENT_METHOD_CASH,
        string $deliveryMethod = CustomerOrder::DELIVERY_METHOD_TIAK_TIAK,
        ?string $couponCode = null,
        ?string $notes = null
    ): CustomerOrder {
        $items = $this->getItems($user);

        if (empty($items)) {
            throw new \LogicException('Le panier est vide.');
        }

        $this->entityManager->beginTransaction();

        try {
            $subtotal = 0;

            // 1. Stock check & subtotal calculation
            foreach ($items as $cartItem) {
                $product = $cartItem->getProduct();
                $colorName = $cartItem->getSelectedColor();

                if ($colorName) {
                    $colorEntity = $this->productColorRepository->findOneBy(['product' => $product, 'name' => $colorName]);
                    if ($colorEntity && $cartItem->getQuantity() > $colorEntity->getStock()) {
                        throw new \RuntimeException(sprintf(
                            'Stock insuffisant pour "%s" en %s (il en reste %d).',
                            $product->getName(),
                            $colorName,
                            $colorEntity->getStock()
                        ));
                    }
                } elseif ($cartItem->getQuantity() > $product->getStock()) {
                    throw new \RuntimeException(sprintf(
                        'Stock insuffisant pour "%s" (il en reste %d).',
                        $product->getName(),
                        $product->getStock()
                    ));
                }

                $subtotal += $product->getPrice() * $cartItem->getQuantity();
            }

            // 2. Coupon calculation
            $discountAmount = 0;
            $appliedCoupon = null;
            if ($couponCode) {
                $couponResult = $this->couponManager->applyCoupon($couponCode, $subtotal);
                if ($couponResult['valid'] && $couponResult['coupon']) {
                    $discountAmount = $couponResult['discount'];
                    $appliedCoupon = $couponResult['coupon'];
                }
            }

            $finalTotal = max(0, $subtotal - $discountAmount) + $deliveryFee;

            // 3. Create Order
            $order = new CustomerOrder();
            $order->setUser($user);
            if (!$user) {
                $session = $this->requestStack->getSession();
                if ($session->isStarted()) {
                    $order->setSessionId($session->getId());
                }
                $order->setGuestEmail($guestEmail);
                $order->setGuestFirstName($guestFirstName);
                $order->setGuestLastName($guestLastName);
            }

            $order->setTotal($finalTotal);
            $order->setDeliveryFee($deliveryFee);
            $order->setDeliveryAddress($deliveryAddress);
            $order->setDeliveryPhone($deliveryPhone);
            $order->setPaymentMethod($paymentMethod);
            $order->setDeliveryMethod($deliveryMethod);
            $order->setCouponCode($appliedCoupon ? $appliedCoupon->getCode() : null);
            $order->setDiscountAmount($discountAmount);
            $order->setNotes($notes);
            $order->generateTrackingNumber();

            $this->entityManager->persist($order);

            // 4. Attach order items and decrement stock
            foreach ($items as $cartItem) {
                $product = $cartItem->getProduct();

                $orderItem = new OrderItem();
                $orderItem->setProduct($product);
                $orderItem->setQuantity($cartItem->getQuantity());
                $orderItem->setUnitPrice($product->getPrice());
                $orderItem->setSelectedColor($cartItem->getSelectedColor());

                $order->addOrderItem($orderItem);
                $this->entityManager->persist($orderItem);

                // Décrémentation stricte du stock
                $product->setStock(max(0, $product->getStock() - $cartItem->getQuantity()));

                $colorName = $cartItem->getSelectedColor();
                if ($colorName) {
                    $colorEntity = $this->productColorRepository->findOneBy(['product' => $product, 'name' => $colorName]);
                    if ($colorEntity) {
                        $colorEntity->setStock(max(0, $colorEntity->getStock() - $cartItem->getQuantity()));
                    }
                }

                $this->entityManager->remove($cartItem);
            }

            // 5. Initialize tracking timeline and payment transaction
            $this->orderLifecycleService->initializeTracking($order);
            $this->paymentManager->initiateTransaction($order, $paymentMethod, $deliveryPhone);

            // 6. Record coupon usage if applied
            if ($appliedCoupon) {
                $appliedCoupon->incrementUsedCount();
            }

            $this->entityManager->flush();
            $this->entityManager->commit();
        } catch (\Throwable $e) {
            $this->entityManager->rollback();
            throw $e;
        }

        try {
            $this->sendOrderConfirmationEmail($order);
        } catch (\Throwable $e) {
            // Email failure does not block confirmed transaction
        }

        return $order;
    }

    private function sendOrderConfirmationEmail(CustomerOrder $order): void
    {
        $to = $order->getUser() ? $order->getUser()->getEmail() : $order->getGuestEmail();

        if (!$to) {
            return;
        }

        $email = (new TemplatedEmail())
            ->from(new Address($this->senderEmail, 'TechLink'))
            ->to((string) $to)
            ->subject('Confirmation de ta commande n°' . $order->getTrackingNumber())
            ->htmlTemplate('emails/order_confirmation.html.twig')
            ->context([
                'order' => $order,
            ]);

        $this->mailer->send($email);
    }
}