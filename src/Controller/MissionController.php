<?php

namespace App\Controller;

use App\Entity\Mission;
use App\Form\MissionFilterType;
use App\Form\MissionType;
use App\Repository\MissionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Psr\Log\LoggerInterface;
use App\Service\RedisService;
use App\Service\ElasticsearchService;
use App\Repository\MissionElasticRepository;
use Symfony\Component\HttpFoundation\JsonResponse;

#[Route(path: '/mission', name: 'sagit_mission_')]
final class MissionController extends AbstractController
{
    private ElasticsearchService $elasticsearchService;
    private RedisService $redisService;
    private LoggerInterface $logger;

    public function __construct(
        ElasticsearchService $elasticsearchService,
        RedisService $redisService,
        LoggerInterface $logger
    ) {
        $this->elasticsearchService = $elasticsearchService;
        $this->redisService = $redisService;
        $this->logger = $logger;
    }
    #[Route('/', name: 'index')]
    public function index(
        Request $request,
        MissionRepository $missionRepository,
        MissionElasticRepository $elasticRepository,
        PaginatorInterface $paginator
    ): Response {
        // Create filter form
        $filterForm = $this->createForm(MissionFilterType::class);
        $filterForm->handleRequest($request);

        // Get current user role and id for access control
        $user = $this->getUser();
        $isAdmin = $user && in_array('ROLE_ADMIN', $user->getRoles());
        $page = $request->query->getInt('page', 1);
        
        // Check if we have a search query
        $searchQuery = $request->query->get('q');
        
        // Create a unique cache key based on search/filters and user
        $cacheKey = $searchQuery 
            ? 'search_missions_' . md5($searchQuery . '_page_' . $page . '_user_' . ($user ? $user->getUserIdentifier() : 'anonymous') . '_admin_' . ($isAdmin ? '1' : '0'))
            : 'filtered_missions_' . md5(serialize($filterForm->isSubmitted() && $filterForm->isValid() ? $filterForm->getData() : $request->query->all()) . '_page_' . $page . '_user_' . ($user ? $user->getUserIdentifier() : 'anonymous') . '_admin_' . ($isAdmin ? '1' : '0'));

        // Skip cache for search queries as they need fresh Elasticsearch results
        if ($searchQuery) {
            $missions = null;
        } else {
            // Try to get cached data first for non-search queries
            $missions = $this->redisService->getValue($cacheKey);
        }

        if ($missions === null) {
            try {
                if ($searchQuery) {
                    try {
                        // Get search results using the same method as the search endpoint
                        $searchResults = $this->elasticsearchService->searchWithFilters(
                            'mission',
                            $searchQuery,
                            [], // No additional filters for basic search
                            [], // No sorting
                            $page,
                            $this->getParameter('app.items_per_page')
                        );
                        
                        $this->logger->info('Elasticsearch results', [
                            'count' => $searchResults['total'] ?? 0,
                            'query' => $searchQuery,
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
                                // Fetch actual Mission entities from the repository
                                $missionsQuery = $missionRepository->createQueryBuilder('m')
                                    ->where('m.id IN (:ids)')
                                    ->setParameter('ids', $missionIds)
                                    ->getQuery();

                                $missions = $paginator->paginate(
                                    $missionsQuery,
                                    $page,
                                    $this->getParameter('app.items_per_page')
                                );
                            } else {
                                $missions = [];
                            }
                        } else {
                            $missions = [];
                        }
                        
                        $this->logger->info('Search missions with Elasticsearch', [
                            'query' => $searchQuery,
                            'user_id' => $user?->getId(),
                            'results_count' => count($missions)
                        ]);
                    } catch (\Exception $e) {
                        $this->logger->error('Elasticsearch error', [
                            'message' => $e->getMessage(),
                            'trace' => $e->getTraceAsString()
                        ]);
                        throw $e;
                    }
                } else {
                    // Use regular filters if no search query
                    $filters = $filterForm->isSubmitted() && $filterForm->isValid()
                        ? $filterForm->getData()
                        : $request->query->all();
                    
                    // Get missions query based on filters and user role
                    $missionsQuery = $missionRepository->getFilteredMissionsQuery(
                        $filters,
                        $isAdmin ? null : ($user ? (int)$user->getId() : null)
                    );
                    
                    $this->logger->info('Filter missions from database', [
                        'user_id' => $user?->getId(),
                        'filters' => $filters
                    ]);
                    
                    $missions = $paginator->paginate(
                        $missionsQuery,
                        $page,
                        $this->getParameter('app.items_per_page')
                    );
                }

                // Only cache non-search results
                if (!$searchQuery) {
                    // Store the paginated results to Redis (cache for 10 minutes)
                    $this->redisService->setValue($cacheKey, $missions, 600);
                    $this->logger->info('Missions cached in Redis', ['cache_key' => $cacheKey]);
                }
            } catch (\Exception $e) {
                $this->addFlash('warning', 'An error occurred while fetching missions. ' . $e->getMessage());
                $missions = [];
            }
        } else {
            $this->logger->info('Missions retrieved from Redis cache', ['cache_key' => $cacheKey]);
        }

        return $this->render('mission/index.html.twig', [
            'missions' => $missions ?? [],
            'filter_form' => $filterForm->createView(),
            'no_results' => empty($missions) || count($missions) === 0
        ]);
    }

