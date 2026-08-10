<?php

namespace App\Service;

use App\Entity\Product;
use App\Entity\User;
use App\Entity\WishlistItem;
use App\Repository\WishlistItemRepository;
use Doctrine\ORM\EntityManagerInterface;

class WishlistManager
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private WishlistItemRepository $wishlistItemRepository,
    ) {
    }

    public function toggle(User $user, Product $product): bool
    {
        $existing = $this->wishlistItemRepository->findOneBy([
            'user' => $user,
            'product' => $product,
        ]);

        if ($existing) {
            $this->entityManager->remove($existing);
            $this->entityManager->flush();
            return false;
        }

        $item = new WishlistItem();
        $item->setUser($user);
        $item->setProduct($product);
        $this->entityManager->persist($item);
        $this->entityManager->flush();

        return true;
    }

    public function isInWishlist(User $user, Product $product): bool
    {
        return $this->wishlistItemRepository->findOneBy([
            'user' => $user,
            'product' => $product,
        ]) !== null;
    }

    public function getItems(User $user): array
    {
        return $this->wishlistItemRepository->findBy(['user' => $user]);
    }
}