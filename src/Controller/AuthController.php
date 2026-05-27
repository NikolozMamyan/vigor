<?php

namespace App\Controller;

use App\Entity\UserProfile;
use App\Repository\UserProfileRepository;
use App\Service\Auth\AuthSessionManager;
use App\Service\Auth\CurrentUserProfileProvider;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AuthController extends AbstractController
{
    #[Route('/login', name: 'auth_login', methods: ['GET', 'POST'])]
    public function login(
        Request $request,
        UserProfileRepository $profileRepository,
        AuthSessionManager $sessionManager,
        CurrentUserProfileProvider $currentUser,
    ): Response {
        if ('GET' === $request->getMethod() && $currentUser->getProfile()) {
            return $this->redirectToRoute('vigor_app', ['view' => 'home']);
        }

        $error = null;
        $registerError = null;
        $email = mb_strtolower(trim((string) $request->request->get('email', '')));

        if ('POST' === $request->getMethod()) {
            $profile = $profileRepository->findOneBy(['email' => $email]);
            $password = (string) $request->request->get('password', '');

            if ($profile && $profile->getPasswordHash() && password_verify($password, $profile->getPasswordHash())) {
                $created = $sessionManager->createPasswordSession($profile, $request);
                $response = new RedirectResponse($this->generateUrl('vigor_app', ['view' => 'home']));
                $sessionManager->attachLoginCookies($response, $request, $created['plainToken'], $created['deviceId'], $created['session']->getExpiresAt());

                return $response;
            }

            $error = 'Identifiants invalides.';
        }

        return $this->render('auth/login.html.twig', [
            'error' => $error,
            'registerError' => $registerError,
            'email' => $email,
        ]);
    }

    #[Route('/register', name: 'auth_register', methods: ['POST'])]
    public function register(
        Request $request,
        UserProfileRepository $profileRepository,
        EntityManagerInterface $entityManager,
        AuthSessionManager $sessionManager,
    ): Response {
        $displayName = trim((string) $request->request->get('displayName', ''));
        $email = mb_strtolower(trim((string) $request->request->get('email', '')));
        $password = (string) $request->request->get('password', '');
        $error = null;

        if ('' === $displayName || mb_strlen($displayName) > 100) {
            $error = 'Pseudo invalide.';
        } elseif (!filter_var($email, \FILTER_VALIDATE_EMAIL)) {
            $error = 'Adresse email invalide.';
        } elseif (mb_strlen($password) < 8) {
            $error = 'Le mot de passe doit contenir au moins 8 caracteres.';
        } elseif ($profileRepository->findOneBy(['email' => $email])) {
            $error = 'Un compte existe deja avec cet email.';
        }

        if ($error) {
            return $this->render('auth/login.html.twig', [
                'error' => null,
                'registerError' => $error,
                'email' => $email,
                'registerEmail' => $email,
                'displayName' => $displayName,
                'authMode' => 'register',
            ], new Response('', Response::HTTP_UNPROCESSABLE_ENTITY));
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
        $response = new RedirectResponse($this->generateUrl('vigor_app', ['view' => 'home']));
        $sessionManager->attachLoginCookies($response, $request, $created['plainToken'], $created['deviceId'], $created['session']->getExpiresAt());

        return $response;
    }

    #[Route('/logout', name: 'auth_logout', methods: ['POST', 'GET'])]
    public function logout(Request $request, AuthSessionManager $sessionManager): Response
    {
        $sessionManager->revokeFromRequest($request);
        $response = $this->redirectToRoute('auth_login');
        $sessionManager->expireAuthCookie($response, $request);

        return $response;
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
}
