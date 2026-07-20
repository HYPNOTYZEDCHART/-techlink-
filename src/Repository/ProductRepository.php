<?php

namespace App\Repository;

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
     * @return string[]
     */
    public function findDistinctBrands(): array
    {
        $result = $this->createQueryBuilder('p')
            ->select('DISTINCT p.brand')
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
            ->andWhere('p.name LIKE :search OR p.brand LIKE :search')
            ->andWhere('p.isActive = true')
            ->setParameter('search', '%' . $search . '%')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findPaginated(array $criteria, int $page, int $limit = 12, ?int $priceMin = null, ?int $priceMax = null): Paginator
{
    $qb = $this->createQueryBuilder('p');

    foreach ($criteria as $field => $value) {
        $qb->andWhere("p.$field = :$field")
            ->setParameter($field, $value);
    }

    if ($priceMin !== null) {
        $qb->andWhere('p.price >= :priceMin')->setParameter('priceMin', $priceMin * 100);
    }

    if ($priceMax !== null) {
        $qb->andWhere('p.price <= :priceMax')->setParameter('priceMax', $priceMax * 100);
    }

    $qb->orderBy('p.createdAt', 'DESC')
        ->setFirstResult(($page - 1) * $limit)
        ->setMaxResults($limit);

    return new Paginator($qb);
}
    public function searchPaginated(string $search, array $criteria, int $page, int $limit = 12, ?int $priceMin = null, ?int $priceMax = null): Paginator
{
    $qb = $this->createQueryBuilder('p')
        ->andWhere('p.name LIKE :search OR p.brand LIKE :search OR p.description LIKE :search')
        ->setParameter('search', '%' . $search . '%')
        ->orderBy('p.createdAt', 'DESC');

    foreach ($criteria as $field => $value) {
        $qb->andWhere("p.$field = :$field")
            ->setParameter($field, $value);
    }

    if ($priceMin !== null) {
        $qb->andWhere('p.price >= :priceMin')->setParameter('priceMin', $priceMin * 100);
    }

    if ($priceMax !== null) {
        $qb->andWhere('p.price <= :priceMax')->setParameter('priceMax', $priceMax * 100);
    }

    $qb->setFirstResult(($page - 1) * $limit)
        ->setMaxResults($limit);

    return new Paginator($qb);
}
}