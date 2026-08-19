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

class CartManager
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private CartItemRepository $cartItemRepository,
        private MailerInterface $mailer,
        private string $senderEmail,
    ) {
    }

    public function addProduct(User $user, Product $product, int $quantity = 1, ?string $selectedColor = null): void
    {
        $existingItem = $this->cartItemRepository->findOneBy([
            'user' => $user,
            'product' => $product,
            'selectedColor' => $selectedColor,
        ]);

        $currentQuantity = $existingItem ? $existingItem->getQuantity() : 0;
        if ($currentQuantity + $quantity > $product->getStock()) {
            throw new \LogicException('Stock insuffisant pour ce produit.');
        }

        if ($existingItem) {
            $existingItem->setQuantity($existingItem->getQuantity() + $quantity);
        } else {
            $cartItem = new CartItem();
            $cartItem->setUser($user);
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

    public function getItems(User $user): array
    {
        return $this->cartItemRepository->findBy(['user' => $user]);
    }

    public function getTotalItemCount(User $user): int
    {
        $items = $this->getItems($user);
        $count = 0;
        foreach ($items as $item) {
            $count += $item->getQuantity();
        }

        return $count;
    }

    public function getTotalPrice(User $user): int
    {
        $items = $this->getItems($user);
        $total = 0;
        foreach ($items as $item) {
            $total += $item->getProduct()->getPrice() * $item->getQuantity();
        }

        return $total;
    }

    public function checkout(User $user, string $deliveryAddress, string $deliveryPhone): CustomerOrder
{
    $items = $this->getItems($user);

    if (empty($items)) {
        throw new \LogicException('Le panier est vide.');
    }

    $this->entityManager->beginTransaction();

    try {
        // Vérification finale du stock (au cas où il aurait changé depuis l'ajout au panier)
        foreach ($items as $cartItem) {
            $product = $cartItem->getProduct();
            if ($cartItem->getQuantity() > $product->getStock()) {
                throw new \RuntimeException(
                    'Stock insuffisant pour "' . $product->getName() . '" (il en reste ' . $product->getStock() . ').'
                );
            }
        }

        $order = new CustomerOrder();
        $order->setUser($user);
        $order->setStatus('pending');
        $order->setTotal($this->getTotalPrice($user));
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
        $email = (new TemplatedEmail())
            ->from(new Address($this->senderEmail, 'TechLink'))
            ->to((string) $order->getUser()->getEmail())
            ->subject('Confirmation de ta commande n°' . $order->getId())
            ->htmlTemplate('emails/order_confirmation.html.twig')
            ->context([
                'order' => $order,
            ]);

        $this->mailer->send($email);
    }
}