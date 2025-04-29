<?php

namespace App\Repository;

use App\Entity\Mission;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Mission>
 */
class MissionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Mission::class);
    }

    /**
     * Creates a query builder for filtered missions
     */
    public function getFilteredMissionsQuery(array $filters = [], ?int $userId = null): QueryBuilder
    {
        $qb = $this->createQueryBuilder('m')
            ->leftJoin('m.client', 'u')
            ->addSelect('u');
        
        // Filter by user if not admin
        if ($userId !== null) {
            $qb->andWhere('m.client = :userId')
               ->setParameter('userId', $userId);
        }
        
        // Apply filters
        if (!empty($filters['productName'])) {
            $qb->andWhere('m.productName LIKE :productName')
               ->setParameter('productName', '%' . $filters['productName'] . '%');
        }
        
        if (!empty($filters['destinationCountry'])) {
            $qb->andWhere('m.destinationCountry = :destinationCountry')
               ->setParameter('destinationCountry', $filters['destinationCountry']);
        }
        
        if (!empty($filters['serviceDate'])) {
            $qb->andWhere('m.serviceDate = :serviceDate')
               ->setParameter('serviceDate', $filters['serviceDate']);
        }
        
        // Default sorting
        $qb->orderBy('m.serviceDate', 'DESC');
        
        return $qb;
    }

    /**
     * Fall back method to get all missions if no filters are provided
     */
    public function findAllOrEmpty(): array
    {
        try {
            return $this->findAll();
        } catch (\Exception $e) {
            return [];
        }
    }
}