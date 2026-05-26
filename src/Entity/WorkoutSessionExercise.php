<?php

namespace App\Entity;

use App\Repository\WorkoutSessionExerciseRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: WorkoutSessionExerciseRepository::class)]
#[ORM\Index(columns: ['position'], name: 'idx_workout_session_exercise_position')]
class WorkoutSessionExercise
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private WorkoutSession $session;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Exercise $exercise;

    #[ORM\Column]
    private int $position = 1;

    #[ORM\Column(nullable: true)]
    private ?int $targetSets = null;

    #[ORM\Column(nullable: true)]
    private ?int $targetRepsMin = null;

    #[ORM\Column(nullable: true)]
    private ?int $targetRepsMax = null;

    #[ORM\Column]
    private int $restSeconds = 90;

    public function __construct(WorkoutSession $session, Exercise $exercise)
    {
        $this->session = $session;
        $this->exercise = $exercise;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSession(): WorkoutSession
    {
        return $this->session;
    }

    public function getExercise(): Exercise
    {
        return $this->exercise;
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

    public function getTargetSets(): ?int
    {
        return $this->targetSets;
    }

    public function setTargetSets(?int $targetSets): self
    {
        $this->targetSets = $targetSets;

        return $this;
    }

    public function getTargetRepsMin(): ?int
    {
        return $this->targetRepsMin;
    }

    public function setTargetRepsMin(?int $targetRepsMin): self
    {
        $this->targetRepsMin = $targetRepsMin;

        return $this;
    }

    public function getTargetRepsMax(): ?int
    {
        return $this->targetRepsMax;
    }

    public function setTargetRepsMax(?int $targetRepsMax): self
    {
        $this->targetRepsMax = $targetRepsMax;

        return $this;
    }

    public function getRestSeconds(): int
    {
        return $this->restSeconds;
    }

    public function setRestSeconds(int $restSeconds): self
    {
        $this->restSeconds = $restSeconds;

        return $this;
    }
}
