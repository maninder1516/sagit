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
use App\Service\MissionSearchService;
use App\Service\MissionSearchRequestHandler;
use App\Repository\MissionElasticRepository;
use Symfony\Component\HttpFoundation\JsonResponse;

#[Route(path: '/mission', name: 'sagit_mission_')]
final class MissionController extends AbstractController
{
    private MissionSearchService $missionSearchService;
    private MissionSearchRequestHandler $requestHandler;
    private LoggerInterface $logger;

    public function __construct(
        MissionSearchService $missionSearchService,
        MissionSearchRequestHandler $requestHandler,
        LoggerInterface $logger
    ) {
        $this->missionSearchService = $missionSearchService;
        $this->requestHandler = $requestHandler;
        $this->logger = $logger;
    }
    #[Route('/', name: 'index')]
    public function index(
        Request $request,
        PaginatorInterface $paginator
    ): Response {
        // Create filter form
        $filterForm = $this->createForm(MissionFilterType::class);
        $filterForm->handleRequest($request);

        // Get current user role and id for access control
        $user = $this->getUser();
        $isAdmin = $user && in_array('ROLE_ADMIN', $user->getRoles());
        $page = $request->query->getInt('page', 1);
        
        // Get search query and filters
        $searchQuery = $request->query->get('q');
        $filters = $filterForm->isSubmitted() && $filterForm->isValid()
            ? $filterForm->getData()
            : $request->query->all();

        // Get sorting parameters
        $sort = [];
        $sortField = $request->query->get('sortBy');
        $sortOrder = $request->query->get('sortOrder', 'asc');
        if ($sortField) {
            $sort[$sortField] = $sortOrder;
        }

        // Search missions using the service
        $result = $this->missionSearchService->searchMissions(
            $searchQuery,
            $filters,
            $sort,
            $page,
            $user?->getId(),
            $isAdmin
        );

        if (isset($result['error'])) {
            $this->addFlash('warning', 'An error occurred while fetching missions. ' . $result['error']);
        }

        $missions = $result['missions'] ?? [];

        return $this->render('mission/index.html.twig', [
            'missions' => $missions,
            'filter_form' => $filterForm->createView(),
            'no_results' => empty($missions) || count($missions) === 0,
            'search_query' => $searchQuery
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

            // Clear mission cache
            $this->missionSearchService->clearMissionCache($this->getUser()?->getId());

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

            // Clear mission cache
            $this->missionSearchService->clearMissionCache($this->getUser()?->getId());

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

            // Clear mission cache
            $this->missionSearchService->clearMissionCache($this->getUser()?->getUserIdentifier());

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
        $query = $request->query->get('q');
        $page = $request->query->getInt('page', 1);
        
        // Get current user info
        $user = $this->getUser();
        $isAdmin = $user && in_array('ROLE_ADMIN', $user->getRoles());
        
        // Extract filters and sorting using helper service
        $filters = $this->requestHandler->extractFilters($request);
        $sort = $this->requestHandler->extractSort($request);

        // Use the mission search service
        $result = $this->missionSearchService->searchMissions(
            $query,
            $filters,
            $sort,
            $page,
            $user?->getUserIdentifier(),
            $isAdmin
        );

        if (isset($result['error'])) {
            return $this->json([
                'error' => $result['error'],
                'items' => [],
                'total' => 0
            ], 500);
        }

        $missions = $result['missions'] ?? [];
        
        // Convert to JSON response format using helper service
        $responseData = $this->requestHandler->createPaginatedResponse($missions, $page);

        return $this->json($responseData);
    }
}
