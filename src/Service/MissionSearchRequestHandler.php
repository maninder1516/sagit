<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\Request;

class MissionSearchRequestHandler
{
    /**
     * Extract filters from request
     */
    public function extractFilters(Request $request): array
    {
        $filters = [];
        
        // Add date range filter if provided
        $dateFrom = $request->query->get('dateFrom');
        $dateTo = $request->query->get('dateTo');
        if ($dateFrom || $dateTo) {
            $filters['dateFrom'] = $dateFrom;
            $filters['dateTo'] = $dateTo;
        }

        // Add quantity range filter if provided
        $minQuantity = $request->query->get('minQuantity');
        $maxQuantity = $request->query->get('maxQuantity');
        if ($minQuantity || $maxQuantity) {
            $filters['minQuantity'] = $minQuantity;
            $filters['maxQuantity'] = $maxQuantity;
        }

        // Add country filter if provided
        $country = $request->query->get('country');
        if ($country) {
            $filters['country'] = $country;
        }

        return $filters;
    }

    /**
     * Extract sorting from request
     */
    public function extractSort(Request $request): array
    {
        $sort = [];
        $sortField = $request->query->get('sortBy');
        $sortOrder = $request->query->get('sortOrder', 'asc');
        
        if ($sortField) {
            $sort[$sortField] = $sortOrder;
        }

        return $sort;
    }

    /**
     * Convert mission entities to array format for JSON response
     */
    public function convertMissionsToArray($missions): array
    {
        $items = [];
        
        if ($missions && method_exists($missions, 'getItems')) {
            foreach ($missions->getItems() as $mission) {
                $items[] = [
                    'id' => $mission->getId(),
                    'title' => $mission->getTitle(),
                    'description' => $mission->getDescription(),
                    'serviceDate' => $mission->getServiceDate()?->format('Y-m-d'),
                    'quantity' => $mission->getQuantity(),
                    'destinationCountry' => $mission->getDestinationCountry(),
                    'status' => $mission->getStatus(),
                    // Add more fields as needed
                ];
            }
        }

        return $items;
    }

    /**
     * Create paginated response data
     */
    public function createPaginatedResponse($missions, int $page): array
    {
        $items = $this->convertMissionsToArray($missions);
        
        return [
            'items' => $items,
            'total' => $missions && method_exists($missions, 'getTotalItemCount') 
                ? $missions->getTotalItemCount() 
                : count($items),
            'page' => $page,
            'pages' => $missions && method_exists($missions, 'getPageCount') 
                ? $missions->getPageCount() 
                : 1
        ];
    }
}
