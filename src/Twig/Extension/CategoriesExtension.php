<?php

namespace App\Twig\Extension;

use App\Twig\Runtime\CategoriesExtensionRuntime;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class CategoriesExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('all_categories', [CategoriesExtensionRuntime::class, 'getAllCategories']),
            new TwigFunction('all_brands', [CategoriesExtensionRuntime::class, 'getAllBrands']),
            new TwigFunction('cart_count', [CategoriesExtensionRuntime::class, 'getCartCount']),
            new TwigFunction('wishlist_count', [CategoriesExtensionRuntime::class, 'getWishlistCount']),
        ];
    }
}