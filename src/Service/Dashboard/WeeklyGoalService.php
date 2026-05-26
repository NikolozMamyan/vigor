<?php

namespace App\Service\Dashboard;

use App\Entity\UserProfile;
use App\Entity\WeeklyGoal;
use App\Entity\WorkoutSet;
use App\Repository\WeeklyGoalRepository;
use App\Repository\WorkoutSetRepository;

final class WeeklyGoalService
{
    public function __construct(
        private readonly WeeklyGoalRepository $goalRepository,
        private readonly WorkoutSetRepository $setRepository,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function buildProgress(UserProfile $profile, ?\DateTimeImmutable $now = null): array
    {
        $now ??= new \DateTimeImmutable();
        $weekStart = $now->modify('monday this week')->setTime(0, 0);
        $weekEnd = $weekStart->modify('+7 days');
        $previousWeekStart = $weekStart->modify('-7 days');

        $goal = $this->goalRepository->findForProfileAndWeek($profile, $weekStart)
            ?? new WeeklyGoal($profile, $weekStart);

        $currentSets = $this->setRepository->findCompletedForProfileBetween($profile, $weekStart, $weekEnd);
        $previousSets = $this->setRepository->findCompletedForProfileBetween($profile, $previousWeekStart, $weekStart);

        $volume = $this->sumVolume($currentSets);
        $previousVolume = $this->sumVolume($previousSets);
        $workouts = $this->countWorkouts($currentSets);
        $trainingMinutes = $this->sumTrainingMinutes($currentSets);

        return [
            'weekStartDate' => $weekStart->format('Y-m-d'),
            'workouts' => [
                'current' => $workouts,
                'target' => $goal->getTargetWorkouts(),
                'percent' => $this->percent($workouts, $goal->getTargetWorkouts()),
            ],
            'volume' => [
                'current' => $volume,
                'target' => $goal->getTargetVolume(),
                'percent' => $this->percent($volume, $goal->getTargetVolume()),
                'trendPercent' => $previousVolume > 0 ? (int) round((($volume - $previousVolume) / $previousVolume) * 100) : 0,
            ],
            'trainingMinutes' => [
                'current' => $trainingMinutes,
                'target' => $goal->getTargetTrainingMinutes(),
                'percent' => $this->percent($trainingMinutes, $goal->getTargetTrainingMinutes()),
            ],
        ];
    }

    /**
     * @param list<WorkoutSet> $sets
     */
    private function sumVolume(array $sets): float
    {
        return array_sum(array_map(static fn (WorkoutSet $set): float => $set->getVolume(), $sets));
    }

    /**
     * @param list<WorkoutSet> $sets
     */
    private function countWorkouts(array $sets): int
    {
        $sessions = [];

        foreach ($sets as $set) {
            $session = $set->getSessionExercise()->getSession();
            $sessions[$session->getId() ?? spl_object_id($session)] = true;
        }

        return count($sessions);
    }

    /**
     * @param list<WorkoutSet> $sets
     */
    private function sumTrainingMinutes(array $sets): int
    {
        $sessions = [];
        $minutes = 0;

        foreach ($sets as $set) {
            $session = $set->getSessionExercise()->getSession();
            $key = $session->getId() ?? spl_object_id($session);

            if (isset($sessions[$key])) {
                continue;
            }

            $sessions[$key] = true;
            $minutes += (int) floor(($session->getDurationSeconds() ?? 0) / 60);
        }

        return $minutes;
    }

    private function percent(float|int $current, float|int $target): int
    {
        if ($target <= 0) {
            return 0;
        }

        return min(100, (int) round(($current / $target) * 100));
    }
}
