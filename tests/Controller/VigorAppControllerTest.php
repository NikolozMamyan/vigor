<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class VigorAppControllerTest extends WebTestCase
{
    public function testAppRouteRedirectsAnonymousUserToLogin(): void
    {
        $client = static::createClient();
        $client->request('GET', '/app');

        self::assertResponseRedirects('/login');
    }

    public function testLoginRouteRendersForm(): void
    {
        $client = static::createClient();
        $client->request('GET', '/login');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Bon retour.');
        self::assertSelectorExists('input[name="email"]');
        self::assertSelectorExists('input[name="password"]');
        self::assertSelectorExists('form[action="/register"]');
        self::assertSelectorExists('meta[name="turbo-cache-control"][content="no-cache"]');
        self::assertNoStoreResponse($client->getResponse());
    }

    public function testWorkoutRouteRedirectsAnonymousUserToLogin(): void
    {
        $client = static::createClient();
        $client->request('GET', '/app/workout');

        self::assertResponseRedirects('/login');
    }

    public function testStatsRouteRedirectsAnonymousUserToLogin(): void
    {
        $client = static::createClient();
        $client->request('GET', '/app/stats');

        self::assertResponseRedirects('/login');
    }

    public function testRecordsRouteRedirectsAnonymousUserToLogin(): void
    {
        $client = static::createClient();
        $client->request('GET', '/app/records');

        self::assertResponseRedirects('/login');
    }

    public function testProfileRouteRedirectsAnonymousUserToLogin(): void
    {
        $client = static::createClient();
        $client->request('GET', '/app/profile');

        self::assertResponseRedirects('/login');
        self::assertNoStoreResponse($client->getResponse());
    }

    public function testAdminExerciseRouteRedirectsAnonymousUserToLogin(): void
    {
        $client = static::createClient();
        $client->request('GET', '/admin/exercises');

        self::assertResponseRedirects('/login');
    }

    private static function assertNoStoreResponse(\Symfony\Component\HttpFoundation\Response $response): void
    {
        self::assertTrue($response->headers->hasCacheControlDirective('private'));
        self::assertTrue($response->headers->hasCacheControlDirective('no-store'));
        self::assertTrue($response->headers->hasCacheControlDirective('no-cache'));
        self::assertTrue($response->headers->hasCacheControlDirective('must-revalidate'));
        self::assertSame('no-cache', $response->headers->get('Pragma'));
        self::assertSame('0', $response->headers->get('Expires'));
    }
}
