<?php

namespace App\Entity;

use App\Repository\PersonalRecordRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PersonalRecordRepository::class)]
#[ORM\Index(columns: ['metric'], name: 'idx_personal_record_metric')]
#[ORM\Index(columns: ['achieved_at'], name: 'idx_personal_record_achieved_at')]
class PersonalRecord
{
    public const METRIC_ESTIMATED_1RM = 'estimated_1rm';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private UserProfile $profile;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Exercise $exercise;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private WorkoutSet $workoutSet;

    #[ORM\Column(length: 40)]
    private string $metric = self::METRIC_ESTIMATED_1RM;

    #[ORM\Column(type: Types::DECIMAL, precision: 7, scale: 2)]
    private string $value;

    #[ORM\Column(type: Types::DECIMAL, precision: 7, scale: 2, nullable: true)]
    private ?string $previousValue = null;

    #[ORM\Column]
    private \DateTimeImmutable $achievedAt;

    public function __construct(UserProfile $profile, Exercise $exercise, WorkoutSet $workoutSet, float $value)
    {
        $this->profile = $profile;
        $this->exercise = $exercise;
        $this->workoutSet = $workoutSet;
        $this->value = number_format($value, 2, '.', '');
        $this->achievedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProfile(): UserProfile
    {
        return $this->profile;
    }

    public function getExercise(): Exercise
    {
        return $this->exercise;
    }

    public function getWorkoutSet(): WorkoutSet
    {
        return $this->workoutSet;
    }

    public function getMetric(): string
    {
        return $this->metric;
    }

    public function setMetric(string $metric): self
    {
        $this->metric = $metric;

        return $this;
    }

    public function getValue(): float
    {
        return (float) $this->value;
    }

    public function setValue(float $value): self
    {
        $this->value = number_format($value, 2, '.', '');

        return $this;
    }

    public function getPreviousValue(): ?float
    {
        return null === $this->previousValue ? null : (float) $this->previousValue;
    }

    public function setPreviousValue(?float $previousValue): self
    {
        $this->previousValue = null === $previousValue ? null : number_format($previousValue, 2, '.', '');

        return $this;
    }

    public function getAchievedAt(): \DateTimeImmutable
    {
        return $this->achievedAt;
    }

    public function setAchievedAt(\DateTimeImmutable $achievedAt): self
    {
        $this->achievedAt = $achievedAt;

        return $this;
    }
}
