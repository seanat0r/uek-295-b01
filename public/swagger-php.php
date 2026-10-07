<?php
declare(strict_types=1);


use OpenApi\Builder;

require __DIR__ . "/../vendor/autoload.php";
// Require all scripts within /api directory.
$scripts = glob(__DIR__ . "/api/*.php");
foreach ($scripts as $script) {
    require $script;
}
// Build and return OpenAPI documentation as YAML.
$result = (new Builder())
    ->addSource(__DIR__)
    ->build();
header('Content-Type: application/x-yaml');
echo $result->toYaml();
