<?php

namespace App\Controller;

use App\Service\Dashboard\DashboardService;
use App\Service\Exercise\ExerciseCatalogService;
use App\Service\Profile\ProfileStatsService;
use App\Service\Workout\ActiveWorkoutViewService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class VigorAppController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    #[Route('/app/{view}', name: 'vigor_app', requirements: ['view' => 'home|workout|library|stats|profile'], defaults: ['view' => 'home'])]
    public function __invoke(
        DashboardService $dashboardService,
        ExerciseCatalogService $exerciseCatalogService,
        ActiveWorkoutViewService $activeWorkoutViewService,
        ProfileStatsService $profileStatsService,
        Request $request,
        string $view = 'home',
    ): Response {
        $dashboard = $dashboardService->build();
        $exerciseCatalog = $exerciseCatalogService->build();
        $activeWorkout = $activeWorkoutViewService->build((int) $request->query->get('exercise', 0));
        $profileView = $profileStatsService->build();

        return $this->render('vigor_app/index.html.twig', [
            'activeView' => $view,
            'dashboard' => $dashboard,
            'exerciseCatalog' => $exerciseCatalog,
            'activeWorkout' => $activeWorkout,
            'navItems' => [
                ['id' => 'home', 'label' => 'Accueil', 'icon' => 'layout-grid'],
                ['id' => 'workout', 'label' => 'Seance', 'icon' => 'play'],
                ['id' => 'library', 'label' => 'Bibliotheque', 'icon' => 'search'],
                ['id' => 'stats', 'label' => 'Stats', 'icon' => 'bar-chart-2'],
            ],
            'profileView' => $profileView,
            'profileStats' => $profileView['stats'],
            'badges' => $profileView['badges'],
            'profileSettings' => $profileView['settings'],
        ]);
    }
}
