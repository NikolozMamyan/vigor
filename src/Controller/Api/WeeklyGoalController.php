<?php

namespace App\Controller\Api;

use App\Service\Auth\CurrentUserProfileProvider;
use App\Service\Dashboard\WeeklyGoalService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class WeeklyGoalController extends AbstractController
{
    #[Route('/api/profile/weekly-goal', name: 'api_profile_weekly_goal_update', methods: ['PATCH'])]
    public function update(Request $request, CurrentUserProfileProvider $currentUser, WeeklyGoalService $weeklyGoalService): JsonResponse
    {
        try {
            $profile = $currentUser->getProfile();

            if (!$profile) {
                return $this->json(['error' => 'Authentication required.'], JsonResponse::HTTP_UNAUTHORIZED);
            }

            $payload = json_decode($request->getContent() ?: '{}', true, 512, \JSON_THROW_ON_ERROR);
            $weeklyGoal = $weeklyGoalService->updateCurrentWeek(
                $profile,
                (int) ($payload['targetWorkouts'] ?? 0),
                (int) ($payload['targetVolume'] ?? 0),
                (int) ($payload['targetTrainingMinutes'] ?? 0),
            );

            return $this->json(['weeklyGoal' => $weeklyGoal]);
        } catch (\InvalidArgumentException|\JsonException $exception) {
            return $this->json(['error' => $exception->getMessage()], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }
    }
}
