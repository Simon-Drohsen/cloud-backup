<?php

declare(strict_types=1);

use Pimcore\Bootstrap;

require dirname(__DIR__) . '/vendor/autoload.php';

// Pimcore kernel expects project root constants before kernel boot in WebTestCase.
Bootstrap::setProjectRoot();
Bootstrap::bootstrap();
