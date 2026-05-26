<?php

namespace App\Entity;

use App\Repository\WeeklyGoalRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: WeeklyGoalRepository::class)]
#[ORM\Index(columns: ['week_start_date'], name: 'idx_weekly_goal_week_start_date')]
#[ORM\UniqueConstraint(name: 'uniq_weekly_goal_profile_week', columns: ['profile_id', 'week_start_date'])]
class WeeklyGoal
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private UserProfile $profile;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $weekStartDate;

    #[ORM\Column]
    private int $targetWorkouts;

    #[ORM\Column]
    private int $targetVolume;

    #[ORM\Column]
    private int $targetTrainingMinutes;

    public function __construct(UserProfile $profile, \DateTimeImmutable $weekStartDate)
    {
        $this->profile = $profile;
        $this->weekStartDate = $weekStartDate->setTime(0, 0);
        $this->targetWorkouts = $profile->getWeeklyWorkoutGoal();
        $this->targetVolume = $profile->getWeeklyVolumeGoal();
        $this->targetTrainingMinutes = 180;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProfile(): UserProfile
    {
        return $this->profile;
    }

    public function getWeekStartDate(): \DateTimeImmutable
    {
        return $this->weekStartDate;
    }

    public function getTargetWorkouts(): int
    {
        return $this->targetWorkouts;
    }

    public function setTargetWorkouts(int $targetWorkouts): self
    {
        $this->targetWorkouts = $targetWorkouts;

        return $this;
    }

    public function getTargetVolume(): int
    {
        return $this->targetVolume;
    }

    public function setTargetVolume(int $targetVolume): self
    {
        $this->targetVolume = $targetVolume;

        return $this;
    }

    public function getTargetTrainingMinutes(): int
    {
        return $this->targetTrainingMinutes;
    }

    public function setTargetTrainingMinutes(int $targetTrainingMinutes): self
    {
        $this->targetTrainingMinutes = $targetTrainingMinutes;

        return $this;
    }
}
