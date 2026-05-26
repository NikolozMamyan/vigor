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
use App\Repository\ExerciseRepository;
use App\Repository\UserProfileRepository;
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
        $this->seedActiveSession($profile, $exercises);

        $this->entityManager->flush();

        $io->success('VIGOR demo data is ready.');

        return Command::SUCCESS;
    }

    private function seedProfile(): UserProfile
    {
        $profile = $this->profileRepository->findOneBy(['username' => 'alexvigor']);

        if ($profile) {
            return $profile;
        }

        $profile = (new UserProfile())
            ->setDisplayName('Alex')
            ->setUsername('alexvigor')
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
            'developpe-couche' => ['Developpe couche', 'Pectoraux', 'Barre', Exercise::SOURCE_VIGOR, null, 'https://images.unsplash.com/photo-1534438327276-14e5300c3a48?q=80&w=1470&auto=format&fit=crop'],
            'squat' => ['Squat', 'Jambes', 'Barre', Exercise::SOURCE_VIGOR, null, 'https://images.unsplash.com/photo-1574680096145-d05b474e2155?q=80&w=1470&auto=format&fit=crop'],
            'souleve-de-terre' => ['Souleve de terre', 'Dos / Jambes', 'Barre', Exercise::SOURCE_VIGOR, null, 'https://images.unsplash.com/photo-1517344884509-a0c97ec11bcc?q=80&w=1470&auto=format&fit=crop'],
            'curl-biceps' => ['Curl Biceps', 'Bras', 'Halteres', Exercise::SOURCE_VIGOR, null, 'https://images.unsplash.com/photo-1581009146145-b5ef050c2e1e?q=80&w=1470&auto=format&fit=crop'],
            'traction' => ['Traction', 'Dos', 'Poids du corps', Exercise::SOURCE_VIGOR, null, 'https://images.unsplash.com/photo-1598971639058-fab3c3109a00?q=80&w=1470&auto=format&fit=crop'],
            'developpe-militaire' => ['Developpe militaire', 'Epaules', 'Barre', Exercise::SOURCE_VIGOR, null, 'https://images.unsplash.com/photo-1532029837206-abbe2b7620e3?q=80&w=1470&auto=format&fit=crop'],
            'tirage-elastique-maison' => ['Tirage elastique maison', 'Dos', 'Elastique', Exercise::SOURCE_CUSTOM, $profile, null],
        ];

        $exercises = [];

        foreach ($definitions as $slug => [$name, $muscleGroup, $equipment, $source, $createdByProfile, $imageUrl]) {
            $exercise = $this->exerciseRepository->findOneBy(['slug' => $slug]);

            if (!$exercise) {
                $exercise = (new Exercise())
                    ->setName($name)
                    ->setSlug($slug)
                    ->setMuscleGroup($muscleGroup)
                    ->setEquipment($equipment)
                    ->setSource($source)
                    ->setCreatedByProfile($createdByProfile)
                    ->setImageUrl($imageUrl);

                $this->entityManager->persist($exercise);
            }

            $exercises[$slug] = $exercise;
        }

        return $exercises;
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
            ->setDescription('Pecs, epaules, triceps');

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
                ->setRestSeconds(90);

            $this->entityManager->persist($programExercise);
        }
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
