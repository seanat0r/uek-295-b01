<?php
declare(strict_types=1);

use helpers\response as helper;
use OpenApi\Attributes as OAT;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use ReallySimpleJWT\Token;

class AuthController
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
        operationId: 'authenticate',
        description: 'Prüft die in config/config.json hinterlegten Zugangsdaten. Der Benutzername wird getrimmt; das Passwort wird unverändert geprüft. Anschliessende Produkt- und Kategorieanfragen müssen das Cookie jwt_token mitsenden. Für diese Anmeldung ist kein Token erforderlich.',
        summary: 'Anmelden und JWT-Cookie erhalten',
        tags: ['Authentifizierung'],
        security: [],
        requestBody: new OAT\RequestBody(required: true, content: new OAT\JsonContent(ref: '#/components/schemas/Credentials')),
        responses: [
            new OAT\Response(response: 200, description: 'Anmeldung erfolgreich; JWT ist eine Stunde gültig und wird im Cookie jwt_token gesetzt.', headers: [new OAT\Header(header: 'Set-Cookie', description: 'Enthält jwt_token mit einem Ablaufzeitpunkt nach einer Stunde. Der Token wird nicht im JSON-Body zurückgegeben.', schema: new OAT\Schema(type: 'string', example: 'jwt_token=<JWT>; expires=<Ablaufzeitpunkt>; Max-Age=3600'))], content: new OAT\JsonContent(ref: '#/components/schemas/Message', example: ['message' => 'Authenticated successfully'])),
            new OAT\Response(response: 400, description: 'Ungültiges JSON oder fehlender beziehungsweise nicht verarbeitbarer Request-Body.', content: new OAT\JsonContent(ref: '#/components/schemas/Error', example: ['error' => 'Invalid request body.', 'code' => 400])),
            new OAT\Response(response: 422, description: 'Benutzername oder Passwort fehlt, ist kein String oder ist leer.', content: new OAT\JsonContent(ref: '#/components/schemas/Error', example: ['error' => 'Username and password must be strings', 'code' => 422])),
            new OAT\Response(response: 401, description: 'Zugangsdaten stimmen nicht mit der Konfiguration überein.', content: new OAT\JsonContent(ref: '#/components/schemas/Error', example: ['error' => 'Invalid username or password', 'code' => 401])),
            new OAT\Response(ref: '#/components/responses/ServerError', response: 500),
        ]
    )]
    public function authenticate(Request $request, Response $response, array $args): Response
    {
        try {
            $requestBody = $request->getParsedBody();
            if (!is_array($requestBody)) {
                return helper::error($response, 'Invalid request body.', 400);
            }

            $username = $requestBody['username'] ?? null;
            $password = $requestBody['password'] ?? null;

            if (!is_string($username) || !is_string($password)) {
                return helper::error($response, "Username and password must be strings", 422);
            }

            $username = trim($username);

            if ($username === '' || $password === '') {
                return helper::error($response, "Invalid username or password", 422);
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
}