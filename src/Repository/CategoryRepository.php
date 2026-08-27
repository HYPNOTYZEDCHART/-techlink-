<?php

namespace App\Repository;

use App\Entity\Category;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Category>
 */
class CategoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Category::class);
    }

    /**
     * Retourne uniquement les slugs de toutes les catégories pour le sitemap.
     * Utilise un tableau scalaire pour éviter de charger les entités et leurs collections associées.
     *
     * @return string[]
     */
    public function findSlugsForSitemap(): array
    {
        $result = $this->createQueryBuilder('c')
            ->select('c.slug')
            ->orderBy('c.name', 'ASC')
            ->getQuery()
            ->getScalarResult();

        return array_column($result, 'slug');
    }
}
