<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class SensitiveResponseCacheSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => 'disableSensitiveRouteCache',
        ];
    }

    public function disableSensitiveRouteCache(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        if (!$this->isSensitivePath($event->getRequest()->getPathInfo())) {
            return;
        }

        $response = $event->getResponse();
        $response->setPrivate();
        $response->headers->addCacheControlDirective('no-store');
        $response->headers->addCacheControlDirective('no-cache');
        $response->headers->addCacheControlDirective('must-revalidate');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');
    }

    private function isSensitivePath(string $path): bool
    {
        return '/' === $path
            || '/login' === $path
            || '/register' === $path
            || '/logout' === $path
            || str_starts_with($path, '/app')
            || str_starts_with($path, '/admin')
            || str_starts_with($path, '/api/');
    }
}
