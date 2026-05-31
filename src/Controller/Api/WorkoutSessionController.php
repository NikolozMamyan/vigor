<?php

namespace App\Controller\Api;

use App\Entity\UserProfile;
use App\Entity\WorkoutSession;
use App\Entity\WorkoutSessionExercise;
use App\Repository\ExerciseRepository;
use App\Repository\WorkoutSessionExerciseRepository;
use App\Repository\WorkoutSetRepository;
use App\Service\Auth\CurrentUserProfileProvider;
use App\Service\Workout\PreviousExercisePerformanceService;
use App\Service\Workout\WorkoutSessionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class WorkoutSessionController extends AbstractController
{
    #[Route('/api/workout-sessions/free', name: 'api_workout_sessions_start_free', methods: ['POST'])]
    public function startFree(
        CurrentUserProfileProvider $currentUser,
        ExerciseRepository $exerciseRepository,
        WorkoutSessionService $sessionService,
    ): JsonResponse {
        $profile = $currentUser->getProfile();

        if (!$profile) {
            return $this->json(['error' => 'Authentication required.'], JsonResponse::HTTP_UNAUTHORIZED);
        }

        $exercise = $exerciseRepository->findDefaultForProfile($profile);

        if (!$exercise) {
            return $this->json(['error' => 'No exercise available to start a free workout.'], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $session = $sessionService->startFree($profile, $exercise);

        return $this->json($this->normalizeSession($session), JsonResponse::HTTP_CREATED);
    }

    #[Route('/api/workout-sessions/{id}', name: 'api_workout_sessions_show', methods: ['GET'])]
    public function show(
        WorkoutSession $session,
        CurrentUserProfileProvider $currentUser,
        WorkoutSessionExerciseRepository $sessionExerciseRepository,
        WorkoutSetRepository $setRepository,
    ): JsonResponse {
        if (!$this->ownsSession($session, $currentUser->getProfile())) {
            return $this->json(['error' => 'Workout session not found.'], JsonResponse::HTTP_NOT_FOUND);
        }

        return $this->json($this->normalizeSessionDetail($session, $sessionExerciseRepository, $setRepository));
    }

    #[Route('/api/workout-sessions/{id}', name: 'api_workout_sessions_delete', methods: ['DELETE'])]
    public function deleteHistory(
        WorkoutSession $session,
        CurrentUserProfileProvider $currentUser,
        WorkoutSessionService $sessionService,
    ): JsonResponse {
        try {
            if (!$this->ownsSession($session, $currentUser->getProfile())) {
                return $this->json(['error' => 'Workout session not found.'], JsonResponse::HTTP_NOT_FOUND);
            }

            $sessionService->deleteHistorySession($session);

            return $this->json(null, JsonResponse::HTTP_NO_CONTENT);
        } catch (\InvalidArgumentException $exception) {
            return $this->json(['error' => $exception->getMessage()], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    #[Route('/api/workout-sessions/{id}/complete', name: 'api_workout_sessions_complete', methods: ['POST'])]
    public function complete(
        WorkoutSession $session,
        CurrentUserProfileProvider $currentUser,
        WorkoutSessionService $sessionService,
    ): JsonResponse
    {
        try {
            if (!$this->ownsSession($session, $currentUser->getProfile())) {
                return $this->json(['error' => 'Workout session not found.'], JsonResponse::HTTP_NOT_FOUND);
            }

            $session = $sessionService->complete($session);

            return $this->json($this->normalizeSession($session));
        } catch (\InvalidArgumentException $exception) {
            return $this->json(['error' => $exception->getMessage()], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    #[Route('/api/workout-sessions/{id}/cancel', name: 'api_workout_sessions_cancel', methods: ['POST'])]
    public function cancel(
        WorkoutSession $session,
        CurrentUserProfileProvider $currentUser,
        WorkoutSessionService $sessionService,
    ): JsonResponse
    {
        try {
            if (!$this->ownsSession($session, $currentUser->getProfile())) {
                return $this->json(['error' => 'Workout session not found.'], JsonResponse::HTTP_NOT_FOUND);
            }

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
        CurrentUserProfileProvider $currentUser,
        ExerciseRepository $exerciseRepository,
        WorkoutSessionService $sessionService,
        PreviousExercisePerformanceService $previousPerformanceService,
    ): JsonResponse {
        try {
            $profile = $currentUser->getProfile();

            if (!$this->ownsSession($session, $profile)) {
                return $this->json(['error' => 'Workout session not found.'], JsonResponse::HTTP_NOT_FOUND);
            }

            $payload = json_decode($request->getContent() ?: '{}', true, 512, JSON_THROW_ON_ERROR);
            $exercise = $exerciseRepository->find((int) ($payload['exerciseId'] ?? 0));

            if (!$exercise) {
                return $this->json(['error' => 'Exercise not found.'], JsonResponse::HTTP_NOT_FOUND);
            }

            $sessionExercise = $sessionService->addExercise($session, $exercise);
            $previousPerformance = $previousPerformanceService->forSessionExercise($profile, $sessionExercise);
            $previousSetByPosition = $this->previousSetByPosition($previousPerformance);

            return $this->json([
                'id' => $sessionExercise->getId(),
                'sessionId' => $session->getId(),
                'exerciseId' => $exercise->getId(),
                'exerciseName' => $exercise->getName(),
                'muscleGroup' => $exercise->getMuscleGroup(),
                'equipment' => $exercise->getEquipment(),
                'image' => $exercise->getImageUrl() ?? 'https://placehold.co/900x700/18181b/ccff00?text=VIGOR',
                'restSeconds' => $sessionExercise->getRestSeconds(),
                'previousPerformance' => $previousPerformance,
                'sets' => $this->placeholderSets($sessionExercise, $previousSetByPosition),
                'position' => $sessionExercise->getPosition(),
            ], JsonResponse::HTTP_CREATED);
        } catch (\InvalidArgumentException|\JsonException $exception) {
            return $this->json(['error' => $exception->getMessage()], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    #[Route('/api/workout-sessions/{id}/history-exercises', name: 'api_workout_sessions_add_history_exercise', methods: ['POST'])]
    public function addHistoryExercise(
        WorkoutSession $session,
        Request $request,
        CurrentUserProfileProvider $currentUser,
        ExerciseRepository $exerciseRepository,
        WorkoutSessionService $sessionService,
        WorkoutSessionExerciseRepository $sessionExerciseRepository,
        WorkoutSetRepository $setRepository,
    ): JsonResponse {
        try {
            if (!$this->ownsSession($session, $currentUser->getProfile())) {
                return $this->json(['error' => 'Workout session not found.'], JsonResponse::HTTP_NOT_FOUND);
            }

            $payload = json_decode($request->getContent() ?: '{}', true, 512, JSON_THROW_ON_ERROR);
            $exercise = $exerciseRepository->find((int) ($payload['exerciseId'] ?? 0));

            if (!$exercise) {
                return $this->json(['error' => 'Exercise not found.'], JsonResponse::HTTP_NOT_FOUND);
            }

            $sessionService->addHistoryExercise($session, $exercise);

            return $this->json($this->normalizeSessionDetail($session, $sessionExerciseRepository, $setRepository), JsonResponse::HTTP_CREATED);
        } catch (\InvalidArgumentException|\JsonException $exception) {
            return $this->json(['error' => $exception->getMessage()], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    #[Route('/api/workout-session-exercises/{id}', name: 'api_workout_session_exercises_show', methods: ['GET'])]
    public function showSessionExercise(
        WorkoutSessionExercise $sessionExercise,
        CurrentUserProfileProvider $currentUser,
        WorkoutSetRepository $setRepository,
        PreviousExercisePerformanceService $previousPerformanceService,
    ): JsonResponse
    {
        $profile = $currentUser->getProfile();

        if (!$this->ownsSessionExercise($sessionExercise, $profile)) {
            return $this->json(['error' => 'Session exercise not found.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $exercise = $sessionExercise->getExercise();
        $sets = $setRepository->findForSessionExercise($sessionExercise);
        $previousPerformance = $previousPerformanceService->forSessionExercise($profile, $sessionExercise);
        $previousSetByPosition = $this->previousSetByPosition($previousPerformance);

        return $this->json([
            'id' => $sessionExercise->getId(),
            'sessionId' => $sessionExercise->getSession()->getId(),
            'exerciseId' => $exercise->getId(),
            'exerciseName' => $exercise->getName(),
            'muscleGroup' => $exercise->getMuscleGroup(),
            'equipment' => $exercise->getEquipment(),
            'image' => $exercise->getImageUrl() ?? 'https://placehold.co/900x700/18181b/ccff00?text=VIGOR',
            'restSeconds' => $sessionExercise->getRestSeconds(),
            'targetLabel' => $this->targetLabel($sessionExercise),
            'previousPerformance' => $previousPerformance,
            'sets' => [] === $sets ? $this->placeholderSets($sessionExercise, $previousSetByPosition) : array_map(fn ($set): array => [
                'id' => $set->getId(),
                'sessionExerciseId' => $sessionExercise->getId(),
                'number' => $set->getPosition(),
                'previous' => $set->getWeight() > 0 && $set->getReps() > 0 ? $set->getWeight().'kg x '.$set->getReps() : 'A completer',
                'weight' => $set->getWeight() > 0 ? $set->getWeight() : null,
                'reps' => $set->getReps() > 0 ? $set->getReps() : null,
                'completed' => null !== $set->getCompletedAt(),
                'previousSet' => $previousSetByPosition[$set->getPosition()] ?? $this->emptyPreviousSet($set->getPosition()),
            ], $sets),
        ]);
    }

    #[Route('/api/workout-session-exercises/{id}', name: 'api_workout_session_exercises_delete', methods: ['DELETE'])]
    public function deleteSessionExercise(
        WorkoutSessionExercise $sessionExercise,
        CurrentUserProfileProvider $currentUser,
        WorkoutSessionService $sessionService,
    ): JsonResponse
    {
        try {
            if (!$this->ownsSessionExercise($sessionExercise, $currentUser->getProfile())) {
                return $this->json(['error' => 'Session exercise not found.'], JsonResponse::HTTP_NOT_FOUND);
            }

            $sessionService->removeExercise($sessionExercise);

            return $this->json(null, JsonResponse::HTTP_NO_CONTENT);
        } catch (\InvalidArgumentException $exception) {
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

    /**
     * @return array<string, mixed>
     */
    private function normalizeSessionDetail(
        WorkoutSession $session,
        WorkoutSessionExerciseRepository $sessionExerciseRepository,
        WorkoutSetRepository $setRepository,
    ): array {
        $sessionExercises = $sessionExerciseRepository->findForSession($session);
        $totalSets = 0;
        $totalVolume = 0.0;
        $exercises = [];

        foreach ($sessionExercises as $sessionExercise) {
            $sets = $setRepository->findForSessionExercise($sessionExercise);
            $totalSets += count($sets);

            foreach ($sets as $set) {
                $totalVolume += $set->getVolume();
            }

            $exercise = $sessionExercise->getExercise();
            $exercises[] = [
                'id' => $sessionExercise->getId(),
                'exerciseId' => $exercise->getId(),
                'name' => $exercise->getName(),
                'muscleGroup' => $exercise->getMuscleGroup(),
                'image' => $exercise->getImageUrl() ?? 'https://placehold.co/160x160/18181b/ccff00?text=VIGOR',
                'targetLabel' => $this->targetLabel($sessionExercise),
                'sets' => array_map(fn ($set): array => [
                    'id' => $set->getId(),
                    'position' => $set->getPosition(),
                    'weight' => $set->getWeight(),
                    'reps' => $set->getReps(),
                    'completed' => null !== $set->getCompletedAt(),
                    'estimatedOneRepMax' => $set->getEstimatedOneRepMax(),
                ], $sets),
            ];
        }

        return [
            'id' => $session->getId(),
            'name' => $session->getName(),
            'type' => $session->getType(),
            'status' => $session->getStatus(),
            'completedAt' => $session->getCompletedAt()?->format(\DateTimeInterface::ATOM),
            'dateLabel' => ($session->getCompletedAt() ?? $session->getStartedAt())->format('d/m/Y'),
            'timeLabel' => ($session->getCompletedAt() ?? $session->getStartedAt())->format('H:i'),
            'durationLabel' => $this->durationLabel($session->getDurationSeconds()),
            'exerciseCount' => count($sessionExercises),
            'setCount' => $totalSets,
            'volume' => $totalVolume,
            'volumeLabel' => $this->formatNumber($totalVolume).' kg',
            'exercises' => $exercises,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function placeholderSets(WorkoutSessionExercise $sessionExercise, array $previousSetByPosition = []): array
    {
        $sets = [];

        for ($position = 1; $position <= ($sessionExercise->getTargetSets() ?? 3); ++$position) {
            $sets[] = [
                'id' => null,
                'sessionExerciseId' => $sessionExercise->getId(),
                'number' => $position,
                'previous' => 'A completer',
                'weight' => null,
                'reps' => null,
                'completed' => false,
                'previousSet' => $previousSetByPosition[$position] ?? $this->emptyPreviousSet($position),
            ];
        }

        return $sets;
    }

    /**
     * @param array<string, mixed> $previousPerformance
     *
     * @return array<int, array<string, mixed>>
     */
    private function previousSetByPosition(array $previousPerformance): array
    {
        $previousSetByPosition = [];

        foreach ($previousPerformance['sets'] ?? [] as $set) {
            $position = (int) ($set['position'] ?? 0);

            if ($position <= 0) {
                continue;
            }

            $previousSetByPosition[$position] = $set + ['hasData' => true];
        }

        return $previousSetByPosition;
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyPreviousSet(int $position): array
    {
        return [
            'hasData' => false,
            'position' => $position,
            'label' => 'Aucun repere',
            'volume' => '0',
        ];
    }

    private function targetLabel(WorkoutSessionExercise $sessionExercise): string
    {
        $sets = $sessionExercise->getTargetSets() ?? 3;
        $min = $sessionExercise->getTargetRepsMin();
        $max = $sessionExercise->getTargetRepsMax();

        if ($min && $max && $min !== $max) {
            return sprintf('%d x %d-%d', $sets, $min, $max);
        }

        return $min ? sprintf('%d x %d', $sets, $min) : sprintf('%d series', $sets);
    }

    private function ownsSessionExercise(WorkoutSessionExercise $sessionExercise, ?UserProfile $profile): bool
    {
        return $this->ownsSession($sessionExercise->getSession(), $profile);
    }

    private function ownsSession(WorkoutSession $session, ?UserProfile $profile): bool
    {
        if (!$profile) {
            return false;
        }

        $sessionProfileId = $session->getProfile()->getId();
        $currentProfileId = $profile->getId();

        if (null === $sessionProfileId || null === $currentProfileId) {
            return $session->getProfile() === $profile;
        }

        return $sessionProfileId === $currentProfileId;
    }

    private function durationLabel(?int $seconds): string
    {
        if (!$seconds || $seconds <= 0) {
            return '0 min';
        }

        $minutes = (int) ceil($seconds / 60);

        if ($minutes < 60) {
            return sprintf('%d min', $minutes);
        }

        return sprintf('%dh%02d', intdiv($minutes, 60), $minutes % 60);
    }

    private function formatNumber(float $value): string
    {
        $rounded = round($value, 1);

        return 0.0 === fmod($rounded, 1.0) ? (string) (int) $rounded : number_format($rounded, 1, '.', '');
    }
}
