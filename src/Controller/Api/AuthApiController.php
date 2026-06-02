<?php

namespace App\Controller\Api;

use App\Entity\UserProfile;
use App\Repository\UserProfileRepository;
use App\Service\Auth\AuthSessionManager;
use App\Service\Auth\CurrentUserProfileProvider;
use App\Service\Auth\GoogleOAuthService;
use App\Service\Auth\MobileAuthTicketStore;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class AuthApiController extends AbstractController
{
    #[Route('/api/auth/login', name: 'api_auth_login', methods: ['POST'])]
    public function login(Request $request, UserProfileRepository $profileRepository, AuthSessionManager $sessionManager): JsonResponse
    {
        try {
            $payload = json_decode($request->getContent() ?: '{}', true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return $this->json(['error' => 'Invalid JSON payload.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $email = mb_strtolower(trim((string) ($payload['email'] ?? '')));
        $password = (string) ($payload['password'] ?? '');
        $profile = $profileRepository->findOneBy(['email' => $email]);

        if (!$profile || !$profile->getPasswordHash() || !password_verify($password, $profile->getPasswordHash())) {
            return $this->json(['error' => 'Identifiants invalides.'], JsonResponse::HTTP_UNAUTHORIZED);
        }

        $created = $sessionManager->createPasswordSession($profile, $request);
        $response = $this->json(['profile' => $this->normalizeProfile($profile)]);
        $sessionManager->attachLoginCookies($response, $request, $created['plainToken'], $created['deviceId'], $created['session']->getExpiresAt());

        return $response;
    }

    #[Route('/api/auth/register', name: 'api_auth_register', methods: ['POST'])]
    public function register(
        Request $request,
        UserProfileRepository $profileRepository,
        EntityManagerInterface $entityManager,
        AuthSessionManager $sessionManager,
    ): JsonResponse {
        try {
            $payload = json_decode($request->getContent() ?: '{}', true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return $this->json(['error' => 'Invalid JSON payload.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $displayName = trim((string) ($payload['displayName'] ?? ''));
        $email = mb_strtolower(trim((string) ($payload['email'] ?? '')));
        $password = (string) ($payload['password'] ?? '');

        if ('' === $displayName || mb_strlen($displayName) > 100) {
            return $this->json(['error' => 'Pseudo invalide.'], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (!filter_var($email, \FILTER_VALIDATE_EMAIL)) {
            return $this->json(['error' => 'Adresse email invalide.'], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (mb_strlen($password) < 8) {
            return $this->json(['error' => 'Le mot de passe doit contenir au moins 8 caracteres.'], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($profileRepository->findOneBy(['email' => $email])) {
            return $this->json(['error' => 'Un compte existe deja avec cet email.'], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $profile = (new UserProfile())
            ->setDisplayName($displayName)
            ->setEmail($email)
            ->setUsername($this->uniqueUsername($displayName, $profileRepository))
            ->setPasswordHash(password_hash($password, \PASSWORD_DEFAULT))
            ->setPreferredWeightUnit('kg')
            ->setWeeklyWorkoutGoal(4)
            ->setWeeklyVolumeGoal(14000);

        $entityManager->persist($profile);
        $entityManager->flush();

        $created = $sessionManager->createPasswordSession($profile, $request);
        $response = $this->json(['profile' => $this->normalizeProfile($profile)], JsonResponse::HTTP_CREATED);
        $sessionManager->attachLoginCookies($response, $request, $created['plainToken'], $created['deviceId'], $created['session']->getExpiresAt());

        return $response;
    }

    #[Route('/api/auth/google-id-token', name: 'api_auth_google_id_token', methods: ['POST'])]
    public function googleIdToken(Request $request, GoogleOAuthService $googleOAuth, AuthSessionManager $sessionManager): JsonResponse
    {
        try {
            $payload = json_decode($request->getContent() ?: '{}', true, 512, \JSON_THROW_ON_ERROR);
            $profile = $googleOAuth->authenticateIdToken((string) ($payload['idToken'] ?? ''));
            $created = $sessionManager->createGoogleSession($profile, $request);
            $response = $this->json(['profile' => $this->normalizeProfile($profile)]);
            $sessionManager->attachLoginCookies($response, $request, $created['plainToken'], $created['deviceId'], $created['session']->getExpiresAt());

            return $response;
        } catch (\JsonException) {
            return $this->json(['error' => 'Invalid JSON payload.'], JsonResponse::HTTP_BAD_REQUEST);
        } catch (\Throwable $exception) {
            return $this->json(['error' => $exception->getMessage()], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    #[Route('/api/auth/mobile-ticket', name: 'api_auth_mobile_ticket', methods: ['POST'])]
    public function mobileTicket(Request $request, MobileAuthTicketStore $mobileAuthTickets, AuthSessionManager $sessionManager): JsonResponse
    {
        try {
            $payload = json_decode($request->getContent() ?: '{}', true, 512, \JSON_THROW_ON_ERROR);
            $ticket = (string) ($payload['ticket'] ?? '');
            $mobileSession = $mobileAuthTickets->consume($ticket);

            if (null === $mobileSession) {
                return $this->json(['error' => 'Session Google mobile expiree.'], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
            }

            $response = $this->json(['ok' => true]);
            $sessionManager->attachLoginCookies(
                $response,
                $request,
                $mobileSession['plainToken'],
                $mobileSession['deviceId'],
                $mobileSession['expiresAt'],
            );

            return $response;
        } catch (\JsonException) {
            return $this->json(['error' => 'Invalid JSON payload.'], JsonResponse::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/api/auth/logout', name: 'api_auth_logout', methods: ['POST'])]
    public function logout(Request $request, AuthSessionManager $sessionManager): JsonResponse
    {
        $sessionManager->revokeFromRequest($request);
        $response = $this->json(['ok' => true]);
        $sessionManager->expireAuthCookie($response, $request);

        return $response;
    }

    #[Route('/api/auth/me', name: 'api_auth_me', methods: ['GET'])]
    public function me(CurrentUserProfileProvider $currentUser): JsonResponse
    {
        $profile = $currentUser->getProfile();

        if (!$profile) {
            return $this->json(['error' => 'Authentication required.'], JsonResponse::HTTP_UNAUTHORIZED);
        }

        return $this->json(['profile' => $this->normalizeProfile($profile)]);
    }

    private function uniqueUsername(string $displayName, UserProfileRepository $profileRepository): string
    {
        $base = mb_strtolower((string) preg_replace('/[^a-zA-Z0-9]+/', '-', trim($displayName)));
        $base = trim($base, '-') ?: 'athlete';
        $username = $base;
        $suffix = 2;

        while ($profileRepository->findOneBy(['username' => $username])) {
            $username = $base.'-'.$suffix++;
        }

        return $username;
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeProfile(UserProfile $profile): array
    {
        return [
            'id' => $profile->getId(),
            'displayName' => $profile->getDisplayName(),
            'username' => $profile->getUsername(),
            'email' => $profile->getEmail(),
            'avatarUrl' => $profile->getAvatarUrl(),
        ];
    }
}
