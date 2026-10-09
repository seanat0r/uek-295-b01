<?php
declare(strict_types=1);

use helpers\response as helper;
use OpenApi\Attributes as OAT;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Misc API Controller
 */
#[OAT\OpenApi(
    openapi: '3.0.3',
    info: new OAT\Info(
        version: '1.0.0',
        title: 'Online-Shop API',
        description: 'API für Produkte und Kategorien. Zuerst über POST /api/v1/authenticate anmelden und danach das Cookie jwt_token mitsenden. Request-Bodies verwenden application/json. Antworten enthalten direkt die Daten; Fehler enthalten error und code. Ungültiges JSON, ein fehlender oder nicht verarbeitbarer Body und ungültige URL-Parameter führen zu 400; fehlende oder ungültige Felder zu 422. DELETE liefert bei Erfolg 204 ohne Body. Anfragen ohne passende Route und HTTP-Methode erhalten 404.'
    ),
    servers: [new OAT\Server(url: '/', description: 'Aktueller Host')],
    security: [['CookieAuth' => []]],
    tags: [
        new OAT\Tag(name: 'Authentifizierung', description: 'Anmeldung und Ausstellung des JWT-Cookies.'),
        new OAT\Tag(name: 'Produkte', description: 'Produkte auflisten, abrufen, erstellen, ersetzen und löschen.'),
        new OAT\Tag(name: 'Kategorien', description: 'Kategorien auflisten, abrufen, erstellen, teilweise aktualisieren und löschen.')
    ]
)]
#[OAT\Components(
    securitySchemes: [
        new OAT\SecurityScheme(
            securityScheme: 'CookieAuth',
            type: 'apiKey',
            description: 'JWT-Cookie aus POST /api/v1/authenticate; eine Stunde gültig. Fehlender oder ungültiger Token: 401. In Swagger UI zuerst auf demselben Host und Port anmelden.',
            name: 'jwt_token',
            in: 'cookie'
        )
    ],
    parameters: [
        new OAT\Parameter(
            parameter: 'Sku', name: 'sku', in: 'path', required: true,
            description: 'Eindeutige Artikelnummer. Vor dem Trimmen höchstens 100 Unicode-Zeichen; darf nach dem Trimmen nicht leer sein.',
            schema: new OAT\Schema(type: 'string', minLength: 1, maxLength: 100, example: 'PROD-0001')
        ),
        new OAT\Parameter(
            parameter: 'CategoryId', name: 'category_id', in: 'path', required: true,
            description: 'Positive ID einer Kategorie.',
            schema: new OAT\Schema(type: 'integer', minimum: 1, maximum: 2147483647, example: 1)
        )
    ],
    responses: [
        new OAT\Response(
            response: 'Unauthenticated',
            description: 'JWT-Cookie fehlt, ist beschädigt, ungültig oder abgelaufen. Erneut über POST /api/v1/authenticate anmelden.',
            content: new OAT\JsonContent(ref: '#/components/schemas/Error', example: ['error' => 'Token is missing.', 'code' => 401])
        ),
        new OAT\Response(
            response: 'ServerError', description: 'Interner Fehler bei Datenbankzugriff, Token-Erstellung oder Verarbeitung.',
            content: new OAT\JsonContent(ref: '#/components/schemas/Error', example: ['error' => 'Internal Server Error', 'code' => 500])
        ),
    ],
    schemas: [
        new OAT\Schema(
            schema: 'ActiveInput',
            description: 'Akzeptiert JSON-Booleans, die Ganzzahlen 0 und 1 sowie die Strings "true", "false", "0" und "1". Strings werden vor der Prüfung getrimmt. Gespeichert wird 0 oder 1.',
            anyOf: [
                new OAT\Schema(type: 'boolean'),
                new OAT\Schema(type: 'integer', enum: [0, 1]),
                new OAT\Schema(type: 'string', pattern: '^\s*(true|false|0|1)\s*$')
            ],
            example: true
        ),
        new OAT\Schema(
            schema: 'Error', type: 'object', required: ['error', 'code'],
            properties: [
                new OAT\Property(property: 'error', type: 'string', description: 'Beschreibung des Fehlers.', example: 'Token is missing.'),
                new OAT\Property(property: 'code', type: 'integer', description: 'Entspricht dem HTTP-Statuscode.', example: 401)
            ]
        ),
        new OAT\Schema(
            schema: 'Message', type: 'object', required: ['message'],
            properties: [new OAT\Property(property: 'message', type: 'string', example: 'Authenticated successfully')]
        ),
        new OAT\Schema(
            schema: 'Credentials', type: 'object', required: ['username', 'password'],
            properties: [
                new OAT\Property(property: 'username', description: 'Konfigurierter Benutzername; darf nach dem Trimmen nicht leer sein.', type: 'string', minLength: 1, example: 'dein-benutzername'),
                new OAT\Property(property: 'password', description: 'Konfiguriertes Passwort; wird ohne Trimmen geprüft.', type: 'string', format: 'password', minLength: 1, writeOnly: true, example: 'dein-passwort')
            ]
        ),
        new OAT\Schema(
            schema: 'Product', type: 'object', required: ['product_id', 'sku', 'active', 'id_category', 'name', 'image', 'description', 'price', 'stock'],
            example: ['product_id' => 1, 'sku' => 'PROD-0001', 'active' => 1, 'id_category' => 1, 'name' => 'Brot', 'image' => 'images/products/prod-0001.jpg', 'description' => 'Frisches Brot', 'price' => 4.50, 'stock' => 12],
            description: 'Produkt aus der Datenbank. active wird als 0 oder 1 ausgegeben. Numerische Datenbankfelder ausser price können je nach PDO-Treiber als Ganzzahl oder numerischer String vorliegen; die Produktliste normalisiert diese Felder zu Ganzzahlen.',
            properties: [
                new OAT\Property(property: 'product_id', description: 'Automatisch vergebene Produkt-ID.', readOnly: true, anyOf: [new OAT\Schema(type: 'integer'), new OAT\Schema(type: 'string', pattern: '^[0-9]+$')], example: 1),
                new OAT\Property(property: 'sku', type: 'string', example: 'PROD-0001'),
                new OAT\Property(property: 'active', anyOf: [new OAT\Schema(type: 'integer', enum: [0, 1]), new OAT\Schema(type: 'string', enum: ['0', '1'])], example: 1),
                new OAT\Property(property: 'id_category', nullable: true, anyOf: [new OAT\Schema(type: 'integer', nullable: true), new OAT\Schema(type: 'string', pattern: '^[0-9]+$', nullable: true)], example: 1),
                new OAT\Property(property: 'name', type: 'string', example: 'Brot'),
                new OAT\Property(property: 'image', type: 'string', nullable: true, example: 'images/products/prod-0001.jpg'),
                new OAT\Property(property: 'description', type: 'string', nullable: true, example: 'Frisches Brot'),
                new OAT\Property(property: 'price', type: 'number', description: 'Wird als JSON-Zahl ausgegeben.', example: 4.50),
                new OAT\Property(property: 'stock', anyOf: [new OAT\Schema(type: 'integer'), new OAT\Schema(type: 'string', pattern: '^-?[0-9]+$')], example: 12)
            ]
        ),
        new OAT\Schema(
            schema: 'ProductInput', type: 'object', required: ['name', 'active', 'price', 'stock'],
            example: ['name' => 'Brot', 'active' => true, 'id_category' => 1, 'image' => 'images/products/prod-0001.jpg', 'description' => 'Frisches Brot', 'price' => 4.50, 'stock' => 12],
            properties: [
                new OAT\Property(property: 'name', description: 'Pflichtfeld; nach dem Trimmen 1 bis 500 Unicode-Zeichen.', type: 'string', minLength: 1, example: 'Brot'),
                new OAT\Property(property: 'active', ref: '#/components/schemas/ActiveInput'),
                new OAT\Property(property: 'id_category', description: 'Optionale, existierende Kategorie-ID zwischen 1 und 2147483647; Ganzzahl oder entsprechender Integer-String. Fehlend oder null entfernt die Zuordnung.', nullable: true, anyOf: [new OAT\Schema(type: 'integer', minimum: 1, maximum: 2147483647, nullable: true), new OAT\Schema(type: 'string', nullable: true)], example: 1),
                new OAT\Property(property: 'image', description: 'Optionaler Bildpfad oder URL; nach dem Trimmen höchstens 1000 Unicode-Zeichen. Fehlend, null oder leer wird als null gespeichert. Keine URL-Prüfung.', type: 'string', nullable: true, example: 'images/products/prod-0001.jpg'),
                new OAT\Property(property: 'description', description: 'Optionaler Text; vor dem Trimmen höchstens 65535 Bytes in UTF-8. Fehlend, null oder leer wird als null gespeichert.', type: 'string', nullable: true, example: 'Frisches Brot'),
                new OAT\Property(property: 'price', description: 'Pflichtfeld; endliche Zahl grösser als 0 und kleiner als 1e63. Numerische Strings werden ebenfalls akzeptiert. Wird auf zwei Nachkommastellen gerundet und muss danach noch grösser als null sein.', anyOf: [new OAT\Schema(type: 'number', minimum: 0, exclusiveMinimum: true, maximum: 1e63, exclusiveMaximum: true), new OAT\Schema(type: 'string')], example: 4.50),
                new OAT\Property(property: 'stock', description: 'Pflichtfeld; ganze Zahl zwischen 0 und 2147483647. Entsprechende Integer-Strings werden ebenfalls akzeptiert.', anyOf: [new OAT\Schema(type: 'integer', minimum: 0, maximum: 2147483647), new OAT\Schema(type: 'string')], example: 12)
            ]
        ),
        new OAT\Schema(
            schema: 'Category', type: 'object', required: ['category_id', 'active', 'name'],
            example: ['category_id' => 1, 'active' => 1, 'name' => 'Backwaren'],
            description: 'Kategorie aus der Datenbank. Numerische Felder können je nach PDO-Treiber als Ganzzahl oder numerischer String ausgegeben werden.',
            properties: [
                new OAT\Property(property: 'category_id', description: 'Automatisch vergebene Kategorie-ID.', readOnly: true, anyOf: [new OAT\Schema(type: 'integer'), new OAT\Schema(type: 'string', pattern: '^[0-9]+$')], example: 1),
                new OAT\Property(property: 'active', anyOf: [new OAT\Schema(type: 'integer', enum: [0, 1]), new OAT\Schema(type: 'string', enum: ['0', '1'])], example: 1),
                new OAT\Property(property: 'name', type: 'string', example: 'Backwaren')
            ]
        ),
        new OAT\Schema(
            schema: 'CategoryInput', type: 'object', required: ['name', 'active'],
            example: ['name' => 'Backwaren', 'active' => true],
            properties: [
                new OAT\Property(property: 'name', description: 'Nach dem Trimmen 1 bis 500 Unicode-Zeichen.', type: 'string', minLength: 1, example: 'Backwaren'),
                new OAT\Property(property: 'active', ref: '#/components/schemas/ActiveInput')
            ]
        ),
        new OAT\Schema(
            schema: 'CategoryPatch', type: 'object',
            example: ['name' => 'Bäckerei'],
            description: 'Mindestens name oder active angeben. Weitere Felder werden ignoriert. Übergebene Felder dürfen nicht null sein.',
            anyOf: [new OAT\Schema(required: ['name']), new OAT\Schema(required: ['active'])],
            properties: [
                new OAT\Property(property: 'name', description: 'Nach dem Trimmen 1 bis 500 Unicode-Zeichen.', type: 'string', minLength: 1, example: 'Backwaren'),
                new OAT\Property(property: 'active', ref: '#/components/schemas/ActiveInput')
            ]
        )
    ]
)]
class ApiGeneral
{
    /**
     * 404 fallback endpoint
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
