<?php

namespace App\Controller\Api;

use App\Entity\WorkoutSessionExercise;
use App\Entity\WorkoutSet;
use App\Service\Workout\WorkoutSetService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class WorkoutSetController extends AbstractController
{
    #[Route('/api/workout-sets/{id}', name: 'api_workout_sets_update', methods: ['PATCH'])]
    public function update(WorkoutSet $set, Request $request, WorkoutSetService $setService): JsonResponse
    {
        try {
            $payload = $this->payload($request);
            $set = $setService->update($set, (float) $payload['weight'], (int) $payload['reps']);

            return $this->json($this->normalizeSet($set));
        } catch (\InvalidArgumentException|\JsonException $exception) {
            return $this->json(['error' => $exception->getMessage()], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    #[Route('/api/workout-sets/{id}/complete', name: 'api_workout_sets_complete', methods: ['POST'])]
    public function complete(WorkoutSet $set, Request $request, WorkoutSetService $setService): JsonResponse
    {
        try {
            $payload = $this->payload($request, false);

            if (isset($payload['weight'], $payload['reps'])) {
                $setService->update($set, (float) $payload['weight'], (int) $payload['reps']);
            }

            $result = $setService->complete($set);

            return $this->json($this->normalizeSet($result['set']) + [
                'estimatedOneRepMax' => $result['estimatedOneRepMax'],
                'recordCreated' => $result['recordCreated'],
                'recordValue' => $result['recordValue'],
            ]);
        } catch (\InvalidArgumentException|\JsonException $exception) {
            return $this->json(['error' => $exception->getMessage()], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    #[Route('/api/workout-sets/{id}', name: 'api_workout_sets_delete', methods: ['DELETE'])]
    public function delete(WorkoutSet $set, WorkoutSetService $setService): JsonResponse
    {
        $setService->delete($set);

        return $this->json(null, JsonResponse::HTTP_NO_CONTENT);
    }

    #[Route('/api/workout-session-exercises/{id}/sets', name: 'api_workout_sets_create', methods: ['POST'])]
    public function create(WorkoutSessionExercise $sessionExercise, Request $request, WorkoutSetService $setService): JsonResponse
    {
        try {
            $payload = $this->payload($request);
            $position = isset($payload['position']) ? (int) $payload['position'] : $setService->nextPosition($sessionExercise);
            $set = $setService->create($sessionExercise, $position, (float) $payload['weight'], (int) $payload['reps']);

            return $this->json($this->normalizeSet($set), JsonResponse::HTTP_CREATED);
        } catch (\InvalidArgumentException|\JsonException $exception) {
            return $this->json(['error' => $exception->getMessage()], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Request $request, bool $requireSetData = true): array
    {
        $payload = json_decode($request->getContent() ?: '{}', true, 512, JSON_THROW_ON_ERROR);

        if ($requireSetData && (!isset($payload['weight'], $payload['reps']))) {
            throw new \InvalidArgumentException('Weight and reps are required.');
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeSet(WorkoutSet $set): array
    {
        return [
            'id' => $set->getId(),
            'position' => $set->getPosition(),
            'weight' => $set->getWeight(),
            'reps' => $set->getReps(),
            'completed' => null !== $set->getCompletedAt(),
            'estimatedOneRepMax' => $set->getEstimatedOneRepMax(),
        ];
    }
}
