<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class VigorAppControllerTest extends WebTestCase
{
    public function testAppRouteRendersShell(): void
    {
        $client = static::createClient();
        $client->request('GET', '/app');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Pret a briser');
        self::assertSelectorExists('[data-controller~="vigor-navigation"]');
        self::assertSelectorExists('[data-vigor-navigation-target~="skeleton"]');
        self::assertSelectorExists('nav.app-bottom-nav.fixed');
        self::assertSelectorExists('header [data-vigor-navigation-view-param="profile"]');
    }

    public function testWorkoutRouteActivatesWorkoutView(): void
    {
        $client = static::createClient();
        $client->request('GET', '/app/workout');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('#view-workout.active');
        self::assertSelectorTextContains('#view-workout h2', 'Seance libre');
    }

    public function testStatsRouteActivatesStatsView(): void
    {
        $client = static::createClient();
        $client->request('GET', '/app/stats');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('#view-stats.active');
        self::assertSelectorTextContains('#view-stats h2', 'Analyses elite');
        self::assertSelectorExists('[data-vigor-navigation-view-param="stats"]');
    }

    public function testProfileRouteActivatesProfileView(): void
    {
        $client = static::createClient();
        $client->request('GET', '/app/profile');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('#view-profile.active');
    }
}
