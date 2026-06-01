<?php

namespace App\Tests\Controller\Api;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class MobileAppControllerTest extends WebTestCase
{
    public function testBootstrapRequiresAuthentication(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/mobile/bootstrap');

        self::assertResponseStatusCodeSame(401);

        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame('Authentication required.', $payload['error']);
    }
}
