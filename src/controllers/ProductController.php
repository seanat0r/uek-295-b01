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
        operationId: 'getProducts',
        description: 'Liefert alle Produkte als JSON-Array, einschliesslich inaktiver Produkte. Ohne Treffer wird ein leeres Array zurückgegeben. Es gibt keine Filter oder Seitennavigation.',
        summary: 'Alle Produkte abrufen',
        tags: ['Produkte'],
        responses: [
            new OAT\Response(response: 200, description: 'Produktliste; bei leerem Bestand [].', content: new OAT\JsonContent(type: 'array', items: new OAT\Items(ref: '#/components/schemas/Product'))),
            new OAT\Response(ref: '#/components/responses/Unauthenticated', response: 401),
            new OAT\Response(ref: '#/components/responses/ServerError', response: 500),
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
        operationId: 'getProduct',
        description: 'Liefert ein Produkt. Die SKU wird vor der Suche getrimmt. Bei einem fehlenden Produkt wird Status 404 mit error und code zurückgegeben.',
        summary: 'Produkt anhand der SKU abrufen',
        tags: ['Produkte'],
        parameters: [new OAT\Parameter(ref: '#/components/parameters/Sku')],
        responses: [
            new OAT\Response(response: 200, description: 'Produkt gefunden.', content: new OAT\JsonContent(ref: '#/components/schemas/Product')),
            new OAT\Response(response: 400, description: 'SKU ist leer oder länger als 100 Zeichen.', content: new OAT\JsonContent(ref: '#/components/schemas/Error', example: ['error' => 'SKU must contain 1 to 100 characters.', 'code' => 400])),
            new OAT\Response(response: 404, description: 'Kein Produkt mit dieser SKU vorhanden.', content: new OAT\JsonContent(ref: '#/components/schemas/Error', example: ['error' => 'Product not found.', 'code' => 404])),
            new OAT\Response(ref: '#/components/responses/Unauthenticated', response: 401),
            new OAT\Response(ref: '#/components/responses/ServerError', response: 500),
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
                return helper::error($response, "Product not found.", code: 404);
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
        operationId: 'putProduct',
        description: 'Legt ein Produkt unter der SKU an oder ersetzt seine bearbeitbaren Felder. name, active, price und stock sind immer erforderlich. Fehlende optionale Felder id_category, image und description werden auf null gesetzt. Leere Bild- und Beschreibungstexte werden ebenfalls null. Namen und optionale Texte werden getrimmt. Der Preis wird auf zwei Nachkommastellen gerundet und muss danach grösser als null sein. Eine angegebene Kategorie muss existieren.',
        summary: 'Produkt erstellen oder vollständig ersetzen',
        tags: ['Produkte'],
        parameters: [new OAT\Parameter(ref: '#/components/parameters/Sku')],
        requestBody: new OAT\RequestBody(description: 'name, active, price und stock sind erforderlich. Optionale Felder werden bei fehlender Angabe auf null gesetzt.', required: true, content: new OAT\JsonContent(ref: '#/components/schemas/ProductInput')),
        responses: [
            new OAT\Response(response: 200, description: 'Vorhandenes Produkt ersetzt.', content: new OAT\JsonContent(ref: '#/components/schemas/Product')),
            new OAT\Response(response: 201, description: 'Neues Produkt erstellt.', content: new OAT\JsonContent(ref: '#/components/schemas/Product')),
            new OAT\Response(response: 400, description: 'Ungültige SKU, ungültiges JSON oder fehlender beziehungsweise nicht verarbeitbarer Request-Body.', content: new OAT\JsonContent(ref: '#/components/schemas/Error', example: ['error' => 'Invalid request body.', 'code' => 400])),
            new OAT\Response(response: 422, description: 'Pflichtfelder fehlen, Feldwerte sind ungültig, Textgrenzen sind überschritten oder die Kategorie existiert nicht. Der gerundete Preis muss grösser als null sein.', content: new OAT\JsonContent(ref: '#/components/schemas/Error', example: ['error' => 'Category does not exist.', 'code' => 422])),
            new OAT\Response(ref: '#/components/responses/Unauthenticated', response: 401),
            new OAT\Response(ref: '#/components/responses/ServerError', response: 500),
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
            || !is_numeric($price) || !is_finite((float)$price) || (float)$price >= 1e63) {
            return helper::error($response, 'Price must be a number greater than zero.', 422);
        }
        $price = number_format((float)$price, 2, '.', '');
        if ((float)$price <= 0) {
            return helper::error($response, 'Price must be greater than zero after rounding.', 422);
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
            return helper::error($response, 'Active must be true, false, 1 or 0.', 422);
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
            'price' => $price,
        ];

        // optional strings
        foreach (['image', 'description'] as $field) {
            $value = $requestBody[$field] ?? null;
            if ($value !== null && !is_string($value)) {
                return helper::error($response, "$field must be a string or null.", 422);
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
                return helper::success($response, $data['product']);
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
        operationId: 'deleteProduct',
        description: 'Löscht das Produkt mit der angegebenen SKU. Bei Erfolg wird kein Antwortinhalt gesendet.',
        summary: 'Produkt löschen',
        tags: ['Produkte'],
        parameters: [new OAT\Parameter(ref: '#/components/parameters/Sku')],
        responses: [
            new OAT\Response(response: 204, description: 'Produkt gelöscht; leerer Response-Body.'),
            new OAT\Response(response: 400, description: 'SKU ist leer oder länger als 100 Zeichen.', content: new OAT\JsonContent(ref: '#/components/schemas/Error', example: ['error' => 'SKU must contain 1 to 100 characters.', 'code' => 400])),
            new OAT\Response(response: 404, description: 'Kein Produkt mit dieser SKU vorhanden.', content: new OAT\JsonContent(ref: '#/components/schemas/Error', example: ['error' => 'Product not found.', 'code' => 404])),
            new OAT\Response(ref: '#/components/responses/Unauthenticated', response: 401),
            new OAT\Response(ref: '#/components/responses/ServerError', response: 500),
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
                return helper::error($response, "Product not found.", code: 404);
            } else {
                return helper::success($response, code: 204);
            }


        } catch (Exception $e) {
            return helper::error($response, $e->getMessage(), 500);
        }
    }
}
