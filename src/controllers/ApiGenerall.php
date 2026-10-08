<?php
declare(strict_types=1);

use helpers\response as helper;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Misc API Controller
 */
class ApiGeneral
{
    /**
     * 405 endpoint
     * @param Request $request
     * @param Response $response
     * @param array $args
     * @return Response
     */
    static public function index(Request $request, Response $response, array $args): Response
    {
        return helper::error($response, "Endpoint does not exist", 404);
    }
}