<?php

namespace App\Controller\Api;

use App\Entity\WorkoutProgram;
use App\Repository\ExerciseRepository;
use App\Repository\UserProfileRepository;
use App\Repository\WorkoutProgramExerciseRepository;
use App\Service\Workout\WorkoutProgramService;
use App\Service\Workout\WorkoutSessionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class WorkoutProgramController extends AbstractController
{
    #[Route('/api/workout-programs', name: 'api_workout_programs_create', methods: ['POST'])]
    public function create(
        Request $request,
        UserProfileRepository $profileRepository,
        ExerciseRepository $exerciseRepository,
        WorkoutProgramService $programService,
        WorkoutProgramExerciseRepository $programExerciseRepository,
    ): JsonResponse {
        try {
            $profile = $profileRepository->findOneBy(['username' => 'alexvigor']);

            if (!$profile) {
                return $this->json(['error' => 'Profile not found.'], JsonResponse::HTTP_NOT_FOUND);
            }

            $exercise = $exerciseRepository->findDefaultForProfile($profile);

            if (!$exercise) {
                return $this->json(['error' => 'No exercise available for a new program.'], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
            }

            $payload = json_decode($request->getContent() ?: '{}', true, 512, JSON_THROW_ON_ERROR);
            $program = $programService->create(
                $profile,
                $exercise,
                (string) ($payload['name'] ?? 'Mon programme'),
                $this->resolveExerciseConfigs($payload['exercises'] ?? [], $exerciseRepository),
            );

            return $this->json($this->normalizeProgram($program, $programExerciseRepository), JsonResponse::HTTP_CREATED);
        } catch (\InvalidArgumentException|\JsonException $exception) {
            return $this->json(['error' => $exception->getMessage()], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    #[Route('/api/workout-programs/{id}', name: 'api_workout_programs_delete', methods: ['DELETE'])]
    public function delete(WorkoutProgram $program, WorkoutProgramService $programService): JsonResponse
    {
        $programService->delete($program);

        return $this->json(null, JsonResponse::HTTP_NO_CONTENT);
    }

    #[Route('/api/workout-programs/{id}/start', name: 'api_workout_programs_start', methods: ['POST'])]
    #[Route('/api/workout-sessions/from-program/{id}', name: 'api_workout_sessions_start_from_program', methods: ['POST'])]
    public function start(WorkoutProgram $program, WorkoutSessionService $sessionService): JsonResponse
    {
        try {
            $session = $sessionService->startProgram($program);

            return $this->json([
                'id' => $session->getId(),
                'status' => $session->getStatus(),
                'type' => $session->getType(),
            ], JsonResponse::HTTP_CREATED);
        } catch (\InvalidArgumentException $exception) {
            return $this->json(['error' => $exception->getMessage()], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeProgram(WorkoutProgram $program, WorkoutProgramExerciseRepository $programExerciseRepository): array
    {
        $exercises = $programExerciseRepository->findForProgram($program);

        return [
            'id' => $program->getId(),
            'name' => $program->getName(),
            'description' => $program->getDescription(),
            'exerciseCount' => count($exercises),
        ];
    }

    /**
     * @param mixed $exercisePayload
     *
     * @return list<array<string, mixed>>
     */
    private function resolveExerciseConfigs(mixed $exercisePayload, ExerciseRepository $exerciseRepository): array
    {
        if (!is_array($exercisePayload) || [] === $exercisePayload) {
            return [];
        }

        $configs = [];

        foreach ($exercisePayload as $index => $item) {
            if (!is_array($item)) {
                throw new \InvalidArgumentException('Exercise configuration is invalid.');
            }

            $exerciseId = (int) ($item['exerciseId'] ?? 0);
            $exercise = $exerciseRepository->find($exerciseId);

            if (!$exercise) {
                throw new \InvalidArgumentException(sprintf('Exercise #%d was not found.', $index + 1));
            }

            $configs[] = [
                'exercise' => $exercise,
                'targetSets' => (int) ($item['targetSets'] ?? 3),
                'targetRepsMin' => (int) ($item['targetRepsMin'] ?? 8),
                'targetRepsMax' => (int) ($item['targetRepsMax'] ?? ($item['targetRepsMin'] ?? 8)),
                'targetWeight' => isset($item['targetWeight']) && '' !== $item['targetWeight'] ? (float) $item['targetWeight'] : null,
                'restSeconds' => (int) ($item['restSeconds'] ?? 90),
            ];
        }

        return $configs;
    }
}
