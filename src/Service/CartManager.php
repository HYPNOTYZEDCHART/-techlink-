<?php

namespace App\Service;

use App\Entity\CartItem;
use App\Entity\Product;
use App\Entity\User;
use App\Repository\CartItemRepository;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\CustomerOrder;
use App\Entity\OrderItem;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\HttpFoundation\RequestStack;
use App\Repository\ProductColorRepository;

class CartManager
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private CartItemRepository $cartItemRepository,
        private MailerInterface $mailer,
        private string $senderEmail,
        private RequestStack $requestStack,
        private ProductColorRepository $productColorRepository,
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
            // Forcer Symfony à sauvegarder la session et envoyer le cookie PHPSESSID
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

        // Check color stock if applicable
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

    public function checkout(?User $user, string $deliveryAddress, string $deliveryPhone, ?string $guestEmail = null, ?string $guestFirstName = null, ?string $guestLastName = null, int $deliveryFee = 0): CustomerOrder
    {
        $items = $this->getItems($user);

        if (empty($items)) {
            throw new \LogicException('Le panier est vide.');
        }

        $this->entityManager->beginTransaction();

        try {
            foreach ($items as $cartItem) {
                $product = $cartItem->getProduct();
                $colorName = $cartItem->getSelectedColor();
                
                if ($colorName) {
                    $colorEntity = $this->productColorRepository->findOneBy(['product' => $product, 'name' => $colorName]);
                    if ($colorEntity && $cartItem->getQuantity() > $colorEntity->getStock()) {
                        throw new \RuntimeException('Stock insuffisant pour "' . $product->getName() . '" en ' . $colorName . ' (il en reste ' . $colorEntity->getStock() . ').');
                    }
                } elseif ($cartItem->getQuantity() > $product->getStock()) {
                    throw new \RuntimeException('Stock insuffisant pour "' . $product->getName() . '" (il en reste ' . $product->getStock() . ').');
                }
            }

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

            $order->setStatus('pending');
            $order->setTotal($this->getTotalPrice($user) + $deliveryFee);
            $order->setDeliveryFee($deliveryFee);
            $order->setCreatedAt(new \DateTimeImmutable());
            $order->setDeliveryAddress($deliveryAddress);
            $order->setDeliveryPhone($deliveryPhone);

            $this->entityManager->persist($order);

            foreach ($items as $cartItem) {
                $product = $cartItem->getProduct();

                $orderItem = new OrderItem();
                $orderItem->setProduct($product);
                $orderItem->setQuantity($cartItem->getQuantity());
                $orderItem->setUnitPrice($product->getPrice());
                $orderItem->setSelectedColor($cartItem->getSelectedColor());

                $order->addOrderItem($orderItem);
                $this->entityManager->persist($orderItem);

                // Décrémentation du stock
                $product->setStock($product->getStock() - $cartItem->getQuantity());
                
                $colorName = $cartItem->getSelectedColor();
                if ($colorName) {
                    $colorEntity = $this->productColorRepository->findOneBy(['product' => $product, 'name' => $colorName]);
                    if ($colorEntity) {
                        $colorEntity->setStock($colorEntity->getStock() - $cartItem->getQuantity());
                    }
                }

                $this->entityManager->remove($cartItem);
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
        // La commande est déjà validée en base ; on ne bloque pas si l'email échoue
    }

    return $order;
}

    private function sendOrderConfirmationEmail(CustomerOrder $order): void
    {
        $to = $order->getUser() ? $order->getUser()->getEmail() : $order->getGuestEmail();
        
        if (!$to) {
            return; // No email to send to
        }

        $email = (new TemplatedEmail())
            ->from(new Address($this->senderEmail, 'TechLink'))
            ->to((string) $to)
            ->subject('Confirmation de ta commande n°' . $order->getId())
            ->htmlTemplate('emails/order_confirmation.html.twig')
            ->context([
                'order' => $order,
            ]);

        $this->mailer->send($email);
    }
}