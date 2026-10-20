<?php

declare(strict_types=1);

namespace App\DTO;

use App\Enum\ProductCondition;
use Symfony\Component\HttpFoundation\Request;

final readonly class ProductFilterDto
{
    /**
     * @param list<string> $categorySlugs
     * @param list<string> $brands
     */
    public function __construct(
        public ?string $search = null,
        public array $categorySlugs = [],
        public array $brands = [],
        public ?int $priceMin = null,
        public ?int $priceMax = null,
        public ?ProductCondition $condition = null,
        public bool $inStockOnly = false,
        public string $sortBy = 'created_desc',
        public int $page = 1,
        public int $limit = 12,
    ) {
    }

    public static function fromRequest(Request $request): self
    {
        $search = $request->query->get('q');
        if (is_string($search)) {
            $search = trim($search);
            if ($search === '') {
                $search = null;
            }
        } else {
            $search = null;
        }

        // Catégories : supporte ?categorie=slug ou ?categories[]=slug
        $rawCat = $request->query->all('categories');
        if (empty($rawCat) && $request->query->has('categorie')) {
            $singleCat = $request->query->getString('categorie');
            $rawCat = $singleCat !== '' ? [$singleCat] : [];
        }
        /** @var list<string> $categorySlugs */
        $categorySlugs = array_values(array_filter(array_map('strval', $rawCat)));

        // Marques : supporte ?marque=brand ou ?marques[]=brand
        $rawBrands = $request->query->all('marques');
        if (empty($rawBrands) && $request->query->has('marque')) {
            $singleBrand = $request->query->getString('marque');
            $rawBrands = $singleBrand !== '' ? [$singleBrand] : [];
        }
        /** @var list<string> $brands */
        $brands = array_values(array_filter(array_map('strval', $rawBrands)));

        $priceMin = $request->query->has('prix_min') && $request->query->get('prix_min') !== ''
            ? (int) $request->query->get('prix_min')
            : null;

        $priceMax = $request->query->has('prix_max') && $request->query->get('prix_max') !== ''
            ? (int) $request->query->get('prix_max')
            : null;

        $conditionRaw = $request->query->getString('etat');
        $condition = null;
        if ($conditionRaw !== '') {
            $condition = ProductCondition::tryFrom($conditionRaw);
        }

        $inStockOnly = $request->query->getBoolean('en_stock', false);

        $sortBy = $request->query->getString('tri', 'created_desc');
        $allowedSorts = ['created_desc', 'created_asc', 'price_asc', 'price_desc', 'name_asc', 'name_desc'];
        if (!in_array($sortBy, $allowedSorts, true)) {
            $sortBy = 'created_desc';
        }

        $page = max(1, $request->query->getInt('page', 1));
        $limit = max(1, min(48, $request->query->getInt('limit', 12)));

        return new self(
            search: $search,
            categorySlugs: $categorySlugs,
            brands: $brands,
            priceMin: $priceMin,
            priceMax: $priceMax,
            condition: $condition,
            inStockOnly: $inStockOnly,
            sortBy: $sortBy,
            page: $page,
            limit: $limit,
        );
    }
}
