<?php

use Slim\Factory\AppFactory;
use Slim\Psr7\Request;
use Slim\Psr7\Response;

$app = AppFactory::create();

$app->get('/products', function (Request $request, Response $response, $args) {
    $response->getBody()->write("Hello world!");
    return $response;
});