<?php

namespace App\Service\Admin;

use App\Entity\Exercise;
use App\Repository\ExerciseRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\String\Slugger\AsciiSlugger;

final class AdminExerciseService
{
    public function __construct(
        private readonly ExerciseRepository $exerciseRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function save(Exercise $exercise, array $payload): Exercise
    {
        $name = trim((string) ($payload['name'] ?? ''));
        $slug = trim((string) ($payload['slug'] ?? ''));
        $muscleGroup = trim((string) ($payload['muscleGroup'] ?? ''));
        $equipment = trim((string) ($payload['equipment'] ?? ''));
        $imageUrl = trim((string) ($payload['imageUrl'] ?? ''));
        $source = trim((string) ($payload['source'] ?? Exercise::SOURCE_VIGOR));

        if ('' === $name || mb_strlen($name) > 120) {
            throw new \InvalidArgumentException('Le nom est obligatoire et limite a 120 caracteres.');
        }

        if ('' === $muscleGroup || mb_strlen($muscleGroup) > 60) {
            throw new \InvalidArgumentException('Le groupe musculaire est obligatoire et limite a 60 caracteres.');
        }

        if ('' === $equipment || mb_strlen($equipment) > 60) {
            throw new \InvalidArgumentException('L\'equipement est obligatoire et limite a 60 caracteres.');
        }

        if ('' !== $imageUrl && mb_strlen($imageUrl) > 255) {
            throw new \InvalidArgumentException('L\'URL image est limitee a 255 caracteres.');
        }

        if (!\in_array($source, [Exercise::SOURCE_VIGOR, Exercise::SOURCE_CUSTOM], true)) {
            throw new \InvalidArgumentException('La source est invalide.');
        }

        $slug = '' === $slug ? $this->slugify($name) : $this->slugify($slug);
        $existing = $this->exerciseRepository->findOneBy(['slug' => $slug]);

        if ($existing && $existing !== $exercise) {
            throw new \InvalidArgumentException('Ce slug est deja utilise.');
        }

        $exercise
            ->setName($name)
            ->setSlug($slug)
            ->setMuscleGroup($muscleGroup)
            ->setEquipment($equipment)
            ->setImageUrl('' === $imageUrl ? null : $imageUrl)
            ->setSource($source);

        $this->entityManager->persist($exercise);
        $this->entityManager->flush();

        return $exercise;
    }

    private function slugify(string $value): string
    {
        $slugger = new AsciiSlugger();
        $slug = mb_strtolower((string) $slugger->slug($value));

        return '' === $slug ? 'exercise' : $slug;
    }
}
