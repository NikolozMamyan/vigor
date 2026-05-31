<?php

namespace App\Service\Workout;

use App\Entity\WorkoutSet;
use App\Repository\WorkoutProgramExerciseRepository;
use App\Repository\WorkoutProgramRepository;
use App\Repository\WorkoutSessionExerciseRepository;
use App\Repository\WorkoutSessionRepository;
use App\Repository\WorkoutSetRepository;
use App\Service\Auth\CurrentUserProfileProvider;

final class ActiveWorkoutViewService
{
    public function __construct(
        private readonly CurrentUserProfileProvider $currentUser,
        private readonly WorkoutSessionRepository $sessionRepository,
        private readonly WorkoutSessionExerciseRepository $sessionExerciseRepository,
        private readonly WorkoutSetRepository $setRepository,
        private readonly WorkoutProgramRepository $programRepository,
        private readonly WorkoutProgramExerciseRepository $programExerciseRepository,
        private readonly PreviousExercisePerformanceService $previousPerformanceService,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function build(?int $preferredSessionExerciseId = null): array
    {
        try {
            $profile = $this->currentUser->getProfile();

            if (!$profile) {
                return $this->emptyState();
            }

            $session = $this->sessionRepository->findActiveForProfile($profile);

            if (!$session) {
                return $this->noActiveWorkout($profile);
            }

            $sessionExercises = $this->sessionExerciseRepository->findForSession($session);
            $currentSessionExercise = $this->resolveCurrentSessionExercise($sessionExercises, $preferredSessionExerciseId);

            if (!$currentSessionExercise) {
                return $this->noActiveWorkout($profile);
            }

            $exercise = $currentSessionExercise->getExercise();
            $sets = $this->setRepository->findForSessionExercise($currentSessionExercise);
            $previousPerformance = $this->previousPerformanceService->forSessionExercise($profile, $currentSessionExercise);
            $previousSetByPosition = $this->previousSetByPosition($previousPerformance);

            return [
                'hasActiveSession' => true,
                'sessionId' => $session->getId(),
                'sessionExerciseId' => $currentSessionExercise->getId(),
                'sessionName' => $session->getName(),
                'statusLabel' => 'En cours',
                'headerTitle' => $session->getName(),
                'headerSubtitle' => $exercise->getName().' - '.$exercise->getMuscleGroup(),
                'exercisePosition' => $currentSessionExercise->getPosition(),
                'exerciseCount' => max(1, count($sessionExercises)),
                'exerciseList' => array_map(fn ($sessionExercise): array => [
                    'id' => $sessionExercise->getId(),
                    'name' => $sessionExercise->getExercise()->getName(),
                    'muscleGroup' => $sessionExercise->getExercise()->getMuscleGroup(),
                    'image' => $sessionExercise->getExercise()->getImageUrl() ?? 'https://placehold.co/160x160/18181b/ccff00?text=VIGOR',
                    'active' => $sessionExercise->getId() === $currentSessionExercise->getId(),
                ], $sessionExercises),
                'equipment' => $exercise->getEquipment(),
                'title' => $exercise->getName(),
                'titleLines' => $this->splitTitle($exercise->getName()),
                'image' => $exercise->getImageUrl() ?? 'https://placehold.co/900x700/18181b/ccff00?text=VIGOR',
                'restSeconds' => $currentSessionExercise->getRestSeconds(),
                'targetLabel' => $this->targetLabel($currentSessionExercise->getTargetSets(), $currentSessionExercise->getTargetRepsMin(), $currentSessionExercise->getTargetRepsMax()),
                'previousPerformance' => $previousPerformance,
                'sets' => $this->normalizeSets($sets, $currentSessionExercise->getTargetSets() ?? 3, $currentSessionExercise->getId(), $previousSetByPosition),
            ];
        } catch (\Throwable) {
            return $this->emptyState();
        }
    }

    /**
     * @param list<WorkoutSet> $sets
     *
     * @return list<array<string, mixed>>
     */
    private function normalizeSets(array $sets, int $targetSets, ?int $sessionExerciseId, array $previousSetByPosition = []): array
    {
        $normalized = [];

        foreach ($sets as $set) {
            $normalized[] = [
                'id' => $set->getId(),
                'sessionExerciseId' => $sessionExerciseId,
                'number' => $set->getPosition(),
                'previous' => $this->setLabel($set),
                'weight' => 0.0 === $set->getWeight() ? null : $this->formatNumber($set->getWeight()),
                'reps' => 0 === $set->getReps() ? null : $set->getReps(),
                'completed' => null !== $set->getCompletedAt(),
                'previousSet' => $previousSetByPosition[$set->getPosition()] ?? $this->emptyPreviousSet($set->getPosition()),
            ];
        }

        for ($position = count($normalized) + 1; $position <= $targetSets; ++$position) {
            $normalized[] = [
                'id' => null,
                'sessionExerciseId' => $sessionExerciseId,
                'number' => $position,
                'previous' => 'A completer',
                'weight' => null,
                'reps' => null,
                'completed' => false,
                'previousSet' => $previousSetByPosition[$position] ?? $this->emptyPreviousSet($position),
            ];
        }

        return $normalized;
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

    /**
     * @param list<\App\Entity\WorkoutSessionExercise> $sessionExercises
     */
    private function resolveCurrentSessionExercise(array $sessionExercises, ?int $preferredSessionExerciseId): ?\App\Entity\WorkoutSessionExercise
    {
        if ($preferredSessionExerciseId) {
            foreach ($sessionExercises as $sessionExercise) {
                if ($sessionExercise->getId() === $preferredSessionExerciseId) {
                    return $sessionExercise;
                }
            }
        }

        return $sessionExercises[0] ?? null;
    }

    private function setLabel(WorkoutSet $set): string
    {
        if ($set->getWeight() <= 0 || $set->getReps() <= 0) {
            return 'A completer';
        }

        return sprintf('%skg x %d', $this->formatNumber($set->getWeight()), $set->getReps());
    }

    private function targetLabel(?int $sets, ?int $repsMin, ?int $repsMax): string
    {
        $sets ??= 3;

        if ($repsMin && $repsMax && $repsMin !== $repsMax) {
            return sprintf('%d x %d-%d', $sets, $repsMin, $repsMax);
        }

        if ($repsMin) {
            return sprintf('%d x %d', $sets, $repsMin);
        }

        return sprintf('%d series', $sets);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function splitTitle(string $title): array
    {
        $parts = explode(' ', $title);

        if (count($parts) <= 1) {
            return [$title, ''];
        }

        $last = array_pop($parts);

        return [implode(' ', $parts), $last];
    }

    private function formatNumber(float $value): string
    {
        $rounded = round($value, 1);

        return 0.0 === fmod($rounded, 1.0) ? (string) (int) $rounded : number_format($rounded, 1, '.', '');
    }

    /**
     * @return array<string, mixed>
     */
    private function noActiveWorkout(\App\Entity\UserProfile $profile): array
    {
        $programs = [];

        foreach ($this->programRepository->findForProfile($profile) as $program) {
            $exercises = $this->programExerciseRepository->findForProgram($program);

            $programs[] = [
                'id' => $program->getId(),
                'name' => $program->getName(),
                'description' => $program->getDescription() ?? 'Programme personnalise',
                'exerciseCount' => count($exercises),
                'meta' => $this->programMeta($program->getEstimatedDurationMinutes(), count($exercises)),
                'exercises' => array_map(fn ($programExercise): array => [
                    'exerciseId' => $programExercise->getExercise()->getId(),
                    'name' => $programExercise->getExercise()->getName(),
                    'muscleGroup' => $programExercise->getExercise()->getMuscleGroup(),
                    'image' => $programExercise->getExercise()->getImageUrl() ?? 'https://placehold.co/160x160/18181b/ccff00?text=VIGOR',
                    'targetSets' => $programExercise->getTargetSets(),
                    'targetRepsMin' => $programExercise->getTargetRepsMin(),
                    'targetRepsMax' => $programExercise->getTargetRepsMax(),
                    'targetWeight' => $programExercise->getTargetWeight(),
                    'restSeconds' => $programExercise->getRestSeconds(),
                    'target' => $this->programExerciseTarget($programExercise),
                ], $exercises),
            ];
        }

        return [
            'hasActiveSession' => false,
            'programs' => $programs,
            'history' => $this->historySessions($profile),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function historySessions(\App\Entity\UserProfile $profile): array
    {
        return array_map(
            fn (\App\Entity\WorkoutSession $session): array => $this->historySession($session),
            $this->sessionRepository->findCompletedForProfile($profile),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function historySession(\App\Entity\WorkoutSession $session): array
    {
        $sessionExercises = $this->sessionExerciseRepository->findForSession($session);
        $setCount = 0;
        $volume = 0.0;

        foreach ($sessionExercises as $sessionExercise) {
            foreach ($this->setRepository->findForSessionExercise($sessionExercise) as $set) {
                ++$setCount;
                $volume += $set->getVolume();
            }
        }

        return [
            'id' => $session->getId(),
            'name' => $session->getName(),
            'date' => $session->getCompletedAt()?->format('d/m/Y') ?? $session->getStartedAt()->format('d/m/Y'),
            'time' => $session->getCompletedAt()?->format('H:i') ?? $session->getStartedAt()->format('H:i'),
            'exerciseCount' => count($sessionExercises),
            'setCount' => $setCount,
            'volume' => $this->formatNumber($volume),
            'meta' => sprintf('%d exo%s - %d serie%s', count($sessionExercises), count($sessionExercises) > 1 ? 's' : '', $setCount, $setCount > 1 ? 's' : ''),
        ];
    }

    private function programMeta(?int $durationMinutes, int $exerciseCount): string
    {
        $exerciseLabel = sprintf('%d exo%s', $exerciseCount, $exerciseCount > 1 ? 's' : '');

        if (!$durationMinutes) {
            return $exerciseLabel;
        }

        return sprintf('%d min - %s', $durationMinutes, $exerciseLabel);
    }

    private function programExerciseTarget(\App\Entity\WorkoutProgramExercise $programExercise): string
    {
        $weight = $programExercise->getTargetWeight();
        $reps = $programExercise->getTargetRepsMin() === $programExercise->getTargetRepsMax()
            ? (string) $programExercise->getTargetRepsMin()
            : sprintf('%d-%d', $programExercise->getTargetRepsMin(), $programExercise->getTargetRepsMax());

        $target = sprintf('%d x %s reps', $programExercise->getTargetSets(), $reps);

        if (null !== $weight && $weight > 0) {
            $target = $this->formatNumber($weight).'kg - '.$target;
        }

        return $target;
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyState(): array
    {
        return [
            'hasActiveSession' => false,
            'sessionId' => null,
            'sessionExerciseId' => null,
            'sessionName' => 'Seance libre',
            'statusLabel' => 'Aucune seance',
            'headerTitle' => 'Aucune seance active',
            'headerSubtitle' => 'Demarre une seance libre ou un programme.',
            'exercisePosition' => 0,
            'exerciseCount' => 0,
            'exerciseList' => [],
            'equipment' => '',
            'title' => 'Aucune seance active',
            'titleLines' => ['Aucune seance', 'active'],
            'image' => 'https://placehold.co/900x700/18181b/ccff00?text=VIGOR',
            'restSeconds' => 90,
            'targetLabel' => '3 x 8-10',
            'previousPerformance' => [
                'hasData' => false,
                'title' => 'Premiere fois suivie',
                'subtitle' => 'Aucune seance terminee pour cet exercice.',
                'summary' => 'Nouveau repere',
                'volume' => '0',
                'sets' => [],
            ],
            'history' => [],
            'sets' => [
                ['id' => null, 'sessionExerciseId' => null, 'number' => 1, 'previous' => 'A completer', 'weight' => null, 'reps' => null, 'completed' => false],
                ['id' => null, 'sessionExerciseId' => null, 'number' => 2, 'previous' => 'A completer', 'weight' => null, 'reps' => null, 'completed' => false],
                ['id' => null, 'sessionExerciseId' => null, 'number' => 3, 'previous' => 'A completer', 'weight' => null, 'reps' => null, 'completed' => false],
            ],
            'programs' => [],
        ];
    }
}
