<?php

namespace App\Repository;

use App\Entity\CustomerOrder;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CustomerOrder>
 */
class CustomerOrderRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CustomerOrder::class);
    }

    //    /**
    //     * @return CustomerOrder[] Returns an array of CustomerOrder objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('c')
    //            ->andWhere('c.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('c.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?CustomerOrder
    //    {
    //        return $this->createQueryBuilder('c')
    //            ->andWhere('c.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
    public function getTotalRevenue(): int
    {
        $result = $this->createQueryBuilder('c')
            ->select('SUM(c.total)')
            ->andWhere('c.status != :cancelled')
            ->setParameter('cancelled', 'cancelled')
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $result;
    }
    public function getRevenueLast7Days(): array
{
    $orders = $this->createQueryBuilder('c')
        ->andWhere('c.createdAt >= :date')
        ->andWhere('c.status != :cancelled')
        ->setParameter('date', new \DateTimeImmutable('-6 days'))
        ->setParameter('cancelled', 'cancelled')
        ->getQuery()
        ->getResult();

    $data = [];
    for ($i = 6; $i >= 0; $i--) {
        $date = (new \DateTimeImmutable("-$i days"))->format('Y-m-d');
        $data[$date] = 0;
    }

    foreach ($orders as $order) {
        $day = $order->getCreatedAt()->format('Y-m-d');
        if (isset($data[$day])) {
            $data[$day] += $order->getTotal() / 100;
        }
    }

    return $data;
}

public function getStatusBreakdown(): array
{
    $results = $this->createQueryBuilder('c')
        ->select('c.status, COUNT(c.id) as count')
        ->groupBy('c.status')
        ->getQuery()
        ->getResult();

    $data = [];
    foreach ($results as $row) {
        $data[$row['status']] = (int) $row['count'];
    }

    return $data;
}

    /**
     * @return CustomerOrder[]
     */
    public function findRecent(int $limit = 5): array
    {
        return $this->createQueryBuilder('c')
            ->orderBy('c.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}