<?php

namespace App\Controller\Api;

use App\Service\Exercise\ExerciseCatalogService;
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
}
