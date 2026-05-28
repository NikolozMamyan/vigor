<?php

namespace App\Controller\Admin;

use App\Entity\Exercise;
use App\Repository\ExerciseRepository;
use App\Service\Admin\AdminExerciseService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/exercises')]
final class ExerciseAdminController extends AbstractController
{
    #[Route('', name: 'admin_exercises_index', methods: ['GET'])]
    public function index(ExerciseRepository $exerciseRepository): Response
    {
        $exercises = $exerciseRepository->findBy([], ['name' => 'ASC']);

        return $this->render('admin/exercises/index.html.twig', [
            'exercises' => $exercises,
            'adminStats' => $this->buildStats($exercises),
            'exercise' => new Exercise(),
            'mode' => 'create',
            'error' => null,
        ]);
    }

    #[Route('/new', name: 'admin_exercises_create', methods: ['POST'])]
    public function create(Request $request, ExerciseRepository $exerciseRepository, AdminExerciseService $exerciseService): Response
    {
        $exercise = new Exercise();

        try {
            if (!$this->isCsrfTokenValid('admin_exercise', (string) $request->request->get('_token'))) {
                throw new \InvalidArgumentException('Token CSRF invalide.');
            }

            $exerciseService->save($exercise, $request->request->all());
            $this->addFlash('success', 'Exercice cree.');

            return $this->redirectToRoute('admin_exercises_index');
        } catch (\InvalidArgumentException $exception) {
            $exercises = $exerciseRepository->findBy([], ['name' => 'ASC']);

            return $this->render('admin/exercises/index.html.twig', [
                'exercises' => $exercises,
                'adminStats' => $this->buildStats($exercises),
                'exercise' => $exercise,
                'mode' => 'create',
                'error' => $exception->getMessage(),
                'formData' => $request->request->all(),
            ], new Response('', Response::HTTP_UNPROCESSABLE_ENTITY));
        }
    }

    #[Route('/{id}/edit', name: 'admin_exercises_edit', methods: ['GET'])]
    public function edit(int $id, ExerciseRepository $exerciseRepository): Response
    {
        $exercise = $exerciseRepository->find($id);
        if (!$exercise instanceof Exercise) {
            throw $this->createNotFoundException('Exercice introuvable.');
        }

        $exercises = $exerciseRepository->findBy([], ['name' => 'ASC']);

        return $this->render('admin/exercises/index.html.twig', [
            'exercises' => $exercises,
            'adminStats' => $this->buildStats($exercises),
            'exercise' => $exercise,
            'mode' => 'edit',
            'error' => null,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_exercises_update', methods: ['POST'])]
    public function update(int $id, Request $request, ExerciseRepository $exerciseRepository, AdminExerciseService $exerciseService): Response
    {
        $exercise = $exerciseRepository->find($id);
        if (!$exercise instanceof Exercise) {
            throw $this->createNotFoundException('Exercice introuvable.');
        }

        try {
            if (!$this->isCsrfTokenValid('admin_exercise_'.$exercise->getId(), (string) $request->request->get('_token'))) {
                throw new \InvalidArgumentException('Token CSRF invalide.');
            }

            $exerciseService->save($exercise, $request->request->all());
            $this->addFlash('success', 'Exercice mis a jour.');

            return $this->redirectToRoute('admin_exercises_edit', ['id' => $exercise->getId()]);
        } catch (\InvalidArgumentException $exception) {
            $exercises = $exerciseRepository->findBy([], ['name' => 'ASC']);

            return $this->render('admin/exercises/index.html.twig', [
                'exercises' => $exercises,
                'adminStats' => $this->buildStats($exercises),
                'exercise' => $exercise,
                'mode' => 'edit',
                'error' => $exception->getMessage(),
                'formData' => $request->request->all(),
            ], new Response('', Response::HTTP_UNPROCESSABLE_ENTITY));
        }
    }

    /**
     * @param list<Exercise> $exercises
     *
     * @return array{total: int, majorityEquipment: string, majorityPercent: int, latestName: string, latestLabel: string}
     */
    private function buildStats(array $exercises): array
    {
        $equipmentCounts = [];
        $latest = null;

        foreach ($exercises as $exercise) {
            $equipment = $exercise->getEquipment();
            $equipmentCounts[$equipment] = ($equipmentCounts[$equipment] ?? 0) + 1;

            if (!$latest || $exercise->getCreatedAt() > $latest->getCreatedAt()) {
                $latest = $exercise;
            }
        }

        arsort($equipmentCounts);
        $majorityEquipment = (string) array_key_first($equipmentCounts);
        $total = \count($exercises);

        return [
            'total' => $total,
            'majorityEquipment' => '' !== $majorityEquipment ? $majorityEquipment : 'Aucun',
            'majorityPercent' => $total > 0 ? (int) round(((int) reset($equipmentCounts) / $total) * 100) : 0,
            'latestName' => $latest?->getName() ?? 'Aucun ajout',
            'latestLabel' => $latest ? $latest->getCreatedAt()->format('d/m/Y') : '-',
        ];
    }
}
