<?php

namespace App\Service\Auth;

use App\Entity\UserProfile;
use App\Repository\AuthSessionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

final class CurrentUserProfileProvider
{
    private ?UserProfile $profile = null;
    private bool $resolved = false;

    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly AuthSessionRepository $sessionRepository,
        private readonly AuthSessionManager $sessionManager,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function getProfile(): ?UserProfile
    {
        if ($this->resolved) {
            return $this->profile;
        }

        $this->resolved = true;
        $request = $this->requestStack->getCurrentRequest();
        $plainToken = $request?->cookies->get(AuthSessionManager::AUTH_COOKIE);

        if (!$plainToken) {
            return null;
        }

        $now = new \DateTimeImmutable();
        $session = $this->sessionRepository->findActiveByTokenHash($this->sessionManager->hashToken($plainToken), $now);

        if (!$session) {
            return null;
        }

        $session->markUsed($now);
        $this->entityManager->flush();
        $this->profile = $session->getProfile();

        return $this->profile;
    }

    public function requireProfile(): UserProfile
    {
        return $this->getProfile() ?? throw new \RuntimeException('Authentication required.');
    }
}
