<?php
declare(strict_types=1);

namespace middleware;

use Exception;
use helpers\response as helper;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use ReallySimpleJWT\Token;
use Slim\Psr7\Factory\ResponseFactory;

/**
 * Middleware and Authentication Endpoint
 */
class AuthMiddleware implements MiddlewareInterface
{

    /**
     * issuer
     * @var string
     */
    private string $issuer = 'localhost';

    /**
     * Config file for secrets
     * @param array $config
     */
    public function __construct(private array $config)
    {
    }



    /**
     * Middleware methode, to check the jwt token in cookie
     * @param Request $request
     * @param RequestHandlerInterface $handler
     * @return Response
     */
    public function process(Request $request, RequestHandlerInterface $handler): Response
    {
        $response = (new ResponseFactory())->createResponse();

        try {
            $token = $_COOKIE["jwt_token"] ?? null;

            if (!is_string($token) || $token === '') {
                return helper::error($response, 'Token is missing.', 401);
            }

            try {
                $result = Token::validate($token, $this->config['auth_password']);
            } catch (Exception $e) {
                return helper::error($response, "Broken token", 401);
            }
            if (!$result) {
                return helper::error($response, "Invalid token", 401);
            }

            try {
                $expirationCheck = Token::validateExpiration($token);
            } catch (Exception $e) {
                return helper::error($response, "Broken token", 401);
            }
            if (!$expirationCheck) {
                return helper::error($response, "token expired", 401);
            }
            return $handler->handle($request);

        } catch (Exception $e) {
            return helper::error($response, "Internal Server Error", 500);
        }
    }
}