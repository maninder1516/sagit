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

#[Route(path: '/mission', name: 'sagit_mission_')]
final class MissionController extends AbstractController
{
    #[Route('/', name: 'index')]
    public function index(
        Request $request,
        MissionRepository $missionRepository,
        PaginatorInterface $paginator,
        LoggerInterface $logger,
        RedisService $redisService
    ): Response {
        // Create filter form
        $filterForm = $this->createForm(MissionFilterType::class);
        $filterForm->handleRequest($request);

        // Get filters from form or request
        $filters = $filterForm->isSubmitted() && $filterForm->isValid()
            ? $filterForm->getData()
            : $request->query->all();

        // Get current user role and id
        $user = $this->getUser();
        $isAdmin = $user && in_array('ROLE_ADMIN', $user->getRoles());

        // Create a unique cache key based on filters, page, and user
        $page = $request->query->getInt('page', 1);
        $cacheKey = 'missions_' . md5(serialize($filters) . '_page_' . $page . '_user_' . ($user ? $user->getUserIdentifier() : 'anonymous') . '_admin_' . ($isAdmin ? '1' : '0'));

        // Try to get cached data first
        $missions = $redisService->getValue($cacheKey);

        if ($missions === null) {
            try {
                // Get missions query based on filters and user role
                $missionsQuery = $missionRepository->getFilteredMissionsQuery(
                    $filters,
                    $isAdmin ? null : $user->getUserIdentifier() // If not admin, see only own missions
                );
                $logger->info('Missions Listed', [
                    'user_id' => $user?->getId(),  // Old Way $user ? $user->getId() : null,
                    'filters' => $filters
                ]);
                // Paginate the results
                $missions = $paginator->paginate(
                    $missionsQuery,
                    $page, // current page
                    $this->getParameter('app.items_per_page') // items per page from env
                );

                // Store the paginated results to Redis (cache for 10 minutes)
                $redisService->setValue($cacheKey, $missions, 600);
                $logger->info('Missions cached in Redis', ['cache_key' => $cacheKey]);
            } catch (\Exception $e) {
                // Handle exception, return empty result
                $this->addFlash('warning', 'An error occurred while fetching missions. ' . $e->getMessage());
                $missions = [];
            }
        } else {
            $logger->info('Missions retrieved from Redis cache', ['cache_key' => $cacheKey]);
        }
        return $this->render('mission/index.html.twig', [
            'controller_name' => 'MissionController',
            'missions' => $missions ?? [],
            'filter_form' => $filterForm,
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
}
