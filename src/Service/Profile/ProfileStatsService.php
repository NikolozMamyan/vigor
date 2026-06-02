<?php

namespace App\Service\Profile;

use App\Entity\UserProfile;
use App\Repository\PersonalRecordRepository;
use App\Repository\WorkoutSessionRepository;
use App\Repository\WorkoutSetRepository;
use App\Service\Auth\CurrentUserProfileProvider;
use App\Service\Dashboard\WeeklyGoalService;
use Symfony\Component\HttpFoundation\RequestStack;

final class ProfileStatsService
{
    public function __construct(
        private readonly CurrentUserProfileProvider $currentUser,
        private readonly WorkoutSessionRepository $sessionRepository,
        private readonly WorkoutSetRepository $setRepository,
        private readonly PersonalRecordRepository $recordRepository,
        private readonly WeeklyGoalService $weeklyGoalService,
        private readonly RequestStack $requestStack,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function build(): array
    {
        try {
            $profile = $this->currentUser->getProfile();

            if (!$profile) {
                return $this->fallback();
            }

            $weeklyGoal = $this->weeklyGoalService->buildProgress($profile);
            $recordCount = $this->recordRepository->countForProfile($profile);
            $completedSessions = $this->sessionRepository->countCompletedForProfile($profile);

            return [
                'profile' => $this->normalizeProfile($profile),
                'stats' => [
                    ['label' => 'Seances', 'value' => (string) $completedSessions, 'icon' => 'calendar-check'],
                    ['label' => 'Volume total', 'value' => $this->formatNumber($this->setRepository->sumCompletedVolumeForProfile($profile) / 1000), 'suffix' => 'T', 'icon' => 'dumbbell'],
                    ['label' => 'Records battus', 'value' => (string) $recordCount, 'icon' => 'trophy'],
                    ['label' => 'Plus longue serie', 'value' => (string) $this->sessionRepository->longestCompletedStreakDays($profile), 'suffix' => 'jours', 'icon' => 'flame'],
                ],
                'weeklyGoal' => $weeklyGoal,
                'badges' => $this->buildBadges($completedSessions, $recordCount, $weeklyGoal['volume']['percent']),
                'settings' => [
                    ['label' => 'Preferences', 'description' => 'Unite '.$profile->getPreferredWeightUnit().' - record '.$profile->getRecordMetricPreference(), 'icon' => 'settings'],
                    ['label' => 'Objectif hebdo', 'description' => $profile->getWeeklyWorkoutGoal().' seances - '.$this->formatNumber($profile->getWeeklyVolumeGoal() / 1000).'T', 'icon' => 'target'],
                    ['label' => 'Confidentialite', 'description' => 'Donnees locales V1', 'icon' => 'shield-check'],
                    ['label' => 'Aide & support', 'description' => 'FAQ, nous contacter', 'icon' => 'help-circle'],
                ],
            ];
        } catch (\Throwable) {
            return $this->fallback();
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeProfile(UserProfile $profile): array
    {
        return [
            'displayName' => $profile->getDisplayName(),
            'username' => $profile->getUsername(),
            'avatarUrl' => $this->absoluteAvatarUrl($profile->getAvatarUrl()),
            'initials' => $this->initials($profile->getDisplayName()),
            'joinedLabel' => $this->joinedLabel($profile->getJoinedAt()),
            'levelLabel' => 'Niveau '.$this->levelFor($profile).' - Avance',
        ];
    }

    /**
     * @return list<array{label: string, icon: string, locked: bool}>
     */
    private function buildBadges(int $completedSessions, int $recordCount, int $weeklyVolumePercent): array
    {
        return [
            ['label' => 'Depart', 'icon' => 'play', 'locked' => $completedSessions < 1],
            ['label' => 'Discipline', 'icon' => 'medal', 'locked' => $completedSessions < 4],
            ['label' => 'Champion', 'icon' => 'trophy', 'locked' => $recordCount < 1],
            ['label' => 'Objectif', 'icon' => 'target', 'locked' => $weeklyVolumePercent < 100],
            ['label' => 'Volume', 'icon' => 'dumbbell', 'locked' => $completedSessions < 10],
            ['label' => '???', 'icon' => 'lock', 'locked' => true],
            ['label' => '???', 'icon' => 'lock', 'locked' => true],
            ['label' => '???', 'icon' => 'lock', 'locked' => true],
        ];
    }

    private function levelFor(UserProfile $profile): int
    {
        return max(1, min(99, (int) floor($this->sessionRepository->countCompletedForProfile($profile) / 12) + 1));
    }

    private function joinedLabel(\DateTimeImmutable $joinedAt): string
    {
        $months = [1 => 'Jan', 'Fev', 'Mar', 'Avr', 'Mai', 'Juin', 'Juil', 'Aout', 'Sep', 'Oct', 'Nov', 'Dec'];

        return ($months[(int) $joinedAt->format('n')] ?? $joinedAt->format('M')).' '.$joinedAt->format('Y');
    }

    private function formatNumber(float $value): string
    {
        $rounded = round($value, 1);

        return 0.0 === fmod($rounded, 1.0) ? (string) (int) $rounded : number_format($rounded, 1, '.', '');
    }

    /**
     * @return array<string, mixed>
     */
    private function fallback(): array
    {
        return [
            'profile' => [
                'displayName' => 'Profil indisponible',
                'username' => '',
                'avatarUrl' => null,
                'initials' => '--',
                'joinedLabel' => '',
                'levelLabel' => 'Aucune donnee',
            ],
            'stats' => [
                ['label' => 'Seances', 'value' => '0', 'icon' => 'calendar-check'],
                ['label' => 'Volume total', 'value' => '0', 'suffix' => 'T', 'icon' => 'dumbbell'],
                ['label' => 'Records battus', 'value' => '0', 'icon' => 'trophy'],
                ['label' => 'Plus longue serie', 'value' => '0', 'suffix' => 'jours', 'icon' => 'flame'],
            ],
            'weeklyGoal' => [
                'workouts' => ['current' => 0, 'target' => 4, 'percent' => 0],
                'volume' => ['current' => 0, 'target' => 14000, 'percent' => 0, 'trendPercent' => 0],
                'trainingMinutes' => ['current' => 0, 'target' => 180, 'percent' => 0],
            ],
            'badges' => [],
            'settings' => [
                ['label' => 'Objectif hebdo', 'description' => '4 seances - 14T', 'icon' => 'target'],
            ],
        ];
    }

    private function absoluteAvatarUrl(?string $avatarUrl): ?string
    {
        if (null === $avatarUrl || '' === $avatarUrl || str_starts_with($avatarUrl, 'http://') || str_starts_with($avatarUrl, 'https://')) {
            return $avatarUrl ?: null;
        }

        $request = $this->requestStack->getCurrentRequest();

        if (!$request) {
            return $avatarUrl;
        }

        return rtrim($request->getSchemeAndHttpHost(), '/').'/'.ltrim($avatarUrl, '/');
    }

    private function initials(string $name): string
    {
        $parts = array_values(array_filter(preg_split('/\s+/', trim($name)) ?: []));
        $letters = array_map(static fn (string $part): string => mb_substr($part, 0, 1), array_slice($parts, 0, 2));

        return mb_strtoupper(implode('', $letters) ?: 'VI');
    }
}
