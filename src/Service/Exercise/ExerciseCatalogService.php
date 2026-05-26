<?php

namespace App\Service\Exercise;

use App\Entity\Exercise;
use App\Repository\ExerciseRepository;
use App\Repository\UserProfileRepository;

final class ExerciseCatalogService
{
    public function __construct(
        private readonly ExerciseRepository $exerciseRepository,
        private readonly UserProfileRepository $profileRepository,
    ) {
    }

    /**
     * @return array{categories: list<array<string, mixed>>, exercises: list<array<string, mixed>>}
     */
    public function build(): array
    {
        try {
            $profile = $this->profileRepository->findOneBy(['username' => 'alexvigor']);
            $exercises = $this->exerciseRepository->findCatalogForProfile($profile);

            if ([] === $exercises) {
                return $this->fallback();
            }

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
            $profile = $this->profileRepository->findOneBy(['username' => 'alexvigor']);
            $exercises = '' === $query
                ? $this->exerciseRepository->findCatalogForProfile($profile)
                : $this->exerciseRepository->searchForProfile($query, $profile);

            return array_map(fn (Exercise $exercise): array => $this->normalizeExercise($exercise), $exercises);
        } catch (\Throwable) {
            $fallback = $this->fallback()['exercises'];

            if ('' === $query) {
                return $fallback;
            }

            $normalizedQuery = mb_strtolower($query);

            return array_values(array_filter($fallback, static fn (array $exercise): bool => str_contains(mb_strtolower($exercise['name'].' '.$exercise['category'].' '.$exercise['tag']), $normalizedQuery)));
        }
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
    private function normalizeExercise(Exercise $exercise): array
    {
        return [
            'name' => $exercise->getName(),
            'category' => $exercise->getMuscleGroup(),
            'tag' => $exercise->getEquipment(),
            'image' => $exercise->getImageUrl() ?? 'https://placehold.co/600x400/18181b/ccff00?text=VIGOR',
            'source' => $exercise->getSource(),
            'isCustom' => Exercise::SOURCE_CUSTOM === $exercise->getSource(),
        ];
    }

    /**
     * @return array{categories: list<array<string, mixed>>, exercises: list<array<string, mixed>>}
     */
    private function fallback(): array
    {
        return [
            'categories' => [
                ['label' => 'Populaires', 'icon' => 'flame', 'active' => true],
                ['label' => 'Pecs', 'icon' => 'target', 'active' => false],
                ['label' => 'Dos', 'icon' => 'shield', 'active' => false],
                ['label' => 'Bras', 'icon' => 'dumbbell', 'active' => false],
            ],
            'exercises' => [
                ['name' => 'Squat', 'category' => 'Jambes', 'tag' => 'Barre', 'image' => 'https://images.unsplash.com/photo-1571019614242-c5c5dee9f50b?q=80&w=1470&auto=format&fit=crop', 'source' => Exercise::SOURCE_VIGOR, 'isCustom' => false],
                ['name' => 'Curl Biceps', 'category' => 'Bras', 'tag' => 'Halteres', 'image' => 'https://images.unsplash.com/photo-1581009146145-b5ef050c2e1e?q=80&w=1470&auto=format&fit=crop', 'source' => Exercise::SOURCE_VIGOR, 'isCustom' => false],
                ['name' => 'Tirage elastique maison', 'category' => 'Dos', 'tag' => 'Elastique', 'image' => 'https://placehold.co/600x400/18181b/ccff00?text=VIGOR', 'source' => Exercise::SOURCE_CUSTOM, 'isCustom' => true],
            ],
        ];
    }
}
