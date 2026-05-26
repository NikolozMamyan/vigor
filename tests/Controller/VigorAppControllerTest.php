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
    }

    public function testWorkoutRouteActivatesWorkoutView(): void
    {
        $client = static::createClient();
        $client->request('GET', '/app/workout');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('#view-workout.active');
        self::assertSelectorTextContains('#view-workout h2', 'Developpe');
    }
}