    #[Route('/new', name: 'new')]
    public function new(Request $request, MissionRepository $missionRepository, LoggerInterface $logger): Response
    {
        // Reject if user is not a client or is an admin
        if (!$this->isGranted('ROLE_CLIENT') || $this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException('Only clients without admin role can create missions.');
        }

        $mission = new Mission();
        $form = $this->createForm(MissionType::class, $mission);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Save mission and set the current user as client
            $mission = $missionRepository->save($mission, $this->getUser());

            $logger->info('Mission created', [
                'mission_id' => $mission->getId(),
                'user_id' => $this->getUser() ? $this->getUser()->getUserIdentifier() : null
            ]);

            $this->addFlash('success', 'Mission created successfully!');

            return $this->redirectToRoute('sagit_mission_show', [
                'id' => $mission->getId()
            ]);
        }

        return $this->render('mission/addedit.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/view/{id}', name: 'show', methods: ['GET'])]
    public function show(Mission $mission): Response
    {
        // Check if current user owns this mission or is an admin
        $this->denyAccessUnlessGranted('VIEW', $mission);

        return $this->render('mission/view.html.twig', [
            'mission' => $mission,
        ]);
    }

    #[Route('/edit/{id}', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Mission $mission, MissionRepository $missionRepository, LoggerInterface $logger): Response
    {
        // Check if current user owns this mission
        $this->denyAccessUnlessGranted('EDIT', $mission);

        $form = $this->createForm(MissionType::class, $mission);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Save mission (client already set for existing mission)
            $missionRepository->save($mission);

            $logger->info('Mission updated', [
                'mission_id' => $mission->getId(),
                'user_id' => $this->getUser() ? $this->getUser()->getUserIdentifier() : null
            ]);

            $this->addFlash('success', 'Mission updated successfully!');

            return $this->redirectToRoute('sagit_mission_show', [
                'id' => $mission->getId()
            ]);
        }

        return $this->render('mission/addedit.html.twig', [
            'mission' => $mission,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/delete/{id}', name: 'delete', methods: ['POST'])]
    public function delete(Request $request, Mission $mission, MissionRepository $missionRepository, LoggerInterface $logger): Response
    {
        // Check if current user owns this mission or is an admin
        $this->denyAccessUnlessGranted('EDIT', $mission);

        // Check CSRF token for security
        $submittedToken = $request->request->get('token');
        if ($this->isCsrfTokenValid('delete-mission-' . $mission->getId(), $submittedToken)) {
            // Remove the mission
            $missionRepository->remove($mission);

            $logger->info('Mission deleted', [
                'mission_id' => $mission->getId(),
                'user_id' => $this->getUser() ? $this->getUser()->getUserIdentifier() : null
            ]);

            $this->addFlash('success', 'Mission deleted successfully');
        } else {
            $logger->warning('Mission delete failed due to invalid CSRF token', [
                'mission_id' => $mission->getId(),
                'user_id' => $this->getUser() ? $this->getUser()->getUserIdentifier() : null
            ]);
            $this->addFlash('error', 'Invalid security token');
        }

        return $this->redirectToRoute('sagit_mission_index');
    }

    #[Route('/search', name: 'search', methods: ['GET'])]
    public function search(Request $request): JsonResponse
    {
        //echo 'Hello'; exit;
        $query = $request->query->get('q');
        $page = $request->query->getInt('page', 1);
        $limit = $request->query->getInt('limit', 10);
    
        $filters = [];
        
        // Add date range filter if provided
        $dateFrom = $request->query->get('dateFrom');
        $dateTo = $request->query->get('dateTo');
        if ($dateFrom || $dateTo) {
            $dateRange = [];
            if ($dateFrom) $dateRange['gte'] = $dateFrom;
            if ($dateTo) $dateRange['lte'] = $dateTo;
            $filters['serviceDate'] = $dateRange;
        }
    
        // Add quantity range filter if provided
        $minQuantity = $request->query->get('minQuantity');
        $maxQuantity = $request->query->get('maxQuantity');
        if ($minQuantity || $maxQuantity) {
            $quantityRange = [];
            if ($minQuantity) $quantityRange['gte'] = (int)$minQuantity;
            if ($maxQuantity) $quantityRange['lte'] = (int)$maxQuantity;
            $filters['quantity'] = $quantityRange;
        }
    
        // Add country filter if provided
        $country = $request->query->get('country');
        if ($country) {
            $filters['destinationCountry'] = $country;
        }
    
        // Add sorting
        $sort = [];
        $sortField = $request->query->get('sortBy');
        $sortOrder = $request->query->get('sortOrder', 'asc');
        if ($sortField) {
            $sort[$sortField] = $sortOrder;
        }
    
        $results = $this->elasticsearchService->searchWithFilters(
            'mission',
            $query,
            $filters,
            $sort,
            $page,
            $limit
        );
    
        return $this->json($results);
    }
}
