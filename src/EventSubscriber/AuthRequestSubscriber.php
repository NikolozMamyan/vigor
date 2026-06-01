<?php

namespace App\EventSubscriber;

use App\Service\Auth\CurrentUserProfileProvider;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class AuthRequestSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly CurrentUserProfileProvider $currentUser,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['protectApplication', 8],
        ];
    }

    public function protectApplication(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $path = $request->getPathInfo();

        if ($this->isPublic($path, $request->getMethod())) {
            return;
        }

        if (!$this->requiresAuthentication($path)) {
            return;
        }

        if ($this->currentUser->getProfile()) {
            return;
        }

        if (str_starts_with($path, '/api/')) {
            $event->setResponse(new JsonResponse(['error' => 'Authentication required.'], JsonResponse::HTTP_UNAUTHORIZED));

            return;
        }

        $event->setResponse(new RedirectResponse($this->urlGenerator->generate('auth_login')));
    }

    private function requiresAuthentication(string $path): bool
    {
        return '/' === $path || str_starts_with($path, '/app') || str_starts_with($path, '/admin') || str_starts_with($path, '/api/');
    }

    private function isPublic(string $path, string $method): bool
    {
        if ('/login' === $path || str_starts_with($path, '/_')) {
            return true;
        }

        if (\in_array($path, ['/api/auth/login', '/api/auth/register', '/api/auth/google-id-token'], true) && 'POST' === $method) {
            return true;
        }

        return '/api/exercises' === $path && 'GET' === $method;
    }
}
