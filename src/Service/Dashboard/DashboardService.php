<?php

namespace App\Service\Dashboard;

use App\Entity\UserProfile;
use App\Entity\WorkoutSession;
use App\Repository\PersonalRecordRepository;
use App\Repository\UserProfileRepository;
use App\Repository\WorkoutSessionRepository;
use App\Repository\WorkoutSetRepository;

final class DashboardService
{
    public function __construct(
        private readonly UserProfileRepository $profileRepository,
        private readonly WorkoutSetRepository $setRepository,
        private readonly PersonalRecordRepository $recordRepository,
        private readonly WorkoutSessionRepository $sessionRepository,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function build(): array
    {
        try {
            $profile = $this->profileRepository->findOneBy(['username' => 'alexvigor']);

            if (!$profile) {
                return $this->fallback();
            }

            $now = new \DateTimeImmutable();
            $weekStart = $now->modify('monday this week')->setTime(0, 0);
            $weekEnd = $weekStart->modify('+7 days');
            $previousWeekStart = $weekStart->modify('-7 days');
            $completedThisWeek = $this->setRepository->findCompletedForProfileBetween($profile, $weekStart, $weekEnd);
            $completedPreviousWeek = $this->setRepository->findCompletedForProfileBetween($profile, $previousWeekStart, $weekStart);
            $activeSession = $this->sessionRepository->findActiveForProfile($profile);

            return [
                'dateLabel' => $this->formatDateLabel($now),
                'activity' => $this->buildActivity($activeSession, $completedThisWeek, $now),
                'weekly' => $this->buildWeekly($completedThisWeek, $completedPreviousWeek, $weekStart),
                'recentRecords' => $this->buildRecentRecords($profile),
                'stats' => [
                    'weeklyVolumeTons' => $this->formatTons($this->sumVolume($completedThisWeek)),
                    'streakDays' => $this->calculateStreakDays($profile, $now),
                ],
                'cta' => [
                    'active' => null !== $activeSession,
                    'label' => $activeSession ? 'En cours' : 'Aucune seance en cours',
                    'title' => $activeSession?->getName() ?? 'Pret a demarrer',
                    'meta' => $activeSession ? 'Active maintenant' : 'Choisir une seance libre ou un programme',
                ],
            ];
        } catch (\Throwable) {
            return $this->fallback();
        }
    }

    /**
     * @param list<\App\Entity\WorkoutSet> $completedThisWeek
     */
    private function buildActivity(?WorkoutSession $activeSession, array $completedThisWeek, \DateTimeImmutable $now): array
    {
        $todayStart = $now->setTime(0, 0);
        $todayVolume = 0.0;
        $todayCompletedSets = 0;

        foreach ($completedThisWeek as $set) {
            if ($set->getCompletedAt() && $set->getCompletedAt() >= $todayStart) {
                $todayVolume += $set->getVolume();
                ++$todayCompletedSets;
            }
        }

        $activeMinutes = $activeSession ? max(1, (int) floor(($now->getTimestamp() - $activeSession->getStartedAt()->getTimestamp()) / 60)) : 0;
        $minutes = min(60, $activeMinutes + ($todayCompletedSets * 4));

        return [
            'kcal' => max(0, (int) round(($minutes * 9) + ($todayVolume / 100))),
            'minutes' => $minutes,
            'targetMinutes' => 60,
        ];
    }

    /**
     * @param list<\App\Entity\WorkoutSet> $completedThisWeek
     * @param list<\App\Entity\WorkoutSet> $completedPreviousWeek
     *
     * @return array<string, mixed>
     */
    private function buildWeekly(array $completedThisWeek, array $completedPreviousWeek, \DateTimeImmutable $weekStart): array
    {
        $volumeByDay = array_fill(0, 7, 0.0);

        foreach ($completedThisWeek as $set) {
            $completedAt = $set->getCompletedAt();

            if (!$completedAt) {
                continue;
            }

            $dayIndex = ((int) $completedAt->format('N')) - 1;
            $volumeByDay[$dayIndex] += $set->getVolume();
        }

        $dayLetters = ['L', 'M', 'M', 'J', 'V', 'S', 'D'];
        $dayNames = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche'];
        $total = array_sum($volumeByDay);
        $previousTotal = $this->sumVolume($completedPreviousWeek);
        $max = max($volumeByDay) ?: 1.0;
        $activeIndex = array_search($max, $volumeByDay, true);
        $activeIndex = false === $activeIndex ? ((int) (new \DateTimeImmutable())->format('N')) - 1 : $activeIndex;
        $days = [];

        if ($total <= 0) {
            foreach ($volumeByDay as $index => $volume) {
                $days[] = [
                    'day' => $dayLetters[$index],
                    'value' => $this->formatTons($volume),
                    'label' => $dayNames[$index],
                    'height' => 0,
                    'rest' => true,
                    'active' => false,
                ];
            }

            return [
                'days' => $days,
                'selectedValue' => '0',
                'selectedLabel' => 'Aucune serie cochee cette semaine',
                'totalTons' => '0',
                'trendPercent' => 0,
            ];
        }

        foreach ($volumeByDay as $index => $volume) {
            $isRest = $volume <= 0;
            $label = $dayNames[$index];

            if ($index === $activeIndex && !$isRest) {
                $label .= ' - meilleure seance';
            }

            $days[] = [
                'day' => $dayLetters[$index],
                'value' => $this->formatTons($volume),
                'label' => $label,
                'height' => $isRest ? 0 : max(24, (int) round(($volume / $max) * 100)),
                'rest' => $isRest,
                'active' => $index === $activeIndex && !$isRest,
            ];
        }

        return [
            'days' => $days,
            'selectedValue' => $this->formatTons($volumeByDay[$activeIndex] ?? 0),
            'selectedLabel' => ($dayNames[$activeIndex] ?? 'Jour').' - meilleure seance',
            'totalTons' => $this->formatTons($total),
            'trendPercent' => $previousTotal > 0 ? (int) round((($total - $previousTotal) / $previousTotal) * 100) : 0,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildRecentRecords(UserProfile $profile): array
    {
        $records = [];

        foreach ($this->recordRepository->findRecentForProfile($profile, 6) as $index => $record) {
            $set = $record->getWorkoutSet();
            $gain = $record->getPreviousValue() ? $record->getValue() - $record->getPreviousValue() : 0;

            $records[] = [
                'exercise' => $record->getExercise()->getName(),
                'value' => $this->formatNumber($set->getWeight()),
                'unit' => 'kg x '.$set->getReps(),
                'date' => $this->relativeDateLabel($record->getAchievedAt()),
                'gain' => $gain > 0 ? '+'.$this->formatNumber($gain).'kg' : 'NEW',
                'previous' => $record->getPreviousValue() ? 'vs '.$this->formatNumber($record->getPreviousValue()).'kg 1RM' : '1RM estime',
                'new' => 0 === $index,
            ];
        }

        return $records ?: $this->fallback()['recentRecords'];
    }

    private function calculateStreakDays(UserProfile $profile, \DateTimeImmutable $now): int
    {
        $streak = 0;
        $cursor = $now->setTime(0, 0);

        for ($i = 0; $i < 30; ++$i) {
            $sets = $this->setRepository->findCompletedForProfileBetween($profile, $cursor, $cursor->modify('+1 day'));

            if ([] === $sets) {
                if (0 === $i) {
                    $cursor = $cursor->modify('-1 day');
                    continue;
                }

                break;
            }

            ++$streak;
            $cursor = $cursor->modify('-1 day');
        }

        return $streak;
    }

    /**
     * @param list<\App\Entity\WorkoutSet> $sets
     */
    private function sumVolume(array $sets): float
    {
        return array_sum(array_map(static fn ($set): float => $set->getVolume(), $sets));
    }

    private function formatTons(float $volume): string
    {
        return $this->formatNumber($volume / 1000);
    }

    private function formatNumber(float $value): string
    {
        $rounded = round($value, 1);

        return 0.0 === fmod($rounded, 1.0) ? (string) (int) $rounded : number_format($rounded, 1, '.', '');
    }

    private function formatDateLabel(\DateTimeImmutable $date): string
    {
        $days = ['Dimanche', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'];
        $months = [1 => 'Janvier', 'Fevrier', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Aout', 'Septembre', 'Octobre', 'Novembre', 'Decembre'];

        return sprintf('%s %s %s', $days[(int) $date->format('w')], $date->format('j'), $months[(int) $date->format('n')]);
    }

    private function relativeDateLabel(\DateTimeImmutable $date): string
    {
        $days = (int) floor(((new \DateTimeImmutable())->getTimestamp() - $date->getTimestamp()) / 86400);

        if ($days <= 0) {
            return 'Aujourd\'hui';
        }

        if (1 === $days) {
            return 'Hier';
        }

        return 'Il y a '.$days.'j';
    }

    /**
     * @return array<string, mixed>
     */
    private function fallback(): array
    {
        return [
            'dateLabel' => 'Mardi 26 Mai',
            'activity' => ['kcal' => 850, 'minutes' => 45, 'targetMinutes' => 60],
            'weekly' => [
                'days' => [
                    ['day' => 'L', 'value' => '2.8', 'label' => 'Lundi', 'height' => 55, 'rest' => false],
                    ['day' => 'M', 'value' => '3.6', 'label' => 'Mardi', 'height' => 78, 'rest' => false],
                    ['day' => 'M', 'value' => '0', 'label' => 'Mercredi', 'height' => 0, 'rest' => true],
                    ['day' => 'J', 'value' => '4.2', 'label' => 'Jeudi - meilleure seance', 'height' => 100, 'rest' => false, 'active' => true],
                    ['day' => 'V', 'value' => '2.1', 'label' => 'Vendredi', 'height' => 40, 'rest' => false],
                    ['day' => 'S', 'value' => '0', 'label' => 'Samedi', 'height' => 0, 'rest' => true],
                    ['day' => 'D', 'value' => '1.5', 'label' => 'Dimanche', 'height' => 30, 'rest' => false],
                ],
                'selectedValue' => '4.2',
                'selectedLabel' => 'Jeudi - meilleure seance',
                'totalTons' => '14.2',
                'trendPercent' => 12,
            ],
            'recentRecords' => [
                ['exercise' => 'Squat', 'value' => '140', 'unit' => 'kg x 5', 'date' => 'Aujourd\'hui', 'gain' => '+5kg', 'previous' => 'vs 135kg', 'new' => true],
                ['exercise' => 'Developpe couche', 'value' => '95', 'unit' => 'kg x 6', 'date' => 'Il y a 3j', 'gain' => '+2.5kg', 'previous' => 'vs 92.5kg'],
            ],
            'stats' => ['weeklyVolumeTons' => '14.2', 'streakDays' => 4],
            'cta' => ['active' => true, 'label' => 'En cours', 'title' => 'Hypertrophie Push', 'meta' => '45 Min - 6 Exos'],
        ];
    }
}
