<?php

namespace App\Service;

use App\Repository\MissionRepository;
use App\Repository\MissionElasticRepository;
use Knp\Component\Pager\PaginatorInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class MissionSearchService
{
    private MissionRepository $missionRepository;
    private MissionElasticRepository $elasticRepository;
    private ElasticsearchService $elasticsearchService;
    private RedisService $redisService;
    private PaginatorInterface $paginator;
    private LoggerInterface $logger;
    private ParameterBagInterface $parameterBag;

    public function __construct(
        MissionRepository $missionRepository,
        MissionElasticRepository $elasticRepository,
        ElasticsearchService $elasticsearchService,
        RedisService $redisService,
        PaginatorInterface $paginator,
        LoggerInterface $logger,
        ParameterBagInterface $parameterBag
    ) {
        $this->missionRepository = $missionRepository;
        $this->elasticRepository = $elasticRepository;
        $this->elasticsearchService = $elasticsearchService;
        $this->redisService = $redisService;
        $this->paginator = $paginator;
        $this->logger = $logger;
        $this->parameterBag = $parameterBag;
    }

    /**
     * Search missions with filters, caching, and pagination
     */
    public function searchMissions(
        ?string $searchQuery = null,
        array $filters = [],
        array $sort = [],
        int $page = 1,
        ?int $userId = null,
        bool $isAdmin = false
    ): array {
        $limit = $this->parameterBag->get('app.items_per_page');
        
        // Generate cache key
        $cacheKey = $this->generateCacheKey($searchQuery, $filters, $sort, $page, $userId, $isAdmin);
        
        // Try to get from cache first (skip cache for search queries to ensure fresh results)
        if (!$searchQuery) {
            $cachedResult = $this->redisService->getValue($cacheKey);
            if ($cachedResult !== null) {
                $this->logger->info('Missions retrieved from Redis cache', ['cache_key' => $cacheKey]);
                return [
                    'missions' => $cachedResult,
                    'from_cache' => true
                ];
            }
        }

        try {
            if ($searchQuery) {
                $missions = $this->searchWithElasticsearch($searchQuery, $filters, $sort, $page, $limit, $userId, $isAdmin);
            } else {
                $missions = $this->searchWithDatabase($filters, $page, $limit, $userId, $isAdmin);
            }

            // Cache the result (only for non-search queries)
            if (!$searchQuery) {
                $this->redisService->setValueWithTags($cacheKey, $missions, ['missions'], 600); // 10 minutes
                $this->logger->info('Missions cached in Redis', ['cache_key' => $cacheKey]);
            }

            return [
                'missions' => $missions,
                'from_cache' => false
            ];

        } catch (\Exception $e) {
            $this->logger->error('Error searching missions', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'search_query' => $searchQuery,
                'filters' => $filters
            ]);
            
            return [
                'missions' => [],
                'error' => $e->getMessage(),
                'from_cache' => false
            ];
        }
    }

    /**
     * Search using Elasticsearch
     */
    private function searchWithElasticsearch(
        string $searchQuery,
        array $filters,
        array $sort,
        int $page,
        int $limit,
        ?int $userId,
        bool $isAdmin
    ) {
        // Convert form filters to Elasticsearch filters
        $elasticFilters = $this->convertFiltersToElastic($filters);
        
        // Add user access control to filters if not admin
        if (!$isAdmin && $userId) {
            $elasticFilters['client_id'] = $userId;
        }

        $searchResults = $this->elasticsearchService->searchWithFilters(
            'mission',
            $searchQuery,
            $elasticFilters,
            $sort,
            $page,
            $limit
        );

        $this->logger->info('Elasticsearch search completed', [
            'query' => $searchQuery,
            'total_results' => $searchResults['total'] ?? 0,
            'user_id' => $userId
        ]);

        // Convert Elasticsearch results to Mission entities
        if (!empty($searchResults['items'])) {
            $missionIds = [];
            foreach ($searchResults['items'] as $item) {
                if (isset($item['_source']['id'])) {
                    $missionIds[] = $item['_source']['id'];
                }
            }

            if (!empty($missionIds)) {
                // Maintain the order from Elasticsearch results
                $missionsQuery = $this->missionRepository->createQueryBuilder('m')
                    ->where('m.id IN (:ids)')
                    ->setParameter('ids', $missionIds)
                    ->getQuery();

                // Use a custom ordering to maintain Elasticsearch relevance
                $missions = $missionsQuery->getResult();
                $missions = $this->sortMissionsByIds($missions, $missionIds);

                return $this->paginator->paginate(
                    $missions,
                    $page,
                    $limit
                );
            }
        }

        return [];
    }

    /**
     * Search using database with filters
     */
    private function searchWithDatabase(
        array $filters,
        int $page,
        int $limit,
        ?int $userId,
        bool $isAdmin
    ) {
        $missionsQuery = $this->missionRepository->getFilteredMissionsQuery(
            $filters,
            $isAdmin ? null : $userId
        );

        $this->logger->info('Database search completed', [
            'filters' => $filters,
            'user_id' => $userId
        ]);

        return $this->paginator->paginate(
            $missionsQuery,
            $page,
            $limit
        );
    }

    /**
     * Convert form filters to Elasticsearch filters
     */
    private function convertFiltersToElastic(array $filters): array
    {
        $elasticFilters = [];

        // Handle date range
        if (isset($filters['dateFrom']) || isset($filters['dateTo'])) {
            $dateRange = [];
            if (!empty($filters['dateFrom'])) {
                $dateRange['gte'] = $filters['dateFrom'];
            }
            if (!empty($filters['dateTo'])) {
                $dateRange['lte'] = $filters['dateTo'];
            }
            if (!empty($dateRange)) {
                $elasticFilters['serviceDate'] = $dateRange;
            }
        }

        // Handle quantity range
        if (isset($filters['minQuantity']) || isset($filters['maxQuantity'])) {
            $quantityRange = [];
            if (!empty($filters['minQuantity'])) {
                $quantityRange['gte'] = (int)$filters['minQuantity'];
            }
            if (!empty($filters['maxQuantity'])) {
                $quantityRange['lte'] = (int)$filters['maxQuantity'];
            }
            if (!empty($quantityRange)) {
                $elasticFilters['quantity'] = $quantityRange;
            }
        }

        // Handle country filter
        if (!empty($filters['country'])) {
            $elasticFilters['destinationCountry'] = $filters['country'];
        }

        // Handle status filter
        if (!empty($filters['status'])) {
            $elasticFilters['status'] = $filters['status'];
        }

        // Add more filter mappings as needed
        
        return $elasticFilters;
    }

    /**
     * Sort missions array by the order of IDs from Elasticsearch
     */
    private function sortMissionsByIds(array $missions, array $orderedIds): array
    {
        $missionById = [];
        foreach ($missions as $mission) {
            $missionById[$mission->getId()] = $mission;
        }

        $sortedMissions = [];
        foreach ($orderedIds as $id) {
            if (isset($missionById[$id])) {
                $sortedMissions[] = $missionById[$id];
            }
        }

        return $sortedMissions;
    }

    /**
     * Generate cache key for the search
     */
    private function generateCacheKey(
        ?string $searchQuery,
        array $filters,
        array $sort,
        int $page,
        ?int $userId,
        bool $isAdmin
    ): string {
        $keyData = [
            'search_query' => $searchQuery,
            'filters' => $filters,
            'sort' => $sort,
            'page' => $page,
            'user_id' => $userId,
            'is_admin' => $isAdmin
        ];

        $prefix = $searchQuery ? 'search_missions' : 'filtered_missions';
        return $prefix . '_' . md5(serialize($keyData));
    }

    /**
     * Clear mission-related cache
     */
    public function clearMissionCache(?int $userId = null): void
    {
        // Use tag-based invalidation if available
        $this->redisService->invalidateTag('missions');
        
        $this->logger->info('Mission cache cleared', ['user_id' => $userId]);
    }
}
