<?php

namespace App\Entity;

use App\Repository\ExerciseRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ExerciseRepository::class)]
#[ORM\Index(columns: ['source'], name: 'idx_exercise_source')]
#[ORM\Index(columns: ['muscle_group'], name: 'idx_exercise_muscle_group')]
class Exercise
{
    public const SOURCE_VIGOR = 'vigor';
    public const SOURCE_CUSTOM = 'custom';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 120)]
    private string $name;

    #[ORM\Column(length: 140, unique: true)]
    private string $slug;

    #[ORM\Column(length: 60)]
    private string $muscleGroup;

    #[ORM\Column(length: 60)]
    private string $equipment;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $imageUrl = null;

    #[ORM\Column(length: 20)]
    private string $source = self::SOURCE_VIGOR;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?UserProfile $createdByProfile = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): self
    {
        $this->slug = $slug;

        return $this;
    }

    public function getMuscleGroup(): string
    {
        return $this->muscleGroup;
    }

    public function setMuscleGroup(string $muscleGroup): self
    {
        $this->muscleGroup = $muscleGroup;

        return $this;
    }

    public function getEquipment(): string
    {
        return $this->equipment;
    }

    public function setEquipment(string $equipment): self
    {
        $this->equipment = $equipment;

        return $this;
    }

    public function getImageUrl(): ?string
    {
        return $this->imageUrl;
    }

    public function setImageUrl(?string $imageUrl): self
    {
        $this->imageUrl = $imageUrl;

        return $this;
    }

    public function getSource(): string
    {
        return $this->source;
    }

    public function setSource(string $source): self
    {
        $this->source = $source;

        return $this;
    }

    public function getCreatedByProfile(): ?UserProfile
    {
        return $this->createdByProfile;
    }

    public function setCreatedByProfile(?UserProfile $createdByProfile): self
    {
        $this->createdByProfile = $createdByProfile;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
