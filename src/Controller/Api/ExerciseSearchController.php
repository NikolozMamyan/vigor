<?php

namespace App\Controller\Api;

use App\Service\Exercise\ExerciseCatalogService;
use App\Repository\UserProfileRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class ExerciseSearchController extends AbstractController
{
    #[Route('/api/exercises', name: 'api_exercises_search', methods: ['GET'])]
    public function __invoke(Request $request, ExerciseCatalogService $exerciseCatalogService): JsonResponse
    {
        $query = (string) $request->query->get('q', '');

        if (mb_strlen($query) > 80) {
            return $this->json([
                'error' => 'Query is too long.',
            ], JsonResponse::HTTP_BAD_REQUEST);
        }

        return $this->json([
            'query' => $query,
            'exercises' => $exerciseCatalogService->search($query),
        ]);
    }

    #[Route('/api/exercises/custom', name: 'api_exercises_create_custom', methods: ['POST'])]
    public function createCustom(
        Request $request,
        UserProfileRepository $profileRepository,
        ExerciseCatalogService $exerciseCatalogService,
    ): JsonResponse {
        $profile = $profileRepository->findOneBy(['username' => 'alexvigor']);

        if (!$profile) {
            return $this->json(['error' => 'Profile not found.'], JsonResponse::HTTP_NOT_FOUND);
        }

        try {
            $payload = json_decode($request->getContent() ?: '{}', true, 512, JSON_THROW_ON_ERROR);
            $exercise = $exerciseCatalogService->createCustom(
                $profile,
                (string) ($payload['name'] ?? ''),
                (string) ($payload['muscleGroup'] ?? ''),
                (string) ($payload['equipment'] ?? ''),
                isset($payload['imageUrl']) ? (string) $payload['imageUrl'] : null,
            );

            return $this->json($exerciseCatalogService->normalizeExercise($exercise), JsonResponse::HTTP_CREATED);
        } catch (\InvalidArgumentException|\JsonException $exception) {
            return $this->json(['error' => $exception->getMessage()], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }
    }
}
