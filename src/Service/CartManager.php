<?php

namespace App\Service;

use App\Entity\CartItem;
use App\Entity\Product;
use App\Entity\User;
use App\Repository\CartItemRepository;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\CustomerOrder;
use App\Entity\OrderItem;

class CartManager
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private CartItemRepository $cartItemRepository,
    ) {
    }

    public function addProduct(User $user, Product $product, int $quantity = 1, ?string $selectedColor = null): void
    {
        $existingItem = $this->cartItemRepository->findOneBy([
            'user' => $user,
            'product' => $product,
            'selectedColor' => $selectedColor,
        ]);

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
    }    public function removeItem(CartItem $cartItem): void
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

        $order = new CustomerOrder();
        $order->setUser($user);
        $order->setStatus('pending');
        $order->setTotal($this->getTotalPrice($user));
        $order->setCreatedAt(new \DateTimeImmutable());
        $order->setDeliveryAddress($deliveryAddress);
        $order->setDeliveryPhone($deliveryPhone);

        $this->entityManager->persist($order);

        foreach ($items as $cartItem) {
            $orderItem = new OrderItem();
            $orderItem->setCustomerOrder($order);
            $orderItem->setProduct($cartItem->getProduct());
            $orderItem->setQuantity($cartItem->getQuantity());
            $orderItem->setUnitPrice($cartItem->getProduct()->getPrice());
            $orderItem->setSelectedColor($cartItem->getSelectedColor());

            $this->entityManager->persist($orderItem);
            $this->entityManager->remove($cartItem);
        }

        $this->entityManager->flush();

        return $order;
    }
}