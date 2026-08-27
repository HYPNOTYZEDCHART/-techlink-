<?php

namespace App\Twig\Runtime;

use App\Repository\CategoryRepository;
use App\Repository\ProductRepository;
use App\Service\CartManager;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Extension\RuntimeExtensionInterface;

class CategoriesExtensionRuntime implements RuntimeExtensionInterface
{
    public function __construct(
        private CategoryRepository $categoryRepository,
        private ProductRepository $productRepository,
        private CartManager $cartManager,
        private \App\Service\WishlistManager $wishlistManager,
        private Security $security,
    ) {
    }

    public function getAllCategories(): array
    {
        return $this->categoryRepository->findAll();
    }

    public function getAllBrands(): array
    {
        return $this->productRepository->findDistinctBrands();
    }

    public function getCartCount(): int
    {
        $user = $this->security->getUser();
        return $this->cartManager->getTotalItemCount($user);
    }

    public function getWishlistCount(): int
    {
        $user = $this->security->getUser();

        if (!$user) {
            return 0;
        }

        return count($this->wishlistManager->getItems($user));
    }
}