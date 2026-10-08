<?php
declare(strict_types=1);

use helpers\response as helper;
use OpenApi\Attributes as OAT;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use repositories\ProductRepository;

/**
 * Controller for the Product Endpoint
 */
class ProductController
{
    /**
     * Constructor
     * @param ProductRepository $productRepository SQL statements for Products
     */
    public function __construct(
        private ProductRepository $productRepository,
    )
    {
    }

    /**
     * send all products
     * @param Request $request
     * @param Response $response
     * @param array $args
     * @return Response
     */
    #[OAT\Get(
        path: '/api/v1/products',
        summary: 'Gibt alle Produkte zurück',
        tags: ['Produkte'],
        responses: [
            new OAT\Response(
                response: 200,
                description: 'JSON-Array mit allen Produkten; [] wenn keine vorhanden sind'
            ),
            new OAT\Response(
                response: 401,
                description: 'JWT-Cookie fehlt, ist ungültig oder abgelaufen'
            ),
            new OAT\Response(
                response: 500,
                description: 'Interner Serverfehler, beispielsweise ein Datenbankfehler'
            )
        ]
    )]
    public function getProducts(Request $request, Response $response, array $args): Response
    {
        try {
            $data = $this->productRepository->getAllProducts();
            return helper::success($response, $data);

        } catch (Exception $e) {
            return helper::error($response, $e->getMessage(), 500);
        }
    }

    /**
     * send one product
     * @param Request $request
     * @param Response $response
     * @param array $args
     * @return Response
     */
    #[OAT\Get(
        path: '/api/v1/product/{sku}',
        summary: 'Gibt ein Produkt anhand der SKU zurück',
        tags: ['Produkte'],
        parameters: [
            new OAT\Parameter(
                name: 'sku',
                description: 'Eindeutige SKU des Produkts',
                in: 'path',
                required: true,
                schema: new OAT\Schema(
                    type: 'string',
                    example: '12345678'
                )
            )
        ],
        responses: [
            new OAT\Response(
                response: 200,
                description: 'JSON mit dem Produkt'
            ),
            new OAT\Response(
                response: 400,
                description: 'SKU ist leer oder länger als 100 Zeichen'
            ),
            new OAT\Response(
                response: 404,
                description: 'Produkt nicht gefunden; JSON mit message'
            ),
            new OAT\Response(
                response: 401,
                description: 'JWT-Cookie fehlt, ist ungültig oder abgelaufen'
            ),
            new OAT\Response(
                response: 500,
                description: 'Interner Serverfehler, beispielsweise ein Datenbankfehler'
            )
        ]
    )]
    public function getProduct(Request $request, Response $response, array $args): Response
    {
        try {
            $sku = $args['sku'] ?? null;
            if (!is_string($sku) || trim($sku) === '' || mb_strlen($sku, 'UTF-8') > 100) {
                return helper::error($response, 'SKU must contain 1 to 100 characters.');
            }
            $sku = trim($sku);

            $data = $this->productRepository->getProduct($sku);

            // Nothing was found.
            if ($data === []) {
                return helper::success($response, message: "Product not found.", code: 404);
            }

            return helper::success($response, $data);
        } catch (Exception $e) {
            return helper::error($response, $e->getMessage(), 500);
        }
    }

    /**
     * update or create one product
     * @param Request $request
     * @param Response $response
     * @param array $args
     * @return Response
     */
    #[OAT\Put(
        path: '/api/v1/product/{sku}',
        summary: 'Erstellt oder ersetzt ein Produkt anhand der SKU',
        requestBody: new OAT\RequestBody(
            required: true,
            content: new OAT\JsonContent(
                properties: [
                    new OAT\Property(
                        property: 'name',
                        type: 'string',
                        example: 'Brot'
                    ),
                    new OAT\Property(
                        property: 'active',
                        example: 'true, 1 oder "true"',
                    ),
                    new OAT\Property(
                        property: 'id_category',
                        type: 'integer',
                        example: 1,
                    ),
                    new OAT\Property(
                        property: 'image',
                        type: 'string',
                        example: 'https://example.com/logo.svg'
                    ),
                    new OAT\Property(
                        property: 'description',
                        type: 'string',
                        example: 'Logo der CsBe'
                    ),
                    new OAT\Property(
                        property: 'price',
                        type: 'number',
                        example: 123.98
                    ),
                    new OAT\Property(
                        property: 'stock',
                        type: 'integer',
                        example: 3
                    )
                ],
            )
        ),
        tags: ['Produkte'],
        parameters: [
            new OAT\Parameter(
                name: 'sku',
                description: 'Eindeutige SKU des Produkts',
                in: 'path',
                required: true,
                schema: new OAT\Schema(
                    type: 'string',
                    example: '1',
                )
            )
        ],
        responses: [
            new OAT\Response(
                response: 200,
                description: 'Produkt aktualisiert'
            ),
            new OAT\Response(
                response: 201,
                description: 'Produkt erstellt'
            ),
            new OAT\Response(
                response: 400,
                description: 'Ungültiger Request-Body, SKU, active oder Datentyp eines optionalen Textfelds'
            ),
            new OAT\Response(
                response: 422,
                description: 'Validation fehlgeschlagen'
            ),
            new OAT\Response(
                response: 401,
                description: 'JWT-Cookie fehlt, ist ungültig oder abgelaufen'
            ),
            new OAT\Response(
                response: 500,
                description: 'Interner Server Error'
            )
        ]
    )]
    public function putProduct(Request $request, Response $response, array $args): Response
    {
        // validate args
        $sku = $args['sku'] ?? null;
        if (!is_string($sku) || trim($sku) === '' || mb_strlen($sku, 'UTF-8') > 100) {
            return helper::error($response, 'SKU must contain 1 to 100 characters.', 400);
        }
        $sku = trim($sku);


        $requestBody = $request->getParsedBody();
        if (!is_array($requestBody)) {
            return helper::error($response, 'Invalid request body.');
        }

        // mandatory fields
        $name = $requestBody['name'] ?? null;
        if (!is_string($name) || trim($name) === '') {
            return helper::error($response, 'Name is mandatory', 422);
        }

        if (mb_strlen(trim($name), 'UTF-8') > 500) {
            return helper::error($response, 'Name is too long.', 422);
        }

        $stock = $requestBody['stock'] ?? null;
        if (!is_int($stock) && !is_string($stock)) {
            return helper::error($response, 'Stock must be an integer between 0 and 2147483647.', 422);
        }
        // 2147483647 is the max value of a sql int
        $stock = filter_var($stock, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 2147483647]]);
        if ($stock === false) {
            return helper::error($response, 'Stock must be an integer between 0 and 2147483647.', 422);
        }

        $price = $requestBody['price'] ?? null;
        // 1e63 max size of a decimal in the db
        if ((!is_int($price) && !is_float($price) && !is_string($price))
            || !is_numeric($price) || !is_finite((float)$price) || (float)$price <= 0 || (float)$price >= 1e63) {
            return helper::error($response, 'Price must be a number greater than zero.', 422);
        }

        $rawActive = $requestBody['active'] ?? null;

        if (is_string($rawActive)) {
            $rawActive = trim($rawActive);
        }

        if (in_array($rawActive, [true, 1, '1', 'true'], true)) {
            $active = 1;
        } elseif (in_array($rawActive, [false, 0, '0', 'false'], true)) {
            $active = 0;
        } else {
            return helper::error($response, 'Active must be true, false, 1 or 0.',
            );
        }

        // optional category
        $idCategory = $requestBody['id_category'] ?? null;
        if ($idCategory !== null) {
            if (!is_int($idCategory) && !is_string($idCategory)) {
                return helper::error($response, 'Category ID must be a positive integer or null.', 422);
            }
            $idCategory = filter_var($idCategory, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 2147483647]]);
            if ($idCategory === false) {
                return helper::error($response, 'Category ID must be a positive integer or null.', 422);
            }
        }

        $valueToUpdate = [
            'name' => trim($name),
            'active' => $active,
            'idCategory' => $idCategory,
            'stock' => $stock,
            'price' => number_format((float)$price, 2, '.', ''),
        ];

        // optional strings
        foreach (['image', 'description'] as $field) {
            $value = $requestBody[$field] ?? null;
            if ($value !== null && !is_string($value)) {
                return helper::error($response, "$field must be a string or null.");
            }
            if ($field === 'image' && $value !== null && mb_strlen(trim($value), 'UTF-8') > 1000) {
                return helper::error($response, 'Image is too long.', 422);
            }
            // 65535 is max size of a sql text datatype
            if ($field === 'description' && $value !== null && strlen($value) > 65535) {
                return helper::error($response, 'Description is too long.', 422);
            }
            $valueToUpdate[$field] = $value === null || trim($value) === ''
                ? null
                : trim($value);
        }

        try {

            $data = $this->productRepository->upsertProduct($sku, $valueToUpdate);

            if ($data['wasCreated'] === true) {
                return helper::success($response, $data['product'], code: 201);
            } else {
                return helper::success($response);
            }
        } catch (InvalidArgumentException $e) {
            return helper::error($response, $e->getMessage(), 422);
        } catch (Exception $e) {
            return helper::error($response, $e->getMessage(), 500);
        }
    }

    /**
     * delete one product
     * @param Request $request
     * @param Response $response
     * @param array $args
     * @return Response
     */
    #[OAT\Delete(
        path: '/api/v1/product/{sku}',
        summary: 'Löscht ein Produkt anhand der SKU',
        tags: ['Produkte'],
        parameters: [
            new OAT\Parameter(
                name: 'sku',
                description: 'Eindeutige SKU des Produkts',
                in: 'path',
                required: true,
                schema: new OAT\Schema(
                    type: 'string',
                    example: '1',
                )
            )
        ],
        responses: [
            new OAT\Response(
                response: 204,
                description: 'Produkt gelöscht'
            ),
            new OAT\Response(
                response: 400,
                description: 'SKU ist leer oder länger als 100 Zeichen'
            ),
            new OAT\Response(
                response: 404,
                description: 'Produkt nicht gefunden; JSON mit error und code'
            ),
            new OAT\Response(
                response: 401,
                description: 'JWT-Cookie fehlt, ist ungültig oder abgelaufen'
            ),
            new OAT\Response(
                response: 500,
                description: 'Interner Server Error'
            )
        ]
    )]
    public function deleteProduct(Request $request, Response $response, array $args): Response
    {
        try {
            $sku = $args['sku'] ?? null;
            if (!is_string($sku) || trim($sku) === '' || mb_strlen($sku, 'UTF-8') > 100) {
                return helper::error($response, 'SKU must contain 1 to 100 characters.', 400);
            }
            $sku = trim($sku);

            $data = $this->productRepository->deleteProduct($sku);

            if ($data["gotDeleted"] === false) {
                return helper::error($response, code: 404);
            } else {
                return helper::success($response, code: 204);
            }


        } catch (Exception $e) {
            return helper::error($response, $e->getMessage(), 500);
        }
    }
}
