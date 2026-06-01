<?php

namespace App\Service\Auth;

use App\Entity\AuthSession;
use App\Entity\UserProfile;
use App\Repository\AuthSessionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class AuthSessionManager
{
    public const AUTH_COOKIE = 'AUTH_TOKEN';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly AuthSessionRepository $sessionRepository,
        private readonly DeviceIdentifier $deviceIdentifier,
    ) {
    }

    /**
     * @return array{session: AuthSession, plainToken: string, deviceId: string}
     */
    public function createPasswordSession(UserProfile $profile, Request $request): array
    {
        return $this->createSession($profile, $request, AuthSession::CONNECTION_PASSWORD);
    }

    /**
     * @return array{session: AuthSession, plainToken: string, deviceId: string}
     */
    public function createGoogleSession(UserProfile $profile, Request $request): array
    {
        return $this->createSession($profile, $request, AuthSession::CONNECTION_GOOGLE);
    }

    /**
     * @return array{session: AuthSession, plainToken: string, deviceId: string}
     */
    private function createSession(UserProfile $profile, Request $request, string $connectionType): array
    {
        $plainToken = bin2hex(random_bytes(32));
        $deviceId = $request->cookies->get(DeviceIdentifier::COOKIE_NAME) ?: $this->deviceIdentifier->generate();
        $expiresAt = new \DateTimeImmutable('+90 days');
        $session = (new AuthSession($profile, $this->hashToken($plainToken), $deviceId, $connectionType, $expiresAt))
            ->setIpAddress($request->getClientIp())
            ->setUserAgent(substr((string) $request->headers->get('User-Agent'), 0, 1000));

        $this->entityManager->persist($session);
        $this->entityManager->flush();

        return [
            'session' => $session,
            'plainToken' => $plainToken,
            'deviceId' => $deviceId,
        ];
    }

    public function attachLoginCookies(Response $response, Request $request, string $plainToken, string $deviceId, \DateTimeInterface $expiresAt): void
    {
        $response->headers->setCookie($this->buildAuthCookie($request, $plainToken, $expiresAt));
        $response->headers->setCookie($this->buildDeviceCookie($request, $deviceId));
    }

    public function revokeFromRequest(Request $request): void
    {
        $plainToken = $request->cookies->get(self::AUTH_COOKIE);

        if (!$plainToken) {
            return;
        }

        $session = $this->sessionRepository->findActiveByTokenHash($this->hashToken($plainToken), new \DateTimeImmutable());

        if (!$session) {
            return;
        }

        $session->revoke(new \DateTimeImmutable());
        $this->entityManager->flush();
    }

    public function expireAuthCookie(Response $response, Request $request): void
    {
        $response->headers->clearCookie(self::AUTH_COOKIE, '/', null, $request->isSecure(), true, 'lax');
    }

    public function hashToken(string $plainToken): string
    {
        return hash('sha256', $plainToken);
    }

    private function buildAuthCookie(Request $request, string $plainToken, \DateTimeInterface $expiresAt): Cookie
    {
        return Cookie::create(self::AUTH_COOKIE)
            ->withValue($plainToken)
            ->withHttpOnly(true)
            ->withSecure($request->isSecure())
            ->withSameSite('lax')
            ->withPath('/')
            ->withExpires($expiresAt);
    }

    private function buildDeviceCookie(Request $request, string $deviceId): Cookie
    {
        return Cookie::create(DeviceIdentifier::COOKIE_NAME)
            ->withValue($deviceId)
            ->withHttpOnly(true)
            ->withSecure($request->isSecure())
            ->withSameSite('lax')
            ->withPath('/')
            ->withExpires(new \DateTimeImmutable('+5 years'));
    }
}
