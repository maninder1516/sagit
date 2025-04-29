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

#[Route(path: '/mission', name: 'sagit_mission_')]
final class MissionController extends AbstractController
{
    #[Route('/', name: 'index')]
    public function index(
        Request $request,
        MissionRepository $missionRepository,
        PaginatorInterface $paginator
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

        try {
            // Get missions query based on filters and user role
            $missionsQuery = $missionRepository->getFilteredMissionsQuery(
                $filters,
                $isAdmin ? null : $user->getId() // If not admin, see only own missions
            );

            // Paginate the results
            $missions = $paginator->paginate(
                $missionsQuery,
                $request->query->getInt('page', 1), // current page
                $this->getParameter('app.items_per_page') // items per page from env
            );
        } catch (\Exception $e) {
            // Handle exception, return empty result
            $this->addFlash('warning', 'An error occurred while fetching missions. ' . $e->getMessage());
            $missions = [];
        }

        return $this->render('mission/index.html.twig', [
            'controller_name' => 'MissionController',
            'missions' => $missions ?? [],
            'filter_form' => $filterForm,
            'no_results' => empty($missions) || count($missions) === 0
        ]);
    }

    #[Route('/new', name: 'new')]
    #[IsGranted('ROLE_CLIENT')]
    public function new(Request $request, MissionRepository $missionRepository): Response
    {
        $mission = new Mission();
        $form = $this->createForm(MissionType::class, $mission);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Save mission and set the current user as client
            $mission = $missionRepository->save($mission, $this->getUser());

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
    public function edit(Request $request, Mission $mission, MissionRepository $missionRepository): Response
    {
        // Check if current user owns this mission
        $this->denyAccessUnlessGranted('EDIT', $mission);

        $form = $this->createForm(MissionType::class, $mission);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Save mission (client already set for existing mission)
            $missionRepository->save($mission);

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
    public function delete(Request $request, Mission $mission, MissionRepository $missionRepository): Response
    {
        // Check if current user owns this mission or is an admin
        $this->denyAccessUnlessGranted('EDIT', $mission);

        // Check CSRF token for security
        $submittedToken = $request->request->get('token');
        if ($this->isCsrfTokenValid('delete-mission-' . $mission->getId(), $submittedToken)) {
            // Remove the mission
            $missionRepository->remove($mission);

            $this->addFlash('success', 'Mission deleted successfully');
        } else {
            $this->addFlash('error', 'Invalid security token');
        }

        return $this->redirectToRoute('sagit_mission_index');
    }
}
