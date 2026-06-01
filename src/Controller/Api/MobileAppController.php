<?php

namespace App\Controller\Api;

use App\Service\Auth\CurrentUserProfileProvider;
use App\Service\Dashboard\DashboardService;
use App\Service\Exercise\ExerciseCatalogService;
use App\Service\Profile\ProfileStatsService;
use App\Service\Records\RecordsViewService;
use App\Service\Stats\StatsAnalyticsService;
use App\Service\Workout\ActiveWorkoutViewService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class MobileAppController extends AbstractController
{
    #[Route('/api/mobile/bootstrap', name: 'api_mobile_bootstrap', methods: ['GET'])]
    public function bootstrap(
        DashboardService $dashboardService,
        ExerciseCatalogService $exerciseCatalogService,
        ActiveWorkoutViewService $activeWorkoutViewService,
        ProfileStatsService $profileStatsService,
        RecordsViewService $recordsViewService,
        StatsAnalyticsService $statsAnalyticsService,
        CurrentUserProfileProvider $currentUser,
        Request $request,
    ): JsonResponse {
        $profile = $currentUser->getProfile();

        if (!$profile) {
            return $this->json(['error' => 'Authentication required.'], JsonResponse::HTTP_UNAUTHORIZED);
        }

        $statsPeriod = (string) $request->query->get('period', 'week');
        $statsPeriod = \in_array($statsPeriod, ['week', 'month', 'quarter'], true) ? $statsPeriod : 'week';
        $recordsView = $recordsViewService->build();
        $profileView = $profileStatsService->build();

        return $this->json([
            'profile' => [
                'id' => $profile->getId(),
                'displayName' => $profile->getDisplayName(),
                'username' => $profile->getUsername(),
                'email' => $profile->getEmail(),
            ],
            'navItems' => [
                ['id' => 'home', 'label' => 'Accueil', 'icon' => 'layout-grid'],
                ['id' => 'workout', 'label' => 'Seance', 'icon' => 'play'],
                ['id' => 'library', 'label' => 'Bibliotheque', 'icon' => 'search'],
                ['id' => 'stats', 'label' => 'Stats', 'icon' => 'bar-chart-2'],
                ['id' => 'records', 'label' => 'Records', 'icon' => 'trophy'],
            ],
            'dashboard' => $dashboardService->build(),
            'exerciseCatalog' => $exerciseCatalogService->build(),
            'activeWorkout' => $activeWorkoutViewService->build((int) $request->query->get('exercise', 0)),
            'profileView' => $profileView,
            'profileStats' => $profileView['stats'] ?? [],
            'badges' => $profileView['badges'] ?? [],
            'profileSettings' => $profileView['settings'] ?? [],
            'statsAnalytics' => $statsAnalyticsService->build($statsPeriod),
            'recordsView' => $recordsView + [
                'recordsById' => json_decode((string) ($recordsView['recordsJson'] ?? '{}'), true) ?: [],
            ],
        ]);
    }
}
