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
        return $this->render('admin/exercises/index.html.twig', [
            'exercises' => $exerciseRepository->findBy([], ['name' => 'ASC']),
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
            return $this->render('admin/exercises/index.html.twig', [
                'exercises' => $exerciseRepository->findBy([], ['name' => 'ASC']),
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

        return $this->render('admin/exercises/index.html.twig', [
            'exercises' => $exerciseRepository->findBy([], ['name' => 'ASC']),
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
            return $this->render('admin/exercises/index.html.twig', [
                'exercises' => $exerciseRepository->findBy([], ['name' => 'ASC']),
                'exercise' => $exercise,
                'mode' => 'edit',
                'error' => $exception->getMessage(),
                'formData' => $request->request->all(),
            ], new Response('', Response::HTTP_UNPROCESSABLE_ENTITY));
        }
    }
}
