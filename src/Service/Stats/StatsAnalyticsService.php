<?php

namespace App\Service\Stats;

use App\Entity\PersonalRecord;
use App\Entity\UserProfile;
use App\Entity\WorkoutSet;
use App\Repository\PersonalRecordRepository;
use App\Repository\UserProfileRepository;
use App\Repository\WorkoutSetRepository;

final class StatsAnalyticsService
{
    public function __construct(
        private readonly UserProfileRepository $profileRepository,
        private readonly WorkoutSetRepository $setRepository,
        private readonly PersonalRecordRepository $recordRepository,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function build(string $period = 'week', ?\DateTimeImmutable $now = null): array
    {
        try {
            $now ??= new \DateTimeImmutable();
            $profile = $this->profileRepository->findOneBy(['username' => 'alexvigor']);

            if (!$profile) {
                return $this->fallback($period);
            }

            [$from, $to] = $this->periodRange($period, $now);
            $sets = $this->setRepository->findCompletedForProfileBetween($profile, $from, $to);
            $previousSets = $this->setRepository->findCompletedForProfileBetween($profile, $this->previousRangeStart($from, $to), $from);
            $totalVolume = $this->sumVolume($sets);
            $previousVolume = $this->sumVolume($previousSets);

            return [
                'period' => $period,
                'totalVolumeKg' => (int) round($totalVolume),
                'totalVolumeTons' => $this->formatTons($totalVolume),
                'trendPercent' => $previousVolume > 0 ? (int) round((($totalVolume - $previousVolume) / $previousVolume) * 100) : 0,
                'volumeBars' => $this->buildVolumeBars($sets, $period, $from, $to),
                'muscleGroups' => $this->buildMuscleGroups($sets, $previousSets),
                'progression' => $this->buildProgression($profile),
                'sessions' => $this->buildSessionBubbles($sets),
                'funFact' => $this->buildFunFact($totalVolume),
            ];
        } catch (\Throwable) {
            return $this->fallback($period);
        }
    }

    /**
     * @return array{0: \DateTimeImmutable, 1: \DateTimeImmutable}
     */
    private function periodRange(string $period, \DateTimeImmutable $now): array
    {
        return match ($period) {
            'month' => [$now->modify('first day of this month')->setTime(0, 0), $now->modify('first day of next month')->setTime(0, 0)],
            'quarter' => [$now->modify('-3 months')->setTime(0, 0), $now->modify('+1 day')->setTime(0, 0)],
            default => [$now->modify('monday this week')->setTime(0, 0), $now->modify('monday this week')->setTime(0, 0)->modify('+7 days')],
        };
    }

    private function previousRangeStart(\DateTimeImmutable $from, \DateTimeImmutable $to): \DateTimeImmutable
    {
        return $from->modify('-'.($to->getTimestamp() - $from->getTimestamp()).' seconds');
    }

    /**
     * @param list<WorkoutSet> $sets
     *
     * @return list<array<string, mixed>>
     */
    private function buildVolumeBars(array $sets, string $period, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $buckets = [];

        if ('week' === $period) {
            for ($index = 0; $index < 7; ++$index) {
                $date = $from->modify('+'.$index.' days');
                $buckets[$date->format('Y-m-d')] = ['label' => ['L', 'M', 'M', 'J', 'V', 'S', 'D'][$index], 'volume' => 0.0];
            }
        } else {
            $cursor = $from;
            $index = 1;

            while ($cursor < $to) {
                $key = $cursor->format('Y-m-d');
                $buckets[$key] = ['label' => 'S'.$index, 'volume' => 0.0];
                $cursor = $cursor->modify('+7 days');
                ++$index;
            }
        }

        foreach ($sets as $set) {
            $completedAt = $set->getCompletedAt();

            if (!$completedAt) {
                continue;
            }

            $key = 'week' === $period ? $completedAt->format('Y-m-d') : $this->weekBucketKey($completedAt, array_keys($buckets));

            if (isset($buckets[$key])) {
                $buckets[$key]['volume'] += $set->getVolume();
            }
        }

        $max = max(array_column($buckets, 'volume')) ?: 1.0;

        return array_map(fn (array $bucket): array => [
            'label' => $bucket['label'],
            'kg' => (int) round($bucket['volume']),
            'tons' => $this->formatTons($bucket['volume']),
            'height' => $bucket['volume'] <= 0 ? 8 : max(18, (int) round(($bucket['volume'] / $max) * 100)),
            'active' => $bucket['volume'] >= $max && $bucket['volume'] > 0,
        ], array_values($buckets));
    }

    /**
     * @param list<string> $keys
     */
    private function weekBucketKey(\DateTimeImmutable $date, array $keys): string
    {
        $selected = $keys[0] ?? $date->format('Y-m-d');

        foreach ($keys as $key) {
            if ($date >= new \DateTimeImmutable($key)) {
                $selected = $key;
            }
        }

        return $selected;
    }

    /**
     * @param list<WorkoutSet> $sets
     * @param list<WorkoutSet> $previousSets
     *
     * @return list<array<string, mixed>>
     */
    private function buildMuscleGroups(array $sets, array $previousSets): array
    {
        $volumes = [];
        $previousVolumes = [];

        foreach ($sets as $set) {
            $muscleGroup = $set->getSessionExercise()->getExercise()->getMuscleGroup();
            $volumes[$muscleGroup] = ($volumes[$muscleGroup] ?? 0.0) + $set->getVolume();
        }

        foreach ($previousSets as $set) {
            $muscleGroup = $set->getSessionExercise()->getExercise()->getMuscleGroup();
            $previousVolumes[$muscleGroup] = ($previousVolumes[$muscleGroup] ?? 0.0) + $set->getVolume();
        }

        arsort($volumes);
        $total = array_sum($volumes) ?: 1.0;
        $colors = ['#ccff00', '#34d399', '#22d3ee', '#c084fc'];
        $index = 0;

        return array_map(function (string $muscleGroup, float $volume) use ($previousVolumes, $total, $colors, &$index): array {
            $percent = (int) round(($volume / $total) * 100);
            $previousVolume = $previousVolumes[$muscleGroup] ?? 0.0;
            $deltaKg = $volume - $previousVolume;
            $deltaPercent = $previousVolume > 0 ? (int) round(($deltaKg / $previousVolume) * 100) : ($volume > 0 ? 100 : 0);

            return [
                'name' => $muscleGroup,
                'kg' => (int) round($volume),
                'tons' => $this->formatTons($volume),
                'percent' => $percent,
                'deltaKg' => $this->formatSigned($deltaKg),
                'deltaPercent' => $deltaPercent,
                'direction' => $deltaKg >= 0 ? 'up' : 'down',
                'color' => $colors[$index++ % count($colors)],
                'dashOffset' => 302 - (int) round(302 * ($percent / 100)),
            ];
        }, array_keys($volumes), array_values($volumes));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildProgression(UserProfile $profile): array
    {
        return array_map(function (PersonalRecord $record): array {
            $set = $record->getWorkoutSet();
            $previous = $record->getPreviousValue();
            $delta = $previous ? $record->getValue() - $previous : $record->getValue();
            $deltaPercent = $previous && $previous > 0 ? (int) round(($delta / $previous) * 100) : 100;

            return [
                'exercise' => $record->getExercise()->getName(),
                'muscleGroup' => $record->getExercise()->getMuscleGroup(),
                'value' => $this->formatNumber($set->getWeight()),
                'unit' => 'kg x '.$set->getReps(),
                'deltaKg' => $this->formatSigned($delta),
                'deltaPercent' => $deltaPercent,
                'direction' => $delta >= 0 ? 'up' : 'down',
            ];
        }, $this->recordRepository->findRecentForProfile($profile, 4));
    }

    /**
     * @param list<WorkoutSet> $sets
     *
     * @return list<array<string, mixed>>
     */
    private function buildSessionBubbles(array $sets): array
    {
        $sessions = [];

        foreach ($sets as $set) {
            $session = $set->getSessionExercise()->getSession();
            $id = $session->getId() ?? spl_object_id($session);

            if (!isset($sessions[$id])) {
                $sessions[$id] = [
                    'name' => $session->getName(),
                    'date' => $session->getCompletedAt()?->format('d/m') ?? $session->getStartedAt()->format('d/m'),
                    'volume' => 0.0,
                    'sets' => 0,
                    'durationMinutes' => $session->getDurationSeconds() ? (int) ceil($session->getDurationSeconds() / 60) : 0,
                ];
            }

            $sessions[$id]['volume'] += $set->getVolume();
            ++$sessions[$id]['sets'];
        }

        $maxVolume = max(array_column($sessions, 'volume') ?: [1]) ?: 1.0;
        $position = 0;

        return array_map(function (array $session) use ($maxVolume, &$position): array {
            $volumeRatio = $session['volume'] / $maxVolume;
            $x = 14 + ($position++ * 28) % 76;

            return [
                'name' => $session['name'],
                'date' => $session['date'],
                'kg' => (int) round($session['volume']),
                'tons' => $this->formatTons($session['volume']),
                'sets' => $session['sets'],
                'durationMinutes' => $session['durationMinutes'],
                'x' => $x,
                'y' => 18 + (int) round((1 - $volumeRatio) * 54),
                'size' => 32 + (int) round($volumeRatio * 24),
            ];
        }, array_slice(array_values($sessions), -4));
    }

    private function buildFunFact(float $volume): array
    {
        $elephants = max(1, (int) round($volume / 6000));

        return [
            'title' => 'Exploit de la semaine',
            'text' => 'Avec '.$this->formatTons($volume).'T, tu as souleve environ '.$elephants.' elephant'.($elephants > 1 ? 's' : '').' d\'Afrique.',
            'equivalent' => $elephants,
        ];
    }

    /**
     * @param list<WorkoutSet> $sets
     */
    private function sumVolume(array $sets): float
    {
        return array_sum(array_map(static fn (WorkoutSet $set): float => $set->getVolume(), $sets));
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

    private function formatSigned(float $value): string
    {
        return ($value >= 0 ? '+' : '').$this->formatNumber($value).'kg';
    }

    /**
     * @return array<string, mixed>
     */
    private function fallback(string $period = 'week'): array
    {
        return [
            'period' => \in_array($period, ['week', 'month', 'quarter'], true) ? $period : 'week',
            'totalVolumeKg' => 0,
            'totalVolumeTons' => '0',
            'trendPercent' => 0,
            'volumeBars' => [],
            'muscleGroups' => [],
            'progression' => [],
            'sessions' => [],
            'funFact' => ['title' => 'Exploit de la semaine', 'text' => 'Aucune donnee disponible pour le moment.', 'equivalent' => 0],
        ];
    }
}
