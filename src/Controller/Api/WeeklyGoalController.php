<?php

namespace App\Controller\Api;

use App\Repository\UserProfileRepository;
use App\Service\Dashboard\WeeklyGoalService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class WeeklyGoalController extends AbstractController
{
    #[Route('/api/profile/weekly-goal', name: 'api_profile_weekly_goal_update', methods: ['PATCH'])]
    public function update(Request $request, UserProfileRepository $profileRepository, WeeklyGoalService $weeklyGoalService): JsonResponse
    {
        try {
            $profile = $profileRepository->findOneBy(['username' => 'alexvigor']);

            if (!$profile) {
                return $this->json(['error' => 'Profile not found.'], JsonResponse::HTTP_NOT_FOUND);
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
