<?php

namespace App\Tests\Service\Workout;

use App\Entity\Exercise;
use App\Entity\PersonalRecord;
use App\Entity\UserProfile;
use App\Entity\WorkoutSession;
use App\Entity\WorkoutSessionExercise;
use App\Entity\WorkoutSet;
use App\Service\Workout\PersonalRecordDetector;
use PHPUnit\Framework\TestCase;

final class PersonalRecordDetectorTest extends TestCase
{
    public function testItCreatesFirstRecord(): void
    {
        $detector = new PersonalRecordDetector();

        self::assertTrue($detector->shouldCreateRecord(100.0, null));
    }

    public function testItCreatesRecordOnlyWhenCandidateIsHigher(): void
    {
        $detector = new PersonalRecordDetector();
        $currentBest = $this->recordWithValue(120.0);

        self::assertFalse($detector->shouldCreateRecord(120.0, $currentBest));
        self::assertFalse($detector->shouldCreateRecord(119.99, $currentBest));
        self::assertTrue($detector->shouldCreateRecord(120.01, $currentBest));
    }

    public function testItBuildsRecordWithPreviousValue(): void
    {
        $profile = new UserProfile();
        $exercise = (new Exercise())
            ->setName('Squat')
            ->setSlug('squat')
            ->setMuscleGroup('Jambes')
            ->setEquipment('Barre');
        $session = new WorkoutSession($profile);
        $sessionExercise = new WorkoutSessionExercise($session, $exercise);
        $set = new WorkoutSet($sessionExercise);
        $currentBest = $this->recordWithValue(120.0);

        $record = (new PersonalRecordDetector())->buildRecord($profile, $exercise, $set, 125.0, $currentBest);

        self::assertSame(125.0, $record->getValue());
        self::assertSame(120.0, $record->getPreviousValue());
        self::assertSame('estimated_1rm', $record->getMetric());
    }

    private function recordWithValue(float $value): PersonalRecord
    {
        $profile = new UserProfile();
        $exercise = (new Exercise())
            ->setName('Developpe couche')
            ->setSlug('developpe-couche')
            ->setMuscleGroup('Pectoraux')
            ->setEquipment('Barre');
        $session = new WorkoutSession($profile);
        $sessionExercise = new WorkoutSessionExercise($session, $exercise);
        $set = new WorkoutSet($sessionExercise);

        return new PersonalRecord($profile, $exercise, $set, $value);
    }
}
