<?php

namespace App\Entity;

use App\Repository\UserProfileRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserProfileRepository::class)]
class UserProfile
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private string $displayName = 'Alex';

    #[ORM\Column(length: 60, unique: true)]
    private string $username = 'alexvigor';

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $avatarUrl = null;

    #[ORM\Column]
    private \DateTimeImmutable $joinedAt;

    #[ORM\Column(length: 40)]
    private string $recordMetricPreference = 'estimated_1rm';

    #[ORM\Column]
    private int $weeklyWorkoutGoal = 4;

    #[ORM\Column]
    private int $weeklyVolumeGoal = 14000;

    #[ORM\Column(length: 10)]
    private string $preferredWeightUnit = 'kg';

    public function __construct()
    {
        $this->joinedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDisplayName(): string
    {
        return $this->displayName;
    }

    public function setDisplayName(string $displayName): self
    {
        $this->displayName = $displayName;

        return $this;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function setUsername(string $username): self
    {
        $this->username = $username;

        return $this;
    }

    public function getAvatarUrl(): ?string
    {
        return $this->avatarUrl;
    }

    public function setAvatarUrl(?string $avatarUrl): self
    {
        $this->avatarUrl = $avatarUrl;

        return $this;
    }

    public function getJoinedAt(): \DateTimeImmutable
    {
        return $this->joinedAt;
    }

    public function setJoinedAt(\DateTimeImmutable $joinedAt): self
    {
        $this->joinedAt = $joinedAt;

        return $this;
    }

    public function getRecordMetricPreference(): string
    {
        return $this->recordMetricPreference;
    }

    public function setRecordMetricPreference(string $recordMetricPreference): self
    {
        $this->recordMetricPreference = $recordMetricPreference;

        return $this;
    }

    public function getWeeklyWorkoutGoal(): int
    {
        return $this->weeklyWorkoutGoal;
    }

    public function setWeeklyWorkoutGoal(int $weeklyWorkoutGoal): self
    {
        $this->weeklyWorkoutGoal = $weeklyWorkoutGoal;

        return $this;
    }

    public function getWeeklyVolumeGoal(): int
    {
        return $this->weeklyVolumeGoal;
    }

    public function setWeeklyVolumeGoal(int $weeklyVolumeGoal): self
    {
        $this->weeklyVolumeGoal = $weeklyVolumeGoal;

        return $this;
    }

    public function getPreferredWeightUnit(): string
    {
        return $this->preferredWeightUnit;
    }

    public function setPreferredWeightUnit(string $preferredWeightUnit): self
    {
        $this->preferredWeightUnit = $preferredWeightUnit;

        return $this;
    }
}
