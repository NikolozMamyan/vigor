<?php

namespace App\Service\Workout;

use App\Entity\Exercise;
use App\Entity\UserProfile;
use App\Entity\WorkoutProgram;
use App\Entity\WorkoutProgramExercise;
use App\Repository\WorkoutProgramExerciseReaderInterface;
use Doctrine\ORM\EntityManagerInterface;

final class WorkoutProgramService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly WorkoutProgramExerciseReaderInterface $programExerciseRepository,
    ) {
    }

    /**
     * @param list<array{exercise: Exercise, targetSets?: int, targetRepsMin?: int, targetRepsMax?: int, targetWeight?: float|null, restSeconds?: int}> $exerciseConfigs
     */
    public function create(UserProfile $profile, Exercise $firstExercise, string $name, array $exerciseConfigs = []): WorkoutProgram
    {
        $exerciseConfigs = $exerciseConfigs ?: [['exercise' => $firstExercise]];

        $program = (new WorkoutProgram($profile))
            ->setName($this->validateName($name))
            ->setDescription('Programme personnalise')
            ->setEstimatedDurationMinutes($this->estimateDurationMinutes($exerciseConfigs));

        $this->entityManager->persist($program);
        $this->persistProgramExercises($program, $exerciseConfigs);
        $this->entityManager->flush();

        return $program;
    }

    /**
     * @param list<array{exercise: Exercise, targetSets?: int, targetRepsMin?: int, targetRepsMax?: int, targetWeight?: float|null, restSeconds?: int}> $exerciseConfigs
     */
    public function update(WorkoutProgram $program, string $name, array $exerciseConfigs): WorkoutProgram
    {
        if ([] === $exerciseConfigs) {
            throw new \InvalidArgumentException('Program must contain at least one exercise.');
        }

        $program
            ->setName($this->validateName($name))
            ->setEstimatedDurationMinutes($this->estimateDurationMinutes($exerciseConfigs));

        foreach ($this->programExerciseRepository->findForProgram($program) as $programExercise) {
            $this->entityManager->remove($programExercise);
        }

        $this->persistProgramExercises($program, $exerciseConfigs);
        $this->entityManager->flush();

        return $program;
    }

    public function delete(WorkoutProgram $program): void
    {
        $this->entityManager->remove($program);
        $this->entityManager->flush();
    }

    /**
     * @param list<array{exercise: Exercise, targetSets?: int}> $exerciseConfigs
     */
    private function estimateDurationMinutes(array $exerciseConfigs): int
    {
        $sets = array_sum(array_map(static fn (array $config): int => (int) ($config['targetSets'] ?? 3), $exerciseConfigs));

        return max(20, $sets * 4);
    }

    /**
     * @param list<array{exercise: Exercise, targetSets?: int, targetRepsMin?: int, targetRepsMax?: int, targetWeight?: float|null, restSeconds?: int}> $exerciseConfigs
     */
    private function persistProgramExercises(WorkoutProgram $program, array $exerciseConfigs): void
    {
        foreach ($exerciseConfigs as $index => $config) {
            $targetSets = $this->positiveInt($config['targetSets'] ?? 3, 'Target sets');
            $targetRepsMin = $this->positiveInt($config['targetRepsMin'] ?? 8, 'Target reps min');
            $targetRepsMax = $this->positiveInt($config['targetRepsMax'] ?? $targetRepsMin, 'Target reps max');
            $restSeconds = $this->positiveInt($config['restSeconds'] ?? 90, 'Rest seconds');
            $targetWeight = $config['targetWeight'] ?? null;

            if ($targetRepsMax < $targetRepsMin) {
                throw new \InvalidArgumentException('Target reps max must be greater than or equal to target reps min.');
            }

            if (null !== $targetWeight && $targetWeight < 0) {
                throw new \InvalidArgumentException('Target weight must be greater than or equal to zero.');
            }

            $programExercise = (new WorkoutProgramExercise($program, $config['exercise']))
                ->setPosition($index + 1)
                ->setTargetSets($targetSets)
                ->setTargetRepsMin($targetRepsMin)
                ->setTargetRepsMax($targetRepsMax)
                ->setTargetWeight($targetWeight)
                ->setRestSeconds($restSeconds);

            $this->entityManager->persist($programExercise);
        }
    }

    private function validateName(string $name): string
    {
        $name = trim($name);

        if (mb_strlen($name) < 2) {
            throw new \InvalidArgumentException('Program name must contain at least 2 characters.');
        }

        return $name;
    }

    private function positiveInt(int $value, string $label): int
    {
        if ($value <= 0) {
            throw new \InvalidArgumentException($label.' must be greater than zero.');
        }

        return $value;
    }
}
