<?php
declare(strict_types=1);

namespace middleware;

use Exception;
use helpers\response as helper;
use OpenApi\Attributes as OAT;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use ReallySimpleJWT\Token;
use Slim\Psr7\Factory\ResponseFactory;

/**
 * Middleware and Authentication Endpoint
 */
#[OAT\Info(
    version: '1.0.0',
    title: 'Online-Shop API'
)]
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
    #[OAT\Post(
        path: '/api/v1/authenticate',
        summary: 'Meldet einen Benutzer an und setzt das JWT-Cookie',
        requestBody: new OAT\RequestBody(
            required: true,
            content: new OAT\JsonContent(
                properties: [
                    new OAT\Property(
                        property: 'username',
                        type: 'string',
                        example: 'benutzer'
                    ),
                    new OAT\Property(
                        property: 'password',
                        type: 'string',
                        example: '123456'
                    )
                ]
            )
        ),
        tags: ['Authentifizierung'],
        responses: [
            new OAT\Response(
                response: 200,
                description: 'Anmeldung erfolgreich; JSON mit message und JWT im Cookie jwt_token, gültig für eine Stunde'
            ),
            new OAT\Response(
                response: 400,
                description: 'Username oder Passwort fehlt, ist kein String oder ist leer'
            ),
            new OAT\Response(
                response: 401,
                description: 'Benutzername oder Passwort falsch'
            ),
            new OAT\Response(
                response: 500,
                description: 'Interner Server Error'
            )
        ]
    )]
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