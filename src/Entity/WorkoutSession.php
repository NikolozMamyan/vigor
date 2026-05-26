<?php

namespace App\Entity;

use App\Repository\WorkoutSessionRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: WorkoutSessionRepository::class)]
#[ORM\Index(columns: ['status'], name: 'idx_workout_session_status')]
#[ORM\Index(columns: ['started_at'], name: 'idx_workout_session_started_at')]
class WorkoutSession
{
    public const TYPE_FREE = 'free';
    public const TYPE_PROGRAM = 'program';

    public const STATUS_ACTIVE = 'active';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private UserProfile $profile;

    #[ORM\Column(length: 120)]
    private string $name = 'Seance libre';

    #[ORM\Column(length: 20)]
    private string $type = self::TYPE_FREE;

    #[ORM\Column(length: 20)]
    private string $status = self::STATUS_ACTIVE;

    #[ORM\Column]
    private \DateTimeImmutable $startedAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $completedAt = null;

    #[ORM\Column(nullable: true)]
    private ?int $durationSeconds = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    public function __construct(UserProfile $profile)
    {
        $this->profile = $profile;
        $this->startedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProfile(): UserProfile
    {
        return $this->profile;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function complete(\DateTimeImmutable $completedAt): self
    {
        $this->status = self::STATUS_COMPLETED;
        $this->completedAt = $completedAt;
        $this->durationSeconds = max(0, $completedAt->getTimestamp() - $this->startedAt->getTimestamp());

        return $this;
    }

    public function cancel(): self
    {
        $this->status = self::STATUS_CANCELLED;

        return $this;
    }

    public function getStartedAt(): \DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function setStartedAt(\DateTimeImmutable $startedAt): self
    {
        $this->startedAt = $startedAt;

        return $this;
    }

    public function getCompletedAt(): ?\DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function getDurationSeconds(): ?int
    {
        return $this->durationSeconds;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): self
    {
        $this->notes = $notes;

        return $this;
    }
}
