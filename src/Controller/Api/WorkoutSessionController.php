<?php

namespace App\Controller\Api;

use App\Entity\WorkoutSession;
use App\Repository\ExerciseRepository;
use App\Repository\UserProfileRepository;
use App\Service\Workout\WorkoutSessionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class WorkoutSessionController extends AbstractController
{
    #[Route('/api/workout-sessions/free', name: 'api_workout_sessions_start_free', methods: ['POST'])]
    public function startFree(
        UserProfileRepository $profileRepository,
        ExerciseRepository $exerciseRepository,
        WorkoutSessionService $sessionService,
    ): JsonResponse {
        $profile = $profileRepository->findOneBy(['username' => 'alexvigor']);

        if (!$profile) {
            return $this->json(['error' => 'Profile not found.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $exercise = $exerciseRepository->findDefaultForProfile($profile);

        if (!$exercise) {
            return $this->json(['error' => 'No exercise available to start a free workout.'], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $session = $sessionService->startFree($profile, $exercise);

        return $this->json($this->normalizeSession($session), JsonResponse::HTTP_CREATED);
    }

    #[Route('/api/workout-sessions/{id}/complete', name: 'api_workout_sessions_complete', methods: ['POST'])]
    public function complete(WorkoutSession $session, WorkoutSessionService $sessionService): JsonResponse
    {
        try {
            $session = $sessionService->complete($session);

            return $this->json($this->normalizeSession($session));
        } catch (\InvalidArgumentException $exception) {
            return $this->json(['error' => $exception->getMessage()], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    #[Route('/api/workout-sessions/{id}/cancel', name: 'api_workout_sessions_cancel', methods: ['POST'])]
    public function cancel(WorkoutSession $session, WorkoutSessionService $sessionService): JsonResponse
    {
        try {
            $session = $sessionService->cancel($session);

            return $this->json($this->normalizeSession($session));
        } catch (\InvalidArgumentException $exception) {
            return $this->json(['error' => $exception->getMessage()], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    #[Route('/api/workout-sessions/{id}/exercises', name: 'api_workout_sessions_add_exercise', methods: ['POST'])]
    public function addExercise(
        WorkoutSession $session,
        Request $request,
        ExerciseRepository $exerciseRepository,
        WorkoutSessionService $sessionService,
    ): JsonResponse {
        try {
            $payload = json_decode($request->getContent() ?: '{}', true, 512, JSON_THROW_ON_ERROR);
            $exercise = $exerciseRepository->find((int) ($payload['exerciseId'] ?? 0));

            if (!$exercise) {
                return $this->json(['error' => 'Exercise not found.'], JsonResponse::HTTP_NOT_FOUND);
            }

            $sessionExercise = $sessionService->addExercise($session, $exercise);

            return $this->json([
                'id' => $sessionExercise->getId(),
                'sessionId' => $session->getId(),
                'exerciseId' => $exercise->getId(),
                'exerciseName' => $exercise->getName(),
                'muscleGroup' => $exercise->getMuscleGroup(),
                'position' => $sessionExercise->getPosition(),
            ], JsonResponse::HTTP_CREATED);
        } catch (\InvalidArgumentException|\JsonException $exception) {
            return $this->json(['error' => $exception->getMessage()], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeSession(WorkoutSession $session): array
    {
        return [
            'id' => $session->getId(),
            'status' => $session->getStatus(),
            'completedAt' => $session->getCompletedAt()?->format(\DateTimeInterface::ATOM),
            'durationSeconds' => $session->getDurationSeconds(),
        ];
    }
}
