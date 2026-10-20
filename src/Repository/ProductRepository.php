<?php

namespace App\Repository;

use App\DTO\ProductFilterDto;
use App\Entity\Product;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Product>
 */
class ProductRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Product::class);
    }

    /**
     * Recherche et filtrage paginé avec élimination stricte des requêtes N+1 (JOIN + addSelect).
     */
    public function findFilteredPaginated(ProductFilterDto $filter): Paginator
    {
        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.category', 'c')
            ->addSelect('c')
            ->leftJoin('p.productImages', 'i')
            ->addSelect('i')
            ->leftJoin('p.productColors', 'col')
            ->addSelect('col')
            ->andWhere('p.isActive = true');

        if ($filter->search !== null && $filter->search !== '') {
            $qb->andWhere('LOWER(p.name) LIKE :search OR LOWER(p.brand) LIKE :search OR LOWER(p.description) LIKE :search OR LOWER(p.sku) LIKE :search')
                ->setParameter('search', '%' . mb_strtolower($filter->search) . '%');
        }

        if (!empty($filter->categorySlugs)) {
            $qb->andWhere('c.slug IN (:categorySlugs)')
                ->setParameter('categorySlugs', $filter->categorySlugs);
        }

        if (!empty($filter->brands)) {
            $qb->andWhere('p.brand IN (:brands)')
                ->setParameter('brands', $filter->brands);
        }

        if ($filter->priceMin !== null) {
            $qb->andWhere('p.price >= :priceMin')
                ->setParameter('priceMin', $filter->priceMin);
        }

        if ($filter->priceMax !== null) {
            $qb->andWhere('p.price <= :priceMax')
                ->setParameter('priceMax', $filter->priceMax);
        }

        if ($filter->condition !== null) {
            $qb->andWhere('p.condition = :condition')
                ->setParameter('condition', $filter->condition);
        }

        if ($filter->inStockOnly) {
            $qb->andWhere('p.stock > 0');
        }

        match ($filter->sortBy) {
            'price_asc' => $qb->orderBy('p.price', 'ASC'),
            'price_desc' => $qb->orderBy('p.price', 'DESC'),
            'name_asc' => $qb->orderBy('p.name', 'ASC'),
            'created_asc' => $qb->orderBy('p.createdAt', 'ASC'),
            default => $qb->orderBy('p.createdAt', 'DESC'),
        };

        $qb->setFirstResult(($filter->page - 1) * $filter->limit)
            ->setMaxResults($filter->limit);

        return new Paginator($qb, fetchJoinCollection: true);
    }

    /**
     * Récupère les produits sous le seuil de stock critique pour le Dashboard Admin.
     *
     * @return Product[]
     */
    public function findLowStockProducts(int $defaultThreshold = 5): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.category', 'c')
            ->addSelect('c')
            ->andWhere('p.isActive = true')
            ->andWhere('p.stock <= p.lowStockThreshold OR p.stock <= :defaultThreshold')
            ->setParameter('defaultThreshold', $defaultThreshold)
            ->orderBy('p.stock', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return string[]
     */
    public function findDistinctBrands(): array
    {
        $result = $this->createQueryBuilder('p')
            ->select('DISTINCT p.brand')
            ->andWhere('p.isActive = true')
            ->orderBy('p.brand', 'ASC')
            ->getQuery()
            ->getResult();

        return array_column($result, 'brand');
    }

    /**
     * @return Product[]
     */
    public function searchByName(string $search, array $criteria = []): array
    {
        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.category', 'c')
            ->addSelect('c')
            ->andWhere('p.name LIKE :search OR p.brand LIKE :search OR p.description LIKE :search')
            ->setParameter('search', '%' . $search . '%')
            ->orderBy('p.createdAt', 'DESC');

        foreach ($criteria as $field => $value) {
            $qb->andWhere("p.$field = :$field")
                ->setParameter($field, $value);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @return Product[]
     */
    public function searchSuggestions(string $search, int $limit = 5): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.category', 'c')
            ->addSelect('c')
            ->andWhere('LOWER(p.name) LIKE :search OR LOWER(p.brand) LIKE :search OR LOWER(p.sku) LIKE :search')
            ->andWhere('p.isActive = true')
            ->setParameter('search', '%' . mb_strtolower($search) . '%')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findPaginated(array $criteria, int $page, int $limit = 12, ?int $priceMin = null, ?int $priceMax = null): Paginator
    {
        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.category', 'c')
            ->addSelect('c')
            ->leftJoin('p.productImages', 'i')
            ->addSelect('i');

        foreach ($criteria as $field => $value) {
            if (is_array($value)) {
                if (!empty($value)) {
                    $qb->andWhere("p.$field IN (:$field)")
                       ->setParameter($field, $value);
                }
            } else {
                $qb->andWhere("p.$field = :$field")
                   ->setParameter($field, $value);
            }
        }

        if ($priceMin !== null) {
            $qb->andWhere('p.price >= :priceMin')->setParameter('priceMin', $priceMin);
        }

        if ($priceMax !== null) {
            $qb->andWhere('p.price <= :priceMax')->setParameter('priceMax', $priceMax);
        }

        $qb->orderBy('p.createdAt', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        return new Paginator($qb, fetchJoinCollection: true);
    }

    public function searchPaginated(string $search, array $criteria, int $page, int $limit = 12, ?int $priceMin = null, ?int $priceMax = null): Paginator
    {
        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.category', 'c')
            ->addSelect('c')
            ->leftJoin('p.productImages', 'i')
            ->addSelect('i')
            ->andWhere('p.name LIKE :search OR p.brand LIKE :search OR p.description LIKE :search')
            ->setParameter('search', '%' . $search . '%')
            ->orderBy('p.createdAt', 'DESC');

        foreach ($criteria as $field => $value) {
            if (is_array($value)) {
                if (!empty($value)) {
                    $qb->andWhere("p.$field IN (:$field)")
                       ->setParameter($field, $value);
                }
            } else {
                $qb->andWhere("p.$field = :$field")
                   ->setParameter($field, $value);
            }
        }

        if ($priceMin !== null) {
            $qb->andWhere('p.price >= :priceMin')->setParameter('priceMin', $priceMin);
        }

        if ($priceMax !== null) {
            $qb->andWhere('p.price <= :priceMax')->setParameter('priceMax', $priceMax);
        }

        $qb->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        return new Paginator($qb, fetchJoinCollection: true);
    }

    /**
     * Retourne uniquement les slugs des produits actifs pour le sitemap.
     *
     * @return string[]
     */
    public function findSlugsForSitemap(): array
    {
        $result = $this->createQueryBuilder('p')
            ->select('p.slug')
            ->andWhere('p.isActive = true')
            ->andWhere('p.stock > 0')
            ->orderBy('p.id', 'ASC')
            ->getQuery()
            ->getScalarResult();

        return array_column($result, 'slug');
    }
}