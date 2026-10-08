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
     * Authenticates the client and give a jwt token in the cookie if successfully
     * @param Request $request
     * @param Response $response
     * @param array $args
     * @return Response
     */
    public function authenticate(Request $request, Response $response, array $args): Response
    {
        try {
            $username = $request->getParsedBody()['username'] ?? null;
            $password = $request->getParsedBody()['password'] ?? null;

            if (!is_string($username) || !is_string($password)) {
                return helper::error($response, "Username and password must be strings");
            }

            $username = trim($username);

            if ($username === '' || $password === '') {
                return helper::error($response, "Invalid username or password");
            }


            if ($username !== ($this->config['auth_username'] ?? throw new Exception("Internal Server Error")) ||
                $password !== ($this->config['auth_password'] ?? throw new Exception("Internal Server Error"))) {
                return helper::error($response, "Invalid username or password", 401);
            }

            $expiration = time() + 3600;

            $token = Token::create($username, $this->config['auth_password'], $expiration, $this->issuer);

            setcookie("jwt_token", $token, time() + 3600);

            return helper::success($response, message: "Authenticated successfully");

        } catch (Exception $e) {
            return helper::error($response, $e->getMessage(), 500);
        }
    }

    /**
     * Middleware methode, to check the jwt token im cookie
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