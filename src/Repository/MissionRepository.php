<?php

namespace App\Repository;

use App\Entity\Mission;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Elastica\Query;
use FOS\ElasticaBundle\Finder\TransformedFinder;
/**
 * @extends ServiceEntityRepository<Mission>
 */
class MissionRepository extends ServiceEntityRepository implements MissionRepositoryInterface
{
    private ?TransformedFinder $finder = null;
    
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Mission::class);
    }
    
    public function setFinder(TransformedFinder $finder): void
    {
        $this->finder = $finder;
    }

    public function search($query, $limit = null, array $options = array()): array
    {
        if ($this->finder === null) {
            throw new \RuntimeException('Finder not initialized. Did you forget to call setFinder()?');
        }
        
        if ($query instanceof Query) {
            $queryObj = $query;
        } else {
            $queryObj = new Query\BoolQuery();
            $queryObj->addShould(
                new Query\QueryString($query)
            );
        }
        
        return $this->finder->find($queryObj, $limit);
    }
    
    /**
     * @return Mission|null
     */
    public function findOne($query, array $options = array()): ?Mission
    {
        $results = $this->find($query, 1, $options);
        
        return count($results) > 0 ? $results[0] : null;
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
    
    /**
     * Save a mission (create or update)
     * 
     * @param Mission $mission The mission to save
     * @param User|null $client The client user to assign (only for new missions)
     * @return Mission The saved mission
     */
    public function save(Mission $mission, ?User $client = null): Mission
    {
        $entityManager = $this->getEntityManager();
        
        // If this is a new mission and a client is provided, set it
        if ($mission->getId() === null && $client !== null) {
            $mission->setClient($client);
        }
        
        // Persist if it's a new entity
        if ($mission->getId() === null) {
            $entityManager->persist($mission);
        }
        
        $entityManager->flush();
        
        return $mission;
    }
    
    /**
     * Remove a mission
     * 
     * @param Mission $mission The mission to remove
     * @return void
     */
    public function remove(Mission $mission): void
    {
        $entityManager = $this->getEntityManager();
        $entityManager->remove($mission);
        $entityManager->flush();
    }
}