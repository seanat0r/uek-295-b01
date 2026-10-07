<?php
declare(strict_types=1);

namespace helpers;

use Psr\Http\Message\ResponseInterface as httpResponse;

class response
{
    /**
     * Return a successfully Http Response
     * @param httpResponse $httpResponse response object (Psr\Http\Message\)
     * @param array|null $data Any Data from db as an assoc array.
     * @param string|null $message Any extra information
     * @param int $code http code 2xx
     * @return httpResponse response object (Psr\Http\Message\)
     */
    public static function success(httpResponse $httpResponse, array|null $data = null, string|null $message = null, int $code = 200): httpResponse
    {
        if ($message !== null && $data === null) {
            $httpResponse->getBody()->write(json_encode($message));
        }

        if ($data !== null && $message === null) {
            $httpResponse->getBody()->write(json_encode($data));
        }

        if ($data !== null && $message !== null) {
            $httpResponse->getBody()->write(json_encode(["data" => $data, "message" => $message]));
        }

        return $httpResponse
            ->withStatus($code)
            ->withHeader('Content-Type', 'application/json');
    }

    /**
     * Return an error Http Response
     * @param httpResponse $httpResponse response object (Psr\Http\Message\)
     * @param string $data message for the error
     * @param int $code 4xx or 5xx
     * @return httpResponse response object (Psr\Http\Message\)
     */
    public static function error(httpResponse $httpResponse, string $data = '', int $code = 400): httpResponse
    {
        $httpResponse->getBody()->write(json_encode(["error" => $data, "code" => $code]));
        return $httpResponse
            ->withStatus($code)
            ->withHeader('Content-Type', 'application/json');
    }
}