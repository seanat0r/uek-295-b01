<?php
declare(strict_types=1);

use OpenApi\Generator;

require_once __DIR__ . '/../vendor/autoload.php';

$sourceDirectory = __DIR__ . '/../src';

foreach (glob($sourceDirectory . '/*/*.php') as $script) {
    require_once $script;
}

$result = (new Generator())->generate([$sourceDirectory]);

header('Content-Type: application/x-yaml');
echo $result->toYaml();