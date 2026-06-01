<?php

namespace App\Entity;

use App\Repository\AuthSessionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AuthSessionRepository::class)]
#[ORM\Index(columns: ['token_hash'], name: 'idx_auth_session_token_hash')]
#[ORM\Index(columns: ['device_id'], name: 'idx_auth_session_device_id')]
#[ORM\Index(columns: ['expires_at'], name: 'idx_auth_session_expires_at')]
class AuthSession
{
    public const CONNECTION_PASSWORD = 'password';
    public const CONNECTION_GOOGLE = 'google';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private UserProfile $profile;

    #[ORM\Column(length: 64, unique: true)]
    private string $tokenHash;

    #[ORM\Column(length: 64)]
    private string $deviceId;

    #[ORM\Column(length: 30)]
    private string $connectionType;

    #[ORM\Column(length: 45, nullable: true)]
    private ?string $ipAddress = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $userAgent = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastUsedAt = null;

    #[ORM\Column]
    private \DateTimeImmutable $expiresAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $revokedAt = null;

    public function __construct(UserProfile $profile, string $tokenHash, string $deviceId, string $connectionType, \DateTimeImmutable $expiresAt)
    {
        $this->profile = $profile;
        $this->tokenHash = $tokenHash;
        $this->deviceId = $deviceId;
        $this->connectionType = $connectionType;
        $this->expiresAt = $expiresAt;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProfile(): UserProfile
    {
        return $this->profile;
    }

    public function getTokenHash(): string
    {
        return $this->tokenHash;
    }

    public function getDeviceId(): string
    {
        return $this->deviceId;
    }

    public function getConnectionType(): string
    {
        return $this->connectionType;
    }

    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function setIpAddress(?string $ipAddress): self
    {
        $this->ipAddress = $ipAddress;

        return $this;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function setUserAgent(?string $userAgent): self
    {
        $this->userAgent = $userAgent;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getLastUsedAt(): ?\DateTimeImmutable
    {
        return $this->lastUsedAt;
    }

    public function markUsed(\DateTimeImmutable $usedAt): self
    {
        $this->lastUsedAt = $usedAt;

        return $this;
    }

    public function getExpiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function isValid(\DateTimeImmutable $now): bool
    {
        return null === $this->revokedAt && $this->expiresAt > $now;
    }

    public function revoke(\DateTimeImmutable $revokedAt): self
    {
        $this->revokedAt = $revokedAt;

        return $this;
    }
}
