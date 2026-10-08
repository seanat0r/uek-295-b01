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

class AuthMiddleware implements MiddlewareInterface
{

    private string $issuer = 'localhost';

    public function __construct(private array $config)
    {
    }

    public function authenticate(Request $request, Response $response, array $args): Response
    {
        try {
            $username = $request->getParsedBody()['username'] ?? null;
            $password = $request->getParsedBody()['password'] ?? null;

            $username = trim($username);
            $password = trim($password);

            if (empty($username) || empty($password)) {
                return helper::error($response, "Invalid username or password");
            }


            if ($username !== ($this->config['auth_username'] ?? throw new Exception("Internal Server Error")) ||
                $password !== ($this->config['auth_password'] ?? throw new Exception("Internal Server Error"))) {
                return helper::error($response, "Invalid username or password", 401);
            }

            $expiration = time() + 3600;

            $token = Token::create($username, $this->config['auth_password'], $expiration, $this->issuer);

            setcookie("jwt_token", $token, time() + $expiration);

            return helper::success($response, message: "Authenticated successfully");

        } catch (Exception $e) {
            return helper::error($response, $e->getMessage(), 500);
        }
    }

    public function process(Request $request, RequestHandlerInterface $handler): Response
    {
        $response = (new ResponseFactory())->createResponse();

        try {
            $token = $_COOKIE["jwt_token"] ?? null;

            if (!is_string($token) || $token === '') {
                return helper::error($response, 'Token is missing.', 401);
            }

            $result = Token::validate($token, $this->config['auth_password']);

            if (!$result) {
                return helper::error($response, "Invalid token", 401);
            }
            return $handler->handle($request);

        } catch (Exception $e) {
            return helper::error($response, "Internal Server Error", 500);
        }
    }
}