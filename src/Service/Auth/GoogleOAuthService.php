<?php

namespace App\Service\Auth;

use App\Entity\UserProfile;
use App\Repository\UserProfileRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class GoogleOAuthService
{
    private const AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';
    private const USERINFO_URL = 'https://openidconnect.googleapis.com/v1/userinfo';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly UserProfileRepository $profileRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly string $clientId = '',
        private readonly string $clientSecret = '',
    ) {
    }

    public function isConfigured(): bool
    {
        return '' !== trim($this->clientId) && '' !== trim($this->clientSecret);
    }

    public function authorizationUrl(string $redirectUri, string $state): string
    {
        if (!$this->isConfigured()) {
            throw new \RuntimeException('Google OAuth is not configured.');
        }

        return self::AUTH_URL.'?'.http_build_query([
            'client_id' => $this->clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'state' => $state,
        ], '', '&', \PHP_QUERY_RFC3986);
    }

    public function authenticate(string $code, string $redirectUri): UserProfile
    {
        if (!$this->isConfigured()) {
            throw new \RuntimeException('Google OAuth is not configured.');
        }

        $token = $this->token($code, $redirectUri);
        $profile = $this->googleProfile((string) ($token['access_token'] ?? ''));
        $email = mb_strtolower(trim((string) ($profile['email'] ?? '')));

        if (!filter_var($email, \FILTER_VALIDATE_EMAIL) || true !== ($profile['email_verified'] ?? false)) {
            throw new \RuntimeException('Google account email is not verified.');
        }

        $userProfile = $this->profileRepository->findOneBy(['email' => $email]);

        if ($userProfile) {
            $this->updateAvatar($userProfile, $profile);
            $this->entityManager->flush();

            return $userProfile;
        }

        $displayName = trim((string) ($profile['name'] ?? ''));
        $displayName = '' !== $displayName ? $displayName : (string) strstr($email, '@', true);

        $userProfile = (new UserProfile())
            ->setDisplayName($displayName)
            ->setEmail($email)
            ->setUsername($this->uniqueUsername($displayName))
            ->setPasswordHash(null)
            ->setPreferredWeightUnit('kg')
            ->setWeeklyWorkoutGoal(4)
            ->setWeeklyVolumeGoal(14000);

        $this->updateAvatar($userProfile, $profile);
        $this->entityManager->persist($userProfile);
        $this->entityManager->flush();

        return $userProfile;
    }

    public function authenticateIdToken(string $idToken): UserProfile
    {
        if ('' === trim($this->clientId)) {
            throw new \RuntimeException('Google OAuth is not configured.');
        }

        $profile = $this->googleProfileFromIdToken($idToken);
        $audience = (string) ($profile['aud'] ?? '');

        if ('' !== $audience && !hash_equals($this->clientId, $audience)) {
            throw new \RuntimeException('Google token audience is invalid.');
        }

        return $this->profileFromGoogleProfile($profile);
    }

    /**
     * @return array<string, mixed>
     */
    private function token(string $code, string $redirectUri): array
    {
        $response = $this->httpClient->request('POST', self::TOKEN_URL, [
            'body' => [
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'code' => $code,
                'grant_type' => 'authorization_code',
                'redirect_uri' => $redirectUri,
            ],
        ]);

        $data = $response->toArray(false);

        if (200 !== $response->getStatusCode() || !isset($data['access_token'])) {
            throw new \RuntimeException('Unable to exchange Google authorization code.');
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function googleProfile(string $accessToken): array
    {
        if ('' === $accessToken) {
            throw new \RuntimeException('Google access token is missing.');
        }

        $response = $this->httpClient->request('GET', self::USERINFO_URL, [
            'headers' => [
                'Authorization' => 'Bearer '.$accessToken,
            ],
        ]);

        $data = $response->toArray(false);

        if (200 !== $response->getStatusCode()) {
            throw new \RuntimeException('Unable to fetch Google profile.');
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function googleProfileFromIdToken(string $idToken): array
    {
        if ('' === trim($idToken)) {
            throw new \RuntimeException('Google ID token is missing.');
        }

        $response = $this->httpClient->request('GET', 'https://oauth2.googleapis.com/tokeninfo', [
            'query' => [
                'id_token' => $idToken,
            ],
        ]);

        $data = $response->toArray(false);

        if (200 !== $response->getStatusCode()) {
            throw new \RuntimeException('Unable to verify Google ID token.');
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $profile
     */
    private function profileFromGoogleProfile(array $profile): UserProfile
    {
        $email = mb_strtolower(trim((string) ($profile['email'] ?? '')));
        $emailVerified = $profile['email_verified'] ?? false;
        $emailVerified = true === $emailVerified || 'true' === $emailVerified || '1' === (string) $emailVerified;

        if (!filter_var($email, \FILTER_VALIDATE_EMAIL) || !$emailVerified) {
            throw new \RuntimeException('Google account email is not verified.');
        }

        $userProfile = $this->profileRepository->findOneBy(['email' => $email]);

        if ($userProfile) {
            $this->updateAvatar($userProfile, $profile);
            $this->entityManager->flush();

            return $userProfile;
        }

        $displayName = trim((string) ($profile['name'] ?? ''));
        $displayName = '' !== $displayName ? $displayName : (string) strstr($email, '@', true);

        $userProfile = (new UserProfile())
            ->setDisplayName($displayName)
            ->setEmail($email)
            ->setUsername($this->uniqueUsername($displayName))
            ->setPasswordHash(null)
            ->setPreferredWeightUnit('kg')
            ->setWeeklyWorkoutGoal(4)
            ->setWeeklyVolumeGoal(14000);

        $this->updateAvatar($userProfile, $profile);
        $this->entityManager->persist($userProfile);
        $this->entityManager->flush();

        return $userProfile;
    }

    /**
     * @param array<string, mixed> $googleProfile
     */
    private function updateAvatar(UserProfile $profile, array $googleProfile): void
    {
        $picture = trim((string) ($googleProfile['picture'] ?? ''));

        if ('' !== $picture) {
            $profile->setAvatarUrl($picture);
        }
    }

    private function uniqueUsername(string $displayName): string
    {
        $base = mb_strtolower((string) preg_replace('/[^a-zA-Z0-9]+/', '-', trim($displayName)));
        $base = trim($base, '-') ?: 'athlete';
        $username = $base;
        $suffix = 2;

        while ($this->profileRepository->findOneBy(['username' => $username])) {
            $username = $base.'-'.$suffix++;
        }

        return $username;
    }
}
