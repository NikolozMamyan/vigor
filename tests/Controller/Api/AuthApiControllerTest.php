<?php

namespace App\Tests\Controller\Api;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class AuthApiControllerTest extends WebTestCase
{
    public function testLoginRejectsInvalidJson(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/auth/login', server: ['CONTENT_TYPE' => 'application/json'], content: '{');

        self::assertResponseStatusCodeSame(400);

        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame('Invalid JSON payload.', $payload['error']);
    }

    public function testRegisterValidatesPayload(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/auth/register', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'displayName' => '',
            'email' => 'invalid',
            'password' => 'short',
        ], \JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(422);

        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame('Pseudo invalide.', $payload['error']);
    }

    public function testGoogleLoginRejectsInvalidJson(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/auth/google-id-token', server: ['CONTENT_TYPE' => 'application/json'], content: '{');

        self::assertResponseStatusCodeSame(400);

        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame('Invalid JSON payload.', $payload['error']);
    }

    public function testMeRequiresAuthentication(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/auth/me');

        self::assertResponseStatusCodeSame(401);
    }
}
