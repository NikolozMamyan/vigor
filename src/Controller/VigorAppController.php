<?php

namespace App\Controller;

use App\Service\Dashboard\DashboardService;
use App\Service\Exercise\ExerciseCatalogService;
use App\Service\Workout\ActiveWorkoutViewService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class VigorAppController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    #[Route('/app/{view}', name: 'vigor_app', requirements: ['view' => 'home|workout|library|profile'], defaults: ['view' => 'home'])]
    public function __invoke(DashboardService $dashboardService, ExerciseCatalogService $exerciseCatalogService, ActiveWorkoutViewService $activeWorkoutViewService, string $view = 'home'): Response
    {
        $dashboard = $dashboardService->build();
        $exerciseCatalog = $exerciseCatalogService->build();
        $activeWorkout = $activeWorkoutViewService->build();

        return $this->render('vigor_app/index.html.twig', [
            'activeView' => $view,
            'dashboard' => $dashboard,
            'exerciseCatalog' => $exerciseCatalog,
            'activeWorkout' => $activeWorkout,
            'navItems' => [
                ['id' => 'home', 'label' => 'Accueil', 'icon' => 'layout-grid'],
                ['id' => 'workout', 'label' => 'Seance', 'icon' => 'play'],
                ['id' => 'library', 'label' => 'Bibliotheque', 'icon' => 'search'],
                ['id' => 'profile', 'label' => 'Profil', 'icon' => 'user'],
            ],
            'profileStats' => [
                ['label' => 'Seances', 'value' => '147', 'icon' => 'calendar-check'],
                ['label' => 'Volume total', 'value' => '428', 'suffix' => 'T', 'icon' => 'dumbbell'],
                ['label' => 'Records battus', 'value' => '23', 'icon' => 'trophy'],
                ['label' => 'Plus longue serie', 'value' => '28', 'suffix' => 'jours', 'icon' => 'flame'],
            ],
            'badges' => [
                ['label' => 'Marathon', 'icon' => 'flame', 'locked' => false],
                ['label' => 'Eclair', 'icon' => 'zap', 'locked' => false],
                ['label' => 'Champion', 'icon' => 'trophy', 'locked' => false],
                ['label' => 'Discipline', 'icon' => 'medal', 'locked' => false],
                ['label' => 'Etoile', 'icon' => 'star', 'locked' => false],
                ['label' => '???', 'icon' => 'lock', 'locked' => true],
                ['label' => '???', 'icon' => 'lock', 'locked' => true],
                ['label' => '???', 'icon' => 'lock', 'locked' => true],
            ],
            'profileSettings' => [
                ['label' => 'Preferences', 'description' => 'Unites, theme, langue', 'icon' => 'settings'],
                ['label' => 'Notifications', 'description' => 'Rappels, rapports hebdo', 'icon' => 'bell'],
                ['label' => 'Confidentialite', 'description' => 'Donnees, partage', 'icon' => 'shield-check'],
                ['label' => 'Aide & support', 'description' => 'FAQ, nous contacter', 'icon' => 'help-circle'],
            ],
        ]);
    }
}
