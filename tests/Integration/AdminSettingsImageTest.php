<?php

declare(strict_types=1);

namespace Integration;

use Pimcore\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

final class AdminSettingsImageTest extends WebTestCase
{
    public function testDisplayCustomImageReturnsImageResponse(): void
    {
        $client = static::createClient();
        $client->request('GET', '/admin/settings/display-custom-image');

        $response = $client->getResponse();

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertStringContainsString(
            'inline',
            (string) $response->headers->get('content-disposition')
        );
        self::assertStringStartsWith(
            'image/',
            (string) $response->headers->get('content-type')
        );
    }
}







