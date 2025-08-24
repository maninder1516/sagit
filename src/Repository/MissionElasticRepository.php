<?php

namespace App\Repository;

use App\Entity\Mission;
use FOS\ElasticaBundle\Repository;
use Elastica\Query;

class MissionElasticRepository extends Repository implements MissionRepositoryInterface
{
    public function search($query, $limit = null, array $options = array()): array
    {
        if ($query instanceof Query) {
            return $this->find($query, $limit);
        }

        // Create a multi-match query for better full-text search
        $multiMatchQuery = new Query\MultiMatch();
        $multiMatchQuery->setQuery($query);
        $multiMatchQuery->setFields(['productName^3', 'vendorName^2', 'destinationCountry', 'vendorEmail']); // Boost product and vendor names
        $multiMatchQuery->setType('best_fields');
        $multiMatchQuery->setFuzziness('AUTO');

        // Create the main query
        $mainQuery = new Query();
        $mainQuery->setQuery($multiMatchQuery);
        
        // Add highlight
        $mainQuery->setHighlight([
            'fields' => [
                'productName' => new \stdClass(),
                'vendorName' => new \stdClass(),
                'destinationCountry' => new \stdClass()
            ]
        ]);

        if ($limit) {
            $mainQuery->setSize($limit);
        }

        return $this->find($mainQuery);
    }

    public function findOne($query, array $options = array()): ?Mission
    {
        $results = $this->search($query, 1, $options);
        
        return count($results) > 0 ? $results[0] : null;
    }
}
