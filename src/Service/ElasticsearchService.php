<?php

namespace App\Service;

use FOS\ElasticaBundle\Finder\PaginatedFinderInterface;
use FOS\ElasticaBundle\Manager\RepositoryManagerInterface;
use Elastica\Query;
use Elastica\Query\BoolQuery;
use Elastica\Query\MultiMatch;
use Elastica\Query\Range;
use Elastica\Query\Terms;
use Elastica\Query\Term;
use Psr\Log\LoggerInterface;

class ElasticsearchService
{
    private $finder;
    private $repositoryManager;
    private $logger;

    public function __construct(
        RepositoryManagerInterface $repositoryManager,
        LoggerInterface $logger
    ) {
        $this->repositoryManager = $repositoryManager;
        $this->logger = $logger;
    }

    public function search(string $indexName, string $query, ?int $limit = 10, ?int $page = 1): array
    {
        try {
            $repository = $this->repositoryManager->getRepository("App\\Entity\\" . ucfirst($indexName));
            
            // Create the multi-match query
            $multiMatchQuery = new MultiMatch();
            $multiMatchQuery->setQuery($query);
            $multiMatchQuery->setFields(['productName^3', 'vendorName^2', 'destinationCountry', 'vendorEmail']);
            $multiMatchQuery->setType('best_fields');
            $multiMatchQuery->setFuzziness('AUTO');
            
            // Create the main query
            $mainQuery = new Query();
            $mainQuery->setQuery($multiMatchQuery);
            $mainQuery->setSize($limit);
            $mainQuery->setFrom(($page - 1) * $limit);

            // Log the query being executed
            $this->logger->debug('Executing search query', [
                'query' => $mainQuery->toArray(),
                'index' => $indexName
            ]);

            // Execute the search
            $results = $repository->find($mainQuery);
            
            // Log the results
            $this->logger->debug('Search results received', [
                'query' => $query,
                'result_count' => count($results),
                'first_result' => !empty($results) ? json_encode($results[0]) : null
            ]);

            return [
                'total' => count($results),
                'items' => $results
            ];
        } catch (\Exception $e) {
            $this->logger->error('Search failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    public function searchWithFilters(
        string $indexName,
        ?string $query = null,
        array $filters = [],
        array $sort = [],
        int $page = 1,
        int $limit = 10
    ): array {
        try {
            $repository = $this->repositoryManager->getRepository("App\\Entity\\" . ucfirst($indexName));
            
            // Create the bool query
            $boolQuery = new BoolQuery();

            // Add full-text search if query is provided
            if ($query) {
                $multiMatchQuery = new MultiMatch();
                $multiMatchQuery->setQuery($query);
                $multiMatchQuery->setFields([
                    'productName^3',    // Boost product name matches
                    'vendorName^2',     // Boost vendor name matches
                    'destinationCountry',
                    'vendorEmail'
                ]);
                $multiMatchQuery->setType('best_fields');
                $multiMatchQuery->setFuzziness('AUTO');
                $boolQuery->addMust($multiMatchQuery);
                
                $this->logger->debug('Full-text search query added', [
                    'query' => $query,
                    'fields' => [
                        'productName^3',
                        'vendorName^2',
                        'destinationCountry',
                        'vendorEmail'
                    ]
                ]);
            }

            // Add filters
            foreach ($filters as $field => $value) {
                if (is_array($value)) {
                    if (isset($value['gte']) || isset($value['lte'])) {
                        // Range filter
                        $rangeQuery = new Range($field, $value);
                        $boolQuery->addFilter($rangeQuery);
                    } else {
                        // Terms filter
                        $termsQuery = new Terms($field, $value);
                        $boolQuery->addFilter($termsQuery);
                    }
                } else {
                    // Term filter
                    $termQuery = new Term([$field => $value]);
                    $boolQuery->addFilter($termQuery);
                }
            }

            // Create the main query
            $mainQuery = new Query($boolQuery);

            // Add sorting
            foreach ($sort as $field => $order) {
                $mainQuery->addSort([$field => ['order' => $order]]);
            }

            // Add pagination
            $mainQuery->setFrom(($page - 1) * $limit);
            $mainQuery->setSize($limit);

            // Log the final query
            $this->logger->debug('Final search query', [
                'query' => $mainQuery->toArray(),
                'index' => $indexName,
                'page' => $page,
                'limit' => $limit
            ]);

            // Execute the search
            $results = $repository->find($mainQuery);
            
            // Log the results
            $this->logger->debug('Search results', [
                'count' => count($results),
                'query' => $query,
                'filters' => $filters,
                'sort' => $sort,
                'first_result' => !empty($results) ? json_encode($results[0]) : null
            ]);

            return [
                'total' => count($results),
                'pages' => ceil(count($results) / $limit),
                'current_page' => $page,
                'items' => array_slice($results, 0, $limit),
                'query_info' => [
                    'search_term' => $query,
                    'filters' => $filters,
                    'sort' => $sort
                ]
            ];
        } catch (\Exception $e) {
            $this->logger->error('Search failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'query' => $query,
                'filters' => $filters
            ]);
            throw $e;
        }
    }
}
