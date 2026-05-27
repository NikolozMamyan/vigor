<?php

namespace App\Service\Exercise;

use App\Entity\Exercise;
use App\Entity\UserProfile;
use App\Repository\ExerciseRepository;
use App\Service\Auth\CurrentUserProfileProvider;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\String\Slugger\AsciiSlugger;

final class ExerciseCatalogService
{
    public function __construct(
        private readonly ExerciseRepository $exerciseRepository,
        private readonly CurrentUserProfileProvider $currentUser,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return array{categories: list<array<string, mixed>>, exercises: list<array<string, mixed>>}
     */
    public function build(): array
    {
        try {
            $profile = $this->currentUser->getProfile();
            $exercises = $this->exerciseRepository->findCatalogForProfile($profile);

            return [
                'categories' => $this->buildCategories($exercises),
                'exercises' => array_map(fn (Exercise $exercise): array => $this->normalizeExercise($exercise), $exercises),
            ];
        } catch (\Throwable) {
            return $this->fallback();
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function search(string $query): array
    {
        $query = trim($query);

        try {
            $profile = $this->currentUser->getProfile();
            $exercises = '' === $query
                ? $this->exerciseRepository->findCatalogForProfile($profile)
                : $this->exerciseRepository->searchForProfile($query, $profile);

            return array_map(fn (Exercise $exercise): array => $this->normalizeExercise($exercise), $exercises);
        } catch (\Throwable) {
            $fallbackExercises = $this->fallback()['exercises'];

            if ('' === $query) {
                return $fallbackExercises;
            }

            return array_values(array_filter($fallbackExercises, static fn (array $exercise): bool => str_contains(mb_strtolower($exercise['name'].' '.$exercise['category'].' '.$exercise['tag']), mb_strtolower($query))));
        }
    }

    public function createCustom(UserProfile $profile, string $name, string $muscleGroup, string $equipment, ?string $imageUrl = null): Exercise
    {
        $name = trim($name);
        $muscleGroup = trim($muscleGroup);
        $equipment = trim($equipment);
        $imageUrl = null === $imageUrl ? null : trim($imageUrl);

        if ('' === $name || '' === $muscleGroup || '' === $equipment) {
            throw new \InvalidArgumentException('Name, muscleGroup and equipment are required.');
        }

        if (mb_strlen($name) > 120 || mb_strlen($muscleGroup) > 60 || mb_strlen($equipment) > 60) {
            throw new \InvalidArgumentException('Exercise fields are too long.');
        }

        if (null !== $imageUrl && mb_strlen($imageUrl) > 255) {
            throw new \InvalidArgumentException('Image URL is too long.');
        }

        $exercise = (new Exercise())
            ->setName($name)
            ->setSlug($this->uniqueSlug($name))
            ->setMuscleGroup($muscleGroup)
            ->setEquipment($equipment)
            ->setImageUrl('' === $imageUrl ? null : $imageUrl)
            ->setSource(Exercise::SOURCE_CUSTOM)
            ->setCreatedByProfile($profile);

        $this->entityManager->persist($exercise);
        $this->entityManager->flush();

        return $exercise;
    }

    /**
     * @param list<Exercise> $exercises
     *
     * @return list<array<string, mixed>>
     */
    private function buildCategories(array $exercises): array
    {
        $categories = [
            ['label' => 'Populaires', 'icon' => 'flame', 'active' => true],
        ];
        $seen = [];
        $iconMap = [
            'Pectoraux' => 'target',
            'Dos' => 'shield',
            'Dos / Jambes' => 'shield',
            'Bras' => 'dumbbell',
            'Jambes' => 'footprints',
            'Epaules' => 'hexagon',
            'Fessiers' => 'circle',
            'Abdominaux' => 'layers',
            'Mollets' => 'footprints',
        ];

        foreach ($exercises as $exercise) {
            $muscleGroup = $exercise->getMuscleGroup();

            if (isset($seen[$muscleGroup])) {
                continue;
            }

            $seen[$muscleGroup] = true;
            $categories[] = [
                'label' => $muscleGroup,
                'icon' => $iconMap[$muscleGroup] ?? 'circle-dot',
                'active' => false,
            ];
        }

        return $categories;
    }

    /**
     * @return array<string, mixed>
     */
    public function normalizeExercise(Exercise $exercise): array
    {
        return [
            'id' => $exercise->getId(),
            'name' => $exercise->getName(),
            'category' => $exercise->getMuscleGroup(),
            'tag' => $exercise->getEquipment(),
            'image' => $exercise->getImageUrl() ?? 'https://placehold.co/600x400/18181b/ccff00?text=VIGOR',
            'source' => $exercise->getSource(),
            'isCustom' => Exercise::SOURCE_CUSTOM === $exercise->getSource(),
        ];
    }

    private function uniqueSlug(string $name): string
    {
        $slugger = new AsciiSlugger();
        $baseSlug = mb_strtolower((string) $slugger->slug($name));
        $baseSlug = '' === $baseSlug ? 'custom-exercise' : $baseSlug;
        $slug = $baseSlug;
        $suffix = 2;

        while ($this->exerciseRepository->findOneBy(['slug' => $slug])) {
            $slug = $baseSlug.'-'.$suffix;
            ++$suffix;
        }

        return $slug;
    }

    /**
     * @return array{categories: list<array<string, mixed>>, exercises: list<array<string, mixed>>}
     */
    private function fallback(): array
    {
        return [
            'categories' => [
                ['label' => 'Populaires', 'icon' => 'flame', 'active' => true],
                ['label' => 'Jambes', 'icon' => 'footprints', 'active' => false],
                ['label' => 'Accessoires', 'icon' => 'circle-dot', 'active' => false],
            ],
            'exercises' => [
                [
                    'id' => 1,
                    'name' => 'Squat',
                    'category' => 'Jambes',
                    'tag' => 'Barre',
                    'image' => 'https://placehold.co/600x400/18181b/ccff00?text=Squat',
                    'source' => Exercise::SOURCE_VIGOR,
                    'isCustom' => false,
                ],
                [
                    'id' => 2,
                    'name' => 'Curl elastique',
                    'category' => 'Accessoires',
                    'tag' => 'Elastique',
                    'image' => 'https://placehold.co/600x400/18181b/ccff00?text=Custom',
                    'source' => Exercise::SOURCE_CUSTOM,
                    'isCustom' => true,
                ],
            ],
        ];
    }
}
