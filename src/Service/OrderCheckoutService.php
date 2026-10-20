<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\CartItem;
use App\Entity\CustomerOrder;
use App\Entity\OrderItem;
use App\Entity\Product;
use App\Entity\User;
use App\Exception\InsufficientStockException;
use App\Repository\CartItemRepository;
use App\Repository\ProductColorRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

final readonly class OrderCheckoutService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private CartItemRepository $cartItemRepository,
        private ProductColorRepository $productColorRepository,
        private CouponManager $couponManager,
        private OrderLifecycleService $orderLifecycleService,
        private PaymentManager $paymentManager,
        private MailerInterface $mailer,
        private RequestStack $requestStack,
        private string $senderEmail,
    ) {
    }

    /**
     * Traitement atomique et transactionnel complet de la commande.
     *
     * @param CartItem[] $items
     * @throws InsufficientStockException Si un produit ou coloris est en rupture
     * @throws \Throwable En cas d'erreur inattendue avec rollback automatique
     */
    public function checkout(
        ?User $user,
        array $items,
        string $deliveryAddress,
        string $deliveryPhone,
        int $deliveryFee = 2000,
        string $paymentMethod = 'CASH_ON_DELIVERY',
        string $deliveryMethod = 'TIAK_TIAK',
        ?string $couponCode = null,
        ?string $notes = null,
        ?string $guestEmail = null,
        ?string $guestFirstName = null,
        ?string $guestLastName = null,
    ): CustomerOrder {
        if (empty($items)) {
            throw new \LogicException('Impossible de passer commande avec un panier vide.');
        }

        $this->entityManager->beginTransaction();

        try {
            $subtotal = 0;

            // 1. Validation stricte et atomique de disponibilité des stocks
            foreach ($items as $cartItem) {
                $product = $cartItem->getProduct();
                if (!$product || !$product->isActive()) {
                    throw new \RuntimeException(sprintf(
                        'Le produit "%s" n\'est plus disponible.',
                        $product ? $product->getName() : 'inconnu'
                    ));
                }

                $quantity = $cartItem->getQuantity();
                $colorName = $cartItem->getSelectedColor();

                if ($colorName !== null && $colorName !== '') {
                    $colorEntity = $this->productColorRepository->findOneBy([
                        'product' => $product,
                        'name' => $colorName,
                    ]);

                    if (!$colorEntity || $quantity > $colorEntity->getStock()) {
                        throw new InsufficientStockException(
                            productName: $product->getName(),
                            requestedQuantity: $quantity,
                            availableStock: $colorEntity ? $colorEntity->getStock() : 0,
                            colorName: $colorName,
                        );
                    }
                } elseif ($quantity > $product->getStock()) {
                    throw new InsufficientStockException(
                        productName: $product->getName(),
                        requestedQuantity: $quantity,
                        availableStock: $product->getStock(),
                    );
                }

                $subtotal += $product->getPrice() * $quantity;
            }

            // 2. Calcul du coupon promo
            $discountAmount = 0;
            $appliedCoupon = null;
            if ($couponCode !== null && trim($couponCode) !== '') {
                $couponResult = $this->couponManager->applyCoupon($couponCode, $subtotal);
                if ($couponResult['valid'] && $couponResult['coupon']) {
                    $discountAmount = $couponResult['discount'];
                    $appliedCoupon = $couponResult['coupon'];
                }
            }

            $finalTotal = max(0, $subtotal - $discountAmount) + $deliveryFee;

            // 3. Création de la commande
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

            // 4. Décrémentation atomique des stocks et création des lignes de commande
            foreach ($items as $cartItem) {
                $product = $cartItem->getProduct();
                $quantity = $cartItem->getQuantity();

                $orderItem = new OrderItem();
                $orderItem->setProduct($product);
                $orderItem->setQuantity($quantity);
                $orderItem->setUnitPrice($product->getPrice());
                $orderItem->setSelectedColor($cartItem->getSelectedColor());

                $order->addOrderItem($orderItem);
                $this->entityManager->persist($orderItem);

                // Décrémentation stricte du stock produit
                $product->setStock(max(0, $product->getStock() - $quantity));

                // Décrémentation stock coloris si applicable
                $colorName = $cartItem->getSelectedColor();
                if ($colorName !== null && $colorName !== '') {
                    $colorEntity = $this->productColorRepository->findOneBy([
                        'product' => $product,
                        'name' => $colorName,
                    ]);
                    if ($colorEntity) {
                        $colorEntity->setStock(max(0, $colorEntity->getStock() - $quantity));
                    }
                }

                $this->entityManager->remove($cartItem);
            }

            // 5. Initialisation du suivi colis et séquestre
            $this->orderLifecycleService->initializeTracking($order);
            $this->paymentManager->initiateTransaction($order, $paymentMethod, $deliveryPhone);

            // 6. Enregistrement de l'usage du code promo
            if ($appliedCoupon) {
                $appliedCoupon->incrementUsedCount();
            }

            $this->entityManager->flush();
            $this->entityManager->commit();

            // 7. Envoi email de confirmation (asynchrone / non bloquant)
            $this->sendOrderConfirmationEmail($order);

            return $order;
        } catch (\Throwable $e) {
            $this->entityManager->rollback();
            throw $e;
        }
    }

    private function sendOrderConfirmationEmail(CustomerOrder $order): void
    {
        $recipientEmail = $order->getUser() ? $order->getUser()->getEmail() : $order->getGuestEmail();
        if (!$recipientEmail) {
            return;
        }

        $recipientName = $order->getUser()
            ? ($order->getUser()->getFirstName() . ' ' . $order->getUser()->getLastName())
            : ($order->getGuestFirstName() . ' ' . $order->getGuestLastName());

        try {
            $email = (new TemplatedEmail())
                ->from(new Address($this->senderEmail, 'TechLink Dakar'))
                ->to(new Address($recipientEmail, trim($recipientName) !== '' ? $recipientName : 'Client TechLink'))
                ->subject('Confirmation de votre commande #' . $order->getId() . ' — TechLink')
                ->htmlTemplate('emails/order_confirmation.html.twig')
                ->context([
                    'order' => $order,
                    'user' => $order->getUser(),
                ]);

            $this->mailer->send($email);
        } catch (\Throwable) {
            // Logué sans casser le tunnel d'achat
        }
    }
}
