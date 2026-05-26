<?php

namespace App\Tests\Controller\Api;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ExerciseSearchControllerTest extends WebTestCase
{
    public function testItReturnsExercises(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/exercises?q=squat');

        self::assertResponseIsSuccessful();

        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertArrayHasKey('exercises', $payload);
        self::assertNotEmpty($payload['exercises']);
        self::assertSame('Squat', $payload['exercises'][0]['name']);
    }

    public function testItReturnsCustomExercises(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/exercises?q=elastique');

        self::assertResponseIsSuccessful();

        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertNotEmpty($payload['exercises']);
        self::assertTrue($payload['exercises'][0]['isCustom']);
    }

    public function testItRejectsTooLongQuery(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/exercises?q='.str_repeat('a', 81));

        self::assertResponseStatusCodeSame(400);
    }
}
