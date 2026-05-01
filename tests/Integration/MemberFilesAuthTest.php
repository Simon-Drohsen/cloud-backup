<?php

declare(strict_types=1);

namespace Integration;

use Pimcore\Test\WebTestCase;

final class MemberFilesAuthTest extends WebTestCase
{
    public function testMyFilesRequiresAuthentication(): void
    {
        $client = static::createClient();
        $client->request('GET', '/my-files');

        $response = $client->getResponse();

        self::assertTrue($response->isRedirection());
        self::assertStringContainsString(
            '/auth/login',
            (string) $response->headers->get('location')
        );
    }
}





