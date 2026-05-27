<?php

namespace App\Service\Records;

use App\Entity\PersonalRecord;
use App\Repository\PersonalRecordRepository;
use App\Repository\UserProfileRepository;

final class RecordsViewService
{
    public function __construct(
        private readonly UserProfileRepository $profileRepository,
        private readonly PersonalRecordRepository $recordRepository,
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
                return $this->emptyView();
            }

            $records = $this->recordRepository->findForProfileByMetric($profile, PersonalRecord::METRIC_ESTIMATED_1RM, 120);
            $byExercise = [];

            foreach ($records as $record) {
                $exerciseId = $record->getExercise()->getId();

                if (null === $exerciseId) {
                    continue;
                }

                $byExercise[$exerciseId][] = $record;
            }

            $items = [];
            $recordsById = [];
            $categories = [];

            foreach ($byExercise as $exerciseId => $exerciseRecords) {
                $best = $this->bestRecord($exerciseRecords);

                if (!$best) {
                    continue;
                }

                $item = $this->formatRecord($best, $exerciseRecords);
                $items[] = $item;
                $recordsById[(string) $exerciseId] = $item;
                $categories[$item['category']] = true;
            }

            usort($items, static fn (array $a, array $b): int => $b['estimatedOneRepMax'] <=> $a['estimatedOneRepMax']);
            $featured = $items[0] ?? null;
            $list = array_values(array_filter($items, static fn (array $item): bool => $featured && $item['id'] !== $featured['id']));

            return [
                'featured' => $featured,
                'records' => $list,
                'filters' => array_keys($categories),
                'recordsJson' => json_encode($recordsById, \JSON_THROW_ON_ERROR),
            ];
        } catch (\Throwable) {
            return $this->emptyView();
        }
    }

    /**
     * @param list<PersonalRecord> $records
     */
    private function bestRecord(array $records): ?PersonalRecord
    {
        usort($records, static fn (PersonalRecord $a, PersonalRecord $b): int => $b->getValue() <=> $a->getValue());

        return $records[0] ?? null;
    }

    /**
     * @param list<PersonalRecord> $exerciseRecords
     *
     * @return array<string, mixed>
     */
    private function formatRecord(PersonalRecord $record, array $exerciseRecords): array
    {
        $set = $record->getWorkoutSet();
        $history = array_slice($exerciseRecords, 0, 5);
        usort($history, static fn (PersonalRecord $a, PersonalRecord $b): int => $a->getAchievedAt()->getTimestamp() <=> $b->getAchievedAt()->getTimestamp());
        $previous = $record->getPreviousValue();
        $delta = null !== $previous ? $record->getValue() - $previous : $record->getValue();

        return [
            'id' => $record->getExercise()->getId(),
            'name' => $record->getExercise()->getName(),
            'category' => $record->getExercise()->getMuscleGroup(),
            'date' => $this->relativeDateLabel($record->getAchievedAt()),
            'weight' => $this->formatNumber($set->getWeight()),
            'reps' => $set->getReps(),
            'estimatedOneRepMax' => (float) $this->formatNumber($record->getValue()),
            'estimatedOneRepMaxLabel' => $this->formatNumber($record->getValue()),
            'trend' => $delta > 0 ? '+'.$this->formatNumber($delta).'kg' : 'NEW',
            'trendPositive' => $delta > 0,
            'volume' => $this->formatNumber($set->getVolume()),
            'history' => array_map(fn (PersonalRecord $historyRecord): array => $this->formatHistoryRecord($historyRecord), $history),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatHistoryRecord(PersonalRecord $record): array
    {
        $set = $record->getWorkoutSet();

        return [
            'date' => $this->shortDateLabel($record->getAchievedAt()),
            'weight' => $this->formatNumber($set->getWeight()),
            'reps' => $set->getReps(),
            'estimatedOneRepMax' => $this->formatNumber($record->getValue()),
            'volume' => $this->formatNumber($set->getVolume()),
        ];
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

        if ($days < 14) {
            return 'Il y a '.$days.'j';
        }

        return $this->shortDateLabel($date);
    }

    private function shortDateLabel(\DateTimeImmutable $date): string
    {
        $months = [1 => 'Jan', 'Fev', 'Mar', 'Avr', 'Mai', 'Juin', 'Juil', 'Aout', 'Sep', 'Oct', 'Nov', 'Dec'];

        return $date->format('j').' '.$months[(int) $date->format('n')];
    }

    private function formatNumber(float $value): string
    {
        $rounded = round($value, 1);

        return 0.0 === fmod($rounded, 1.0) ? (string) (int) $rounded : number_format($rounded, 1, '.', '');
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyView(): array
    {
        return [
            'featured' => null,
            'records' => [],
            'filters' => [],
            'recordsJson' => '{}',
        ];
    }
}
