<?php

namespace App\Entity;

use App\Repository\WorkoutSetRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: WorkoutSetRepository::class)]
#[ORM\Index(columns: ['completed_at'], name: 'idx_workout_set_completed_at')]
class WorkoutSet
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private WorkoutSessionExercise $sessionExercise;

    #[ORM\Column]
    private int $position = 1;

    #[ORM\Column(type: Types::DECIMAL, precision: 6, scale: 2)]
    private string $weight = '0.00';

    #[ORM\Column]
    private int $reps = 0;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $completedAt = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 7, scale: 2, nullable: true)]
    private ?string $estimatedOneRepMax = null;

    public function __construct(WorkoutSessionExercise $sessionExercise)
    {
        $this->sessionExercise = $sessionExercise;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSessionExercise(): WorkoutSessionExercise
    {
        return $this->sessionExercise;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): self
    {
        $this->position = $position;

        return $this;
    }

    public function getWeight(): float
    {
        return (float) $this->weight;
    }

    public function setWeight(float $weight): self
    {
        $this->weight = number_format($weight, 2, '.', '');

        return $this;
    }

    public function getReps(): int
    {
        return $this->reps;
    }

    public function setReps(int $reps): self
    {
        $this->reps = $reps;

        return $this;
    }

    public function getVolume(): float
    {
        return $this->getWeight() * $this->reps;
    }

    public function getCompletedAt(): ?\DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function complete(\DateTimeImmutable $completedAt, float $estimatedOneRepMax): self
    {
        $this->completedAt = $completedAt;
        $this->estimatedOneRepMax = number_format($estimatedOneRepMax, 2, '.', '');

        return $this;
    }

    public function uncomplete(): self
    {
        $this->completedAt = null;
        $this->estimatedOneRepMax = null;

        return $this;
    }

    public function getEstimatedOneRepMax(): ?float
    {
        return null === $this->estimatedOneRepMax ? null : (float) $this->estimatedOneRepMax;
    }
}
