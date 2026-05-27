<?php

namespace App\Command;

use App\Entity\Exercise;
use App\Entity\PersonalRecord;
use App\Entity\UserProfile;
use App\Entity\WorkoutSession;
use App\Entity\WorkoutSessionExercise;
use App\Entity\WorkoutSet;
use App\Entity\WorkoutProgram;
use App\Entity\WorkoutProgramExercise;
use App\Entity\WeeklyGoal;
use App\Repository\ExerciseRepository;
use App\Repository\UserProfileRepository;
use App\Repository\WeeklyGoalRepository;
use App\Repository\WorkoutProgramRepository;
use App\Repository\WorkoutSessionRepository;
use App\Service\Workout\OneRepMaxCalculator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:seed-demo-data',
    description: 'Seed the mono-user VIGOR demo dataset.',
)]
final class SeedDemoDataCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserProfileRepository $profileRepository,
        private readonly ExerciseRepository $exerciseRepository,
        private readonly WorkoutSessionRepository $sessionRepository,
        private readonly WorkoutProgramRepository $programRepository,
        private readonly WeeklyGoalRepository $weeklyGoalRepository,
        private readonly OneRepMaxCalculator $oneRepMaxCalculator,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $profile = $this->seedProfile();
        $exercises = $this->seedExercises($profile);
        $this->seedCompletedSession($profile, $exercises);
        $this->seedPrograms($profile, $exercises);
        $this->seedWeeklyGoal($profile);
        $this->seedActiveSession($profile, $exercises);

        $this->entityManager->flush();

        $io->success('VIGOR demo data is ready.');

        return Command::SUCCESS;
    }

    private function seedProfile(): UserProfile
    {
        $profile = $this->profileRepository->findOneBy(['username' => 'alexvigor']);

        if ($profile) {
            if (!$profile->getEmail()) {
                $profile->setEmail('alex@vigor.local');
            }

            if (!$profile->getPasswordHash()) {
                $profile->setPasswordHash(password_hash('vigor', PASSWORD_DEFAULT));
            }

            return $profile;
        }

        $profile = (new UserProfile())
            ->setDisplayName('Alex')
            ->setUsername('alexvigor')
            ->setEmail('alex@vigor.local')
            ->setPasswordHash(password_hash('vigor', PASSWORD_DEFAULT))
            ->setAvatarUrl('https://placehold.co/200x200/18181b/ccff00?text=AX')
            ->setJoinedAt(new \DateTimeImmutable('2025-01-08 09:00:00'))
            ->setWeeklyWorkoutGoal(4)
            ->setWeeklyVolumeGoal(14200)
            ->setPreferredWeightUnit('kg')
            ->setRecordMetricPreference(PersonalRecord::METRIC_ESTIMATED_1RM);

        $this->entityManager->persist($profile);

        return $profile;
    }

    /**
     * @return array<string, Exercise>
     */
    private function seedExercises(UserProfile $profile): array
    {
        $definitions = [
            // ── Pectoraux ───────────────────────────────────────────────────────────
            'developpe-couche' => ['Developpe couche', 'Pectoraux', 'Barre', Exercise::SOURCE_VIGOR, null, 'https://images.unsplash.com/photo-1534438327276-14e5300c3a48?q=80&w=1470&auto=format&fit=crop'],
            'developpe-incline-barre' => ['Developpe incline barre', 'Pectoraux', 'Barre', Exercise::SOURCE_VIGOR, null, 'https://images.unsplash.com/photo-1526506118085-60ce8714f8c5?q=80&w=1470&auto=format&fit=crop'],
            'developpe-incline-halteres' => ['Developpe incline halteres', 'Pectoraux', 'Halteres', Exercise::SOURCE_VIGOR, null, 'https://images.unsplash.com/photo-1533560904424-a0c61dc306fc?q=80&w=1470&auto=format&fit=crop'],
            'ecartes-halteres' => ['Ecartes halteres', 'Pectoraux', 'Halteres', Exercise::SOURCE_VIGOR, null, 'https://images.unsplash.com/photo-1571019614242-c5c5dee9f50b?q=80&w=1470&auto=format&fit=crop'],
            'pompes' => ['Pompes', 'Pectoraux', 'Poids du corps', Exercise::SOURCE_VIGOR, null, 'https://images.unsplash.com/photo-1598971457999-ca4ef48a9a71?q=80&w=1470&auto=format&fit=crop'],
            'cable-croise' => ['Cable croise', 'Pectoraux', 'Cable', Exercise::SOURCE_VIGOR, null, 'https://images.unsplash.com/photo-1516483638261-f4dbaf036963?q=80&w=1470&auto=format&fit=crop'],
            'pec-deck' => ['Pec deck', 'Pectoraux', 'Machine', Exercise::SOURCE_VIGOR, null, 'https://images.unsplash.com/photo-1583454110551-21f2fa2afe61?q=80&w=1470&auto=format&fit=crop'],
            'dips-pecs' => ['Dips pecs', 'Pectoraux', 'Poids du corps', Exercise::SOURCE_VIGOR, null, 'https://images.unsplash.com/photo-1601422407692-ec4eff3d9b19?q=80&w=1470&auto=format&fit=crop'],

            // ── Dos ─────────────────────────────────────────────────────────────────
            'traction' => ['Traction', 'Dos', 'Poids du corps', Exercise::SOURCE_VIGOR, null, 'https://images.unsplash.com/photo-1598971639058-fab3c3109a00?q=80&w=1470&auto=format&fit=crop'],
            'rowing-barre' => ['Rowing barre', 'Dos', 'Barre', Exercise::SOURCE_VIGOR, null, 'https://images.unsplash.com/photo-1517838277536-f5f99be501cd?q=80&w=1470&auto=format&fit=crop'],
            'rowing-haltere' => ['Rowing haltere', 'Dos', 'Halteres', Exercise::SOURCE_VIGOR, null, 'https://images.unsplash.com/photo-1549060279-7e168fcee0c2?q=80&w=1470&auto=format&fit=crop'],
            'tirage-vertical-poulie' => ['Tirage vertical poulie', 'Dos', 'Cable', Exercise::SOURCE_VIGOR, null, 'https://images.unsplash.com/photo-1604480132736-44c188fe4d20?q=80&w=1470&auto=format&fit=crop'],
            'tirage-horizontal-poulie' => ['Tirage horizontal poulie', 'Dos', 'Cable', Exercise::SOURCE_VIGOR, null, 'https://images.unsplash.com/photo-1540497077202-7c8a3999166f?q=80&w=1470&auto=format&fit=crop'],
            'pull-over' => ['Pull-over', 'Dos', 'Halteres', Exercise::SOURCE_VIGOR, null, 'https://images.unsplash.com/photo-1583454155184-870a1f63aebc?q=80&w=1470&auto=format&fit=crop'],
            'shrugs' => ['Shrugs', 'Dos', 'Halteres', Exercise::SOURCE_VIGOR, null, 'https://images.unsplash.com/photo-1556817411-31ae72fa3ea0?q=80&w=1470&auto=format&fit=crop'],
            'tirage-elastique-maison' => ['Tirage elastique maison', 'Dos', 'Elastique', Exercise::SOURCE_CUSTOM, $profile, null],

            // ── Dos / Jambes ─────────────────────────────────────────────────────────
            'souleve-de-terre' => ['Souleve de terre', 'Dos / Jambes', 'Barre', Exercise::SOURCE_VIGOR, null, 'https://images.unsplash.com/photo-1517344884509-a0c97ec11bcc?q=80&w=1470&auto=format&fit=crop'],
            'souleve-de-terre-roumain' => ['Souleve de terre roumain', 'Dos / Jambes', 'Barre', Exercise::SOURCE_VIGOR, null, 'https://images.unsplash.com/photo-1526506118085-60ce8714f8c5?q=80&w=1470&auto=format&fit=crop'],
            'souleve-de-terre-sumo' => ['Souleve de terre sumo', 'Dos / Jambes', 'Barre', Exercise::SOURCE_VIGOR, null, 'https://images.unsplash.com/photo-1517838277536-f5f99be501cd?q=80&w=1470&auto=format&fit=crop'],

            // ── Épaules ──────────────────────────────────────────────────────────────
            'developpe-militaire' => ['Developpe militaire', 'Epaules', 'Barre', Exercise::SOURCE_VIGOR, null, 'https://images.unsplash.com/photo-1532029837206-abbe2b7620e3?q=80&w=1470&auto=format&fit=crop'],
            'elevations-laterales' => ['Elevations laterales', 'Epaules', 'Halteres', Exercise::SOURCE_VIGOR, null, 'https://images.unsplash.com/photo-1597347316205-36f6c451902a?q=80&w=1470&auto=format&fit=crop'],
            'elevations-frontales' => ['Elevations frontales', 'Epaules', 'Halteres', Exercise::SOURCE_VIGOR, null, 'https://images.unsplash.com/photo-1566351557863-467d22aa1a16?q=80&w=1470&auto=format&fit=crop'],
            'oiseau' => ['Oiseau', 'Epaules', 'Halteres', Exercise::SOURCE_VIGOR, null, 'https://images.unsplash.com/photo-1567689265742-ba9fb7b0f90d?q=80&w=1470&auto=format&fit=crop'],
            'arnold-press' => ['Arnold press', 'Epaules', 'Halteres', Exercise::SOURCE_VIGOR, null, 'https://images.unsplash.com/photo-1518310383802-640c2de311b2?q=80&w=1470&auto=format&fit=crop'],
            'face-pull' => ['Face pull', 'Epaules', 'Cable', Exercise::SOURCE_VIGOR, null, 'https://images.unsplash.com/photo-1517836357463-d25dfeac3438?q=80&w=1470&auto=format&fit=crop'],
            'developpe-halteres-epaules' => ['Developpe halteres epaules', 'Epaules', 'Halteres', Exercise::SOURCE_VIGOR, null, 'https://images.unsplash.com/photo-1571019614242-c5c5dee9f50b?q=80&w=1470&auto=format&fit=crop'],

            // ── Bras ─────────────────────────────────────────────────────────────────
            'curl-biceps' => ['Curl Biceps', 'Bras', 'Halteres', Exercise::SOURCE_VIGOR, null, 'https://images.unsplash.com/photo-1581009146145-b5ef050c2e1e?q=80&w=1470&auto=format&fit=crop'],
            'curl-marteau' => ['Curl marteau', 'Bras', 'Halteres', Exercise::SOURCE_VIGOR, null, 'https://images.unsplash.com/photo-1611311814039-b9045b5fe31a?q=80&w=1470&auto=format&fit=crop'],
            'curl-barre' => ['Curl barre', 'Bras', 'Barre', Exercise::SOURCE_VIGOR, null, 'https://images.unsplash.com/photo-1534437821b-5b41de4fd7b2?q=80&w=1470&auto=format&fit=crop'],
            'curl-pupitre' => ['Curl pupitre', 'Bras', 'Halteres', Exercise::SOURCE_VIGOR, null, 'https://images.unsplash.com/photo-1498720066395-ce84a07e4fb7?q=80&w=1470&auto=format&fit=crop'],
            'french-press' => ['French press', 'Bras', 'Barre', Exercise::SOURCE_VIGOR, null, 'https://images.unsplash.com/photo-1595078475328-1ab05d0a6a0e?q=80&w=1470&auto=format&fit=crop'],
            'extensions-triceps-cable' => ['Extensions triceps cable', 'Bras', 'Cable', Exercise::SOURCE_VIGOR, null, 'https://images.unsplash.com/photo-1540497077202-7c8a3999166f?q=80&w=1470&auto=format&fit=crop'],
            'extensions-triceps-haltere' => ['Extensions triceps haltere', 'Bras', 'Halteres', Exercise::SOURCE_VIGOR, null, 'https://images.unsplash.com/photo-1583454155184-870a1f63aebc?q=80&w=1470&auto=format&fit=crop'],
            'dips-triceps' => ['Dips triceps', 'Bras', 'Poids du corps', Exercise::SOURCE_VIGOR, null, 'https://images.unsplash.com/photo-1526506118085-60ce8714f8c5?q=80&w=1470&auto=format&fit=crop'],

            // ── Jambes ───────────────────────────────────────────────────────────────
            'squat' => ['Squat', 'Jambes', 'Barre', Exercise::SOURCE_VIGOR, null, 'https://images.unsplash.com/photo-1574680096145-d05b474e2155?q=80&w=1470&auto=format&fit=crop'],
            'squat-hack' => ['Squat hack', 'Jambes', 'Machine', Exercise::SOURCE_VIGOR, null, 'https://images.unsplash.com/photo-1583454110551-21f2fa2afe61?q=80&w=1470&auto=format&fit=crop'],
            'goblet-squat' => ['Goblet squat', 'Jambes', 'Kettlebell', Exercise::SOURCE_VIGOR, null, 'https://images.unsplash.com/photo-1517836357463-d25dfeac3438?q=80&w=1470&auto=format&fit=crop'],
            'leg-press' => ['Leg press', 'Jambes', 'Machine', Exercise::SOURCE_VIGOR, null, 'https://images.unsplash.com/photo-1604480132736-44c188fe4d20?q=80&w=1470&auto=format&fit=crop'],
            'leg-extension' => ['Leg extension', 'Jambes', 'Machine', Exercise::SOURCE_VIGOR, null, 'https://images.unsplash.com/photo-1556817411-31ae72fa3ea0?q=80&w=1470&auto=format&fit=crop'],
            'leg-curl' => ['Leg curl', 'Jambes', 'Machine', Exercise::SOURCE_VIGOR, null, 'https://images.unsplash.com/photo-1490645935967-10de6ba17061?q=80&w=1470&auto=format&fit=crop'],
            'fentes' => ['Fentes', 'Jambes', 'Halteres', Exercise::SOURCE_VIGOR, null, 'https://images.unsplash.com/photo-1434682772747-f16d3ea162c3?q=80&w=1470&auto=format&fit=crop'],
            'fentes-bulgares' => ['Fentes bulgares', 'Jambes', 'Halteres', Exercise::SOURCE_VIGOR, null, 'https://images.unsplash.com/photo-1571019614242-c5c5dee9f50b?q=80&w=1470&auto=format&fit=crop'],
            'rdl-halteres' => ['RDL halteres', 'Jambes', 'Halteres', Exercise::SOURCE_VIGOR, null, 'https://images.unsplash.com/photo-1549060279-7e168fcee0c2?q=80&w=1470&auto=format&fit=crop'],

            // ── Fessiers ─────────────────────────────────────────────────────────────
            'hip-thrust' => ['Hip thrust', 'Fessiers', 'Barre', Exercise::SOURCE_VIGOR, null, 'https://images.unsplash.com/photo-1597347316205-36f6c451902a?q=80&w=1470&auto=format&fit=crop'],
            'abduction-machine' => ['Abduction machine', 'Fessiers', 'Machine', Exercise::SOURCE_VIGOR, null, 'https://images.unsplash.com/photo-1516483638261-f4dbaf036963?q=80&w=1470&auto=format&fit=crop'],
            'donkey-kick' => ['Donkey kick', 'Fessiers', 'Poids du corps', Exercise::SOURCE_VIGOR, null, 'https://images.unsplash.com/photo-1598971457999-ca4ef48a9a71?q=80&w=1470&auto=format&fit=crop'],

            // ── Abdominaux ───────────────────────────────────────────────────────────
            'crunch' => ['Crunch', 'Abdominaux', 'Poids du corps', Exercise::SOURCE_VIGOR, null, 'https://images.unsplash.com/photo-1544367567-0f2fcb009e0b?q=80&w=1470&auto=format&fit=crop'],
            'planche' => ['Planche', 'Abdominaux', 'Poids du corps', Exercise::SOURCE_VIGOR, null, 'https://images.unsplash.com/photo-1517438476312-10d79c077509?q=80&w=1470&auto=format&fit=crop'],
            'releve-de-jambes' => ['Releve de jambes', 'Abdominaux', 'Poids du corps', Exercise::SOURCE_VIGOR, null, 'https://images.unsplash.com/photo-1567689265742-ba9fb7b0f90d?q=80&w=1470&auto=format&fit=crop'],
            'russian-twist' => ['Russian twist', 'Abdominaux', 'Poids du corps', Exercise::SOURCE_VIGOR, null, 'https://images.unsplash.com/photo-1518310383802-640c2de311b2?q=80&w=1470&auto=format&fit=crop'],
            'mountain-climbers' => ['Mountain climbers', 'Abdominaux', 'Poids du corps', Exercise::SOURCE_VIGOR, null, 'https://images.unsplash.com/photo-1526506118085-60ce8714f8c5?q=80&w=1470&auto=format&fit=crop'],
            'crunch-cable' => ['Crunch cable', 'Abdominaux', 'Cable', Exercise::SOURCE_VIGOR, null, 'https://images.unsplash.com/photo-1540497077202-7c8a3999166f?q=80&w=1470&auto=format&fit=crop'],

            // ── Mollets ──────────────────────────────────────────────────────────────
            'mollets-debout' => ['Mollets debout', 'Mollets', 'Machine', Exercise::SOURCE_VIGOR, null, 'https://images.unsplash.com/photo-1571019614242-c5c5dee9f50b?q=80&w=1470&auto=format&fit=crop'],
            'mollets-assis' => ['Mollets assis', 'Mollets', 'Machine', Exercise::SOURCE_VIGOR, null, 'https://images.unsplash.com/photo-1583454110551-21f2fa2afe61?q=80&w=1470&auto=format&fit=crop'],
            'mollets-presse' => ['Mollets presse', 'Mollets', 'Machine', Exercise::SOURCE_VIGOR, null, 'https://images.unsplash.com/photo-1556817411-31ae72fa3ea0?q=80&w=1470&auto=format&fit=crop'],
        ];

        $exercises = [];

        foreach ($definitions as $slug => [$name, $muscleGroup, $equipment, $source, $createdByProfile, $imageUrl]) {
            $exercise = $this->exerciseRepository->findOneBy(['slug' => $slug]);

            if (!$exercise) {
                $exercise = (new Exercise())
                    ->setSlug($slug);

                $this->entityManager->persist($exercise);
            }

            $exercise
                ->setName($name)
                ->setMuscleGroup($muscleGroup)
                ->setEquipment($equipment)
                ->setSource($source)
                ->setCreatedByProfile($createdByProfile)
                ->setImageUrl($this->resolveSeedExerciseImageUrl($slug, $muscleGroup, $imageUrl));

            $exercises[$slug] = $exercise;
        }

        return $exercises;
    }

    private function resolveSeedExerciseImageUrl(string $slug, string $muscleGroup, ?string $imageUrl): ?string
    {
        if (null === $imageUrl) {
            return null;
        }

        $slugOverrides = [
            'developpe-couche' => 'https://images.unsplash.com/photo-1534438327276-14e5300c3a48?q=80&w=1470&auto=format&fit=crop',
            'developpe-incline-barre' => 'https://images.unsplash.com/photo-1526506118085-60ce8714f8c5?q=80&w=1470&auto=format&fit=crop',
            'developpe-incline-halteres' => 'https://images.unsplash.com/photo-1533560904424-a0c61dc306fc?q=80&w=1470&auto=format&fit=crop',
            'ecartes-halteres' => 'https://images.unsplash.com/photo-1571019614242-c5c5dee9f50b?q=80&w=1470&auto=format&fit=crop',
            'pompes' => 'https://images.unsplash.com/photo-1598971457999-ca4ef48a9a71?q=80&w=1470&auto=format&fit=crop',
            'traction' => 'https://images.unsplash.com/photo-1598971639058-fab3c3109a00?q=80&w=1470&auto=format&fit=crop',
            'rowing-barre' => 'https://images.unsplash.com/photo-1517838277536-f5f99be501cd?q=80&w=1470&auto=format&fit=crop',
            'rowing-haltere' => 'https://images.unsplash.com/photo-1549060279-7e168fcee0c2?q=80&w=1470&auto=format&fit=crop',
            'tirage-vertical-poulie' => 'https://images.unsplash.com/photo-1604480132736-44c188fe4d20?q=80&w=1470&auto=format&fit=crop',
            'tirage-horizontal-poulie' => 'https://images.unsplash.com/photo-1540497077202-7c8a3999166f?q=80&w=1470&auto=format&fit=crop',
            'souleve-de-terre' => 'https://images.unsplash.com/photo-1517344884509-a0c97ec11bcc?q=80&w=1470&auto=format&fit=crop',
            'developpe-militaire' => 'https://images.unsplash.com/photo-1532029837206-abbe2b7620e3?q=80&w=1470&auto=format&fit=crop',
            'elevations-laterales' => 'https://images.unsplash.com/photo-1597347316205-36f6c451902a?q=80&w=1470&auto=format&fit=crop',
            'curl-biceps' => 'https://images.unsplash.com/photo-1581009146145-b5ef050c2e1e?q=80&w=1470&auto=format&fit=crop',
            'curl-marteau' => 'https://images.unsplash.com/photo-1611311814039-b9045b5fe31a?q=80&w=1470&auto=format&fit=crop',
            'curl-barre' => 'https://images.unsplash.com/photo-1534437821b-5b41de4fd7b2?q=80&w=1470&auto=format&fit=crop',
            'squat' => 'https://images.unsplash.com/photo-1574680096145-d05b474e2155?q=80&w=1470&auto=format&fit=crop',
            'fentes' => 'https://images.unsplash.com/photo-1434682772747-f16d3ea162c3?q=80&w=1470&auto=format&fit=crop',
            'crunch' => 'https://images.unsplash.com/photo-1544367567-0f2fcb009e0b?q=80&w=1470&auto=format&fit=crop',
            'planche' => 'https://images.unsplash.com/photo-1517438476312-10d79c077509?q=80&w=1470&auto=format&fit=crop',
        ];

        if (isset($slugOverrides[$slug])) {
            return $slugOverrides[$slug];
        }

        return match ($muscleGroup) {
            'Pectoraux' => 'https://images.unsplash.com/photo-1534438327276-14e5300c3a48?q=80&w=1470&auto=format&fit=crop',
            'Dos' => 'https://images.unsplash.com/photo-1598971639058-fab3c3109a00?q=80&w=1470&auto=format&fit=crop',
            'Dos / Jambes' => 'https://images.unsplash.com/photo-1517344884509-a0c97ec11bcc?q=80&w=1470&auto=format&fit=crop',
            'Epaules' => 'https://images.unsplash.com/photo-1532029837206-abbe2b7620e3?q=80&w=1470&auto=format&fit=crop',
            'Bras' => 'https://images.unsplash.com/photo-1581009146145-b5ef050c2e1e?q=80&w=1470&auto=format&fit=crop',
            'Jambes' => 'https://images.unsplash.com/photo-1574680096145-d05b474e2155?q=80&w=1470&auto=format&fit=crop',
            'Fessiers' => 'https://images.unsplash.com/photo-1434682772747-f16d3ea162c3?q=80&w=1470&auto=format&fit=crop',
            'Abdominaux' => 'https://images.unsplash.com/photo-1517438476312-10d79c077509?q=80&w=1470&auto=format&fit=crop',
            'Mollets' => 'https://images.unsplash.com/photo-1556817411-31ae72fa3ea0?q=80&w=1470&auto=format&fit=crop',
            default => $imageUrl,
        };
    }

    /**
     * @param array<string, Exercise> $exercises
     */
    private function seedCompletedSession(UserProfile $profile, array $exercises): void
    {
        $existingSession = $this->sessionRepository->findOneBy([
            'profile' => $profile,
            'name' => 'Hypertrophie Push - Demo',
        ]);

        if ($existingSession) {
            return;
        }

        $session = (new WorkoutSession($profile))
            ->setName('Hypertrophie Push - Demo')
            ->setType(WorkoutSession::TYPE_FREE)
            ->setStartedAt(new \DateTimeImmutable('-3 days 18:00:00'));
        $session->complete(new \DateTimeImmutable('-3 days 18:47:00'));

        $this->entityManager->persist($session);

        $this->seedExerciseSets($profile, $session, $exercises['developpe-couche'], 1, [[80, 10], [90, 6], [95, 6]]);
        $this->seedExerciseSets($profile, $session, $exercises['squat'], 2, [[120, 8], [135, 5], [140, 5]]);
        $this->seedExerciseSets($profile, $session, $exercises['developpe-militaire'], 3, [[45, 10], [50, 8], [55, 6]]);
    }

    /**
     * @param array<string, Exercise> $exercises
     */
    private function seedActiveSession(UserProfile $profile, array $exercises): void
    {
        if ($this->sessionRepository->findActiveForProfile($profile)) {
            return;
        }

        $session = (new WorkoutSession($profile))
            ->setName('Seance libre')
            ->setType(WorkoutSession::TYPE_FREE);

        $this->entityManager->persist($session);

        $sessionExercise = (new WorkoutSessionExercise($session, $exercises['developpe-couche']))
            ->setPosition(1)
            ->setTargetSets(3)
            ->setTargetRepsMin(8)
            ->setTargetRepsMax(10)
            ->setRestSeconds(90);

        $this->entityManager->persist($sessionExercise);

        foreach ([[80, 10], [80, 8]] as $index => [$weight, $reps]) {
            $set = (new WorkoutSet($sessionExercise))
                ->setPosition($index + 1)
                ->setWeight($weight)
                ->setReps($reps);

            if (0 === $index) {
                $set->complete(new \DateTimeImmutable('-12 minutes'), $this->oneRepMaxCalculator->estimate($weight, $reps));
            }

            $this->entityManager->persist($set);
        }
    }

    /**
     * @param array<string, Exercise> $exercises
     */
    private function seedPrograms(UserProfile $profile, array $exercises): void
    {
        if ($this->programRepository->findOneBy(['profile' => $profile, 'name' => 'Hypertrophie Push'])) {
            return;
        }

        $program = (new WorkoutProgram($profile))
            ->setName('Hypertrophie Push')
            ->setDescription('Pecs, epaules, triceps')
            ->setEstimatedDurationMinutes(45);

        $this->entityManager->persist($program);

        foreach ([
            [$exercises['developpe-couche'], 1, 4, 6, 8],
            [$exercises['developpe-militaire'], 2, 3, 8, 10],
            [$exercises['curl-biceps'], 3, 3, 10, 12],
        ] as [$exercise, $position, $sets, $repsMin, $repsMax]) {
            $programExercise = (new WorkoutProgramExercise($program, $exercise))
                ->setPosition($position)
                ->setTargetSets($sets)
                ->setTargetRepsMin($repsMin)
                ->setTargetRepsMax($repsMax)
                ->setTargetWeight(match ($position) {
                    1 => 90.0,
                    2 => 50.0,
                    default => 18.0,
                })
                ->setRestSeconds(90);

            $this->entityManager->persist($programExercise);
        }
    }

    private function seedWeeklyGoal(UserProfile $profile): void
    {
        $weekStart = (new \DateTimeImmutable())->modify('monday this week')->setTime(0, 0);

        if ($this->weeklyGoalRepository->findForProfileAndWeek($profile, $weekStart)) {
            return;
        }

        $goal = (new WeeklyGoal($profile, $weekStart))
            ->setTargetWorkouts($profile->getWeeklyWorkoutGoal())
            ->setTargetVolume($profile->getWeeklyVolumeGoal())
            ->setTargetTrainingMinutes(180);

        $this->entityManager->persist($goal);
    }

    /**
     * @param list<array{0: int|float, 1: int}> $sets
     */
    private function seedExerciseSets(UserProfile $profile, WorkoutSession $session, Exercise $exercise, int $position, array $sets): void
    {
        $sessionExercise = (new WorkoutSessionExercise($session, $exercise))
            ->setPosition($position)
            ->setTargetSets(count($sets))
            ->setTargetRepsMin(6)
            ->setTargetRepsMax(10)
            ->setRestSeconds(90);

        $this->entityManager->persist($sessionExercise);

        $bestSet = null;
        $bestEstimate = 0.0;

        foreach ($sets as $index => [$weight, $reps]) {
            $estimate = $this->oneRepMaxCalculator->estimate((float) $weight, $reps);
            $set = (new WorkoutSet($sessionExercise))
                ->setPosition($index + 1)
                ->setWeight((float) $weight)
                ->setReps($reps)
                ->complete(new \DateTimeImmutable(sprintf('-3 days 18:%02d:00', 8 + ($position * 9) + $index)), $estimate);

            $this->entityManager->persist($set);

            if ($estimate > $bestEstimate) {
                $bestEstimate = $estimate;
                $bestSet = $set;
            }
        }

        if ($bestSet) {
            $record = new PersonalRecord($profile, $exercise, $bestSet, $bestEstimate);
            $record->setAchievedAt($bestSet->getCompletedAt() ?? new \DateTimeImmutable('-3 days'));
            $this->entityManager->persist($record);
        }
    }
}
