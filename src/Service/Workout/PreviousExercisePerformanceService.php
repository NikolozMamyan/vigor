<?php

namespace App\Service\Workout;

use App\Entity\Exercise;
use App\Entity\UserProfile;
use App\Entity\WorkoutSession;
use App\Entity\WorkoutSessionExercise;
use App\Entity\WorkoutSet;
use App\Repository\WorkoutSetReaderInterface;

final class PreviousExercisePerformanceService
{
    public function __construct(private readonly WorkoutSetReaderInterface $setRepository)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function forSessionExercise(UserProfile $profile, WorkoutSessionExercise $sessionExercise): array
    {
        return $this->forExercise($profile, $sessionExercise->getExercise(), $sessionExercise->getSession());
    }

    /**
     * @return array<string, mixed>
     */
    public function forExercise(UserProfile $profile, Exercise $exercise, ?WorkoutSession $currentSession = null): array
    {
        return $this->normalize($this->setRepository->findPreviousCompletedForExercise($profile, $exercise, $currentSession));
    }

    /**
     * @param list<WorkoutSet> $sets
     *
     * @return array<string, mixed>
     */
    private function normalize(array $sets): array
    {
        if ([] === $sets) {
            return [
                'hasData' => false,
                'title' => 'Premiere fois suivie',
                'subtitle' => 'Aucune seance terminee pour cet exercice.',
                'summary' => 'Nouveau repere',
                'volume' => '0',
                'sets' => [],
            ];
        }

        $bestSet = $this->bestSet($sets);
        $session = $sets[0]->getSessionExercise()->getSession();
        $volume = array_sum(array_map(static fn (WorkoutSet $set): float => $set->getVolume(), $sets));

        return [
            'hasData' => true,
            'title' => 'Derniere fois',
            'subtitle' => sprintf(
                '%s - %d serie%s',
                ($session->getCompletedAt() ?? $session->getStartedAt())->format('d/m'),
                count($sets),
                count($sets) > 1 ? 's' : '',
            ),
            'summary' => $this->setLabel($bestSet),
            'volume' => $this->formatNumber($volume),
            'sets' => array_map(fn (WorkoutSet $set): array => [
                'position' => $set->getPosition(),
                'label' => $this->setLabel($set),
                'weight' => $this->formatNumber($set->getWeight()),
                'reps' => $set->getReps(),
                'volume' => $this->formatNumber($set->getVolume()),
                'hasData' => true,
            ], $sets),
        ];
    }

    /**
     * @param list<WorkoutSet> $sets
     */
    private function bestSet(array $sets): WorkoutSet
    {
        return array_reduce(
            $sets,
            static function (?WorkoutSet $best, WorkoutSet $set): WorkoutSet {
                if (!$best) {
                    return $set;
                }

                if ($set->getWeight() > $best->getWeight()) {
                    return $set;
                }

                if ($set->getWeight() === $best->getWeight() && $set->getReps() > $best->getReps()) {
                    return $set;
                }

                return $best;
            },
        );
    }

    private function setLabel(WorkoutSet $set): string
    {
        return sprintf('%skg x %d', $this->formatNumber($set->getWeight()), $set->getReps());
    }

    private function formatNumber(float $value): string
    {
        $rounded = round($value, 1);

        return 0.0 === fmod($rounded, 1.0) ? (string) (int) $rounded : number_format($rounded, 1, '.', '');
    }
}
