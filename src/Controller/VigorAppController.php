<?php

namespace App\Controller;

use App\Service\Dashboard\DashboardService;
use App\Service\Exercise\ExerciseCatalogService;
use App\Service\Auth\CurrentUserProfileProvider;
use App\Service\Profile\ProfileStatsService;
use App\Service\Records\RecordsViewService;
use App\Service\Stats\StatsAnalyticsService;
use App\Service\Workout\ActiveWorkoutViewService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class VigorAppController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    #[Route('/app/{view}', name: 'vigor_app', requirements: ['view' => 'home|workout|library|stats|records|profile'], defaults: ['view' => 'home'])]
    public function __invoke(
        DashboardService $dashboardService,
        ExerciseCatalogService $exerciseCatalogService,
        ActiveWorkoutViewService $activeWorkoutViewService,
        ProfileStatsService $profileStatsService,
        RecordsViewService $recordsViewService,
        StatsAnalyticsService $statsAnalyticsService,
        CurrentUserProfileProvider $currentUser,
        Request $request,
        string $view = 'home',
    ): Response {
        if (!$currentUser->getProfile()) {
            return $this->redirectToRoute('auth_login');
        }

        $dashboard = $dashboardService->build();
        $exerciseCatalog = $exerciseCatalogService->build();
        $activeWorkout = $activeWorkoutViewService->build((int) $request->query->get('exercise', 0));
        $profileView = $profileStatsService->build();
        $statsPeriod = $request->query->get('period', 'week');
        $statsPeriod = \in_array($statsPeriod, ['week', 'month', 'quarter'], true) ? $statsPeriod : 'week';

        return $this->render('vigor_app/index.html.twig', [
            'activeView' => $view,
            'dashboard' => $dashboard,
            'exerciseCatalog' => $exerciseCatalog,
            'activeWorkout' => $activeWorkout,
            'navItems' => [
                ['id' => 'home', 'label' => 'Accueil', 'icon' => 'layout-grid'],
                ['id' => 'workout', 'label' => 'Seance', 'icon' => 'play'],
                ['id' => 'library', 'label' => 'Exercices', 'icon' => 'search'],
                ['id' => 'stats', 'label' => 'Stats', 'icon' => 'bar-chart-2'],
                ['id' => 'records', 'label' => 'Records', 'icon' => 'trophy'],
            ],
            'profileView' => $profileView,
            'profileStats' => $profileView['stats'],
            'badges' => $profileView['badges'],
            'profileSettings' => $profileView['settings'],
            'statsAnalytics' => $statsAnalyticsService->build($statsPeriod),
            'recordsView' => $recordsViewService->build(),
        ]);
    }
}
