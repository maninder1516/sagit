<?php

namespace App\Repository;

use App\Entity\Mission;

class CompositeMissionRepository implements MissionRepositoryInterface
{
    private MissionRepository $doctrineRepository;
    private MissionElasticRepository $elasticRepository;
    private bool $useElastic;

    public function __construct(
        MissionRepository $doctrineRepository,
        MissionElasticRepository $elasticRepository,
        bool $useElastic = true
    ) {
        $this->doctrineRepository = $doctrineRepository;
        $this->elasticRepository = $elasticRepository;
        $this->useElastic = $useElastic;
    }

    public function search($query, $limit = null, array $options = array()): array
    {
        if ($this->useElastic) {
            return $this->elasticRepository->search($query, $limit, $options);
        }
        
        return $this->doctrineRepository->searchInElastic($query, $limit, $options);
    }

    public function findOne($query, array $options = array()): ?Mission
    {
        if ($this->useElastic) {
            return $this->elasticRepository->findOne($query, $options);
        }
        
        $results = $this->doctrineRepository->searchInElastic($query, 1, $options);
        return count($results) > 0 ? $results[0] : null;
    }

    // Delegate database operations to doctrine repository
    public function __call($method, $arguments)
    {
        return call_user_func_array([$this->doctrineRepository, $method], $arguments);
    }
}
