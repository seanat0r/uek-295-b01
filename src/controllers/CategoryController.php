<?php
declare(strict_types=1);

use helpers\response as helper;
use OpenApi\Attributes as OAT;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use repositories\CategoryRepository;

/**
 * Controller for the Category Endpoint
 */
class CategoryController
{
    /**
     * Constructor
     * @param CategoryRepository $categoryRepository SQL statements for category
     */
    public function __construct(
        private CategoryRepository $categoryRepository,
    )
    {
    }

    /**
     * Send all Categories
     * @param Request $request
     * @param Response $response
     * @param array $args
     * @return Response
     */
    #[OAT\Get(
        path: '/api/v1/categories',
        operationId: 'getCategories',
        description: 'Liefert alle Kategorien als JSON-Array, einschliesslich inaktiver Kategorien. Ohne Treffer wird ein leeres Array zurückgegeben. Es gibt keine Filter oder Seitennavigation.',
        summary: 'Alle Kategorien abrufen',
        tags: ['Kategorien'],
        responses: [
            new OAT\Response(response: 200, description: 'Kategorieliste; bei leerem Bestand [].', content: new OAT\JsonContent(type: 'array', items: new OAT\Items(ref: '#/components/schemas/Category'))),
            new OAT\Response(ref: '#/components/responses/Unauthenticated', response: 401),
            new OAT\Response(ref: '#/components/responses/ServerError', response: 500),
        ]
    )]
    public function getCategories(Request $request, Response $response, array $args): Response
    {
        try {
            $data = $this->categoryRepository->getAllCategories();
            return helper::success($response, $data);

        } catch (Exception $e) {
            return helper::error($response, $e->getMessage(), 500);
        }
    }

    /**
     * Send one category back
     * @param Request $request
     * @param Response $response
     * @param array $args
     * @return Response
     */
    #[OAT\Get(
        path: '/api/v1/category/{category_id}',
        operationId: 'getCategory',
        description: 'Liefert eine Kategorie. Bei einer fehlenden Kategorie wird Status 404 mit error und code zurückgegeben.',
        summary: 'Kategorie anhand der ID abrufen',
        tags: ['Kategorien'],
        parameters: [new OAT\Parameter(ref: '#/components/parameters/CategoryId')],
        responses: [
            new OAT\Response(response: 200, description: 'Kategorie gefunden.', content: new OAT\JsonContent(ref: '#/components/schemas/Category')),
            new OAT\Response(response: 400, description: 'Kategorie-ID ist keine ganze Zahl zwischen 1 und 2147483647.', content: new OAT\JsonContent(ref: '#/components/schemas/Error', example: ['error' => 'Category ID must be a positive integer.', 'code' => 400])),
            new OAT\Response(response: 404, description: 'Keine Kategorie mit dieser ID vorhanden.', content: new OAT\JsonContent(ref: '#/components/schemas/Error', example: ['error' => 'Category not found.', 'code' => 404])),
            new OAT\Response(ref: '#/components/responses/Unauthenticated', response: 401),
            new OAT\Response(ref: '#/components/responses/ServerError', response: 500),
        ]
    )]
    public function getCategory(Request $request, Response $response, array $args): Response
    {
        try {
            $id = $args['category_id'] ?? null;

            if (filter_var($id, FILTER_VALIDATE_INT, [
                    'options' => ['min_range' => 1, 'max_range' => 2147483647],
                ]) === false) {
                return helper::error($response, "Category ID must be a positive integer.");
            }

            $data = $this->categoryRepository->getCategory($id);

            // Nothing was found.
            if ($data === []) {
                return helper::error($response, "Category not found.", code: 404);
            }

            return helper::success($response, $data);
        } catch (Exception $e) {
            return helper::error($response, $e->getMessage(), 500);
        }
    }

    /**
     * create category
     * @param Request $request
     * @param Response $response
     * @param array $args
     * @return Response
     */
    #[OAT\Post(
        path: '/api/v1/category',
        operationId: 'postCategory',
        description: 'Erstellt eine Kategorie mit automatisch vergebener ID. name und active sind erforderlich. Der Name wird getrimmt.',
        summary: 'Kategorie erstellen',
        tags: ['Kategorien'],
        requestBody: new OAT\RequestBody(description: 'name und active sind erforderlich.', required: true, content: new OAT\JsonContent(ref: '#/components/schemas/CategoryInput')),
        responses: [
            new OAT\Response(response: 201, description: 'Kategorie erstellt; enthält die vergebene ID.', content: new OAT\JsonContent(ref: '#/components/schemas/Category')),
            new OAT\Response(response: 400, description: 'Request-Body fehlt oder name beziehungsweise active sind ungültig.', content: new OAT\JsonContent(ref: '#/components/schemas/Error', example: ['error' => 'Category name is required.', 'code' => 400])),
            new OAT\Response(ref: '#/components/responses/Unauthenticated', response: 401),
            new OAT\Response(ref: '#/components/responses/ServerError', response: 500),
        ]
    )]
    public function postCategory(Request $request, Response $response, array $args): Response
    {
        // request body
        $requestBody = $request->getParsedBody();
        if (!is_array($requestBody)) {
            return helper::error($response, 'Invalid request body.');
        }

        // validation
        $rawActive = $requestBody['active'] ?? null;
        $rawActive = $this->validateActive($rawActive);
        if ($rawActive['isError'] || $rawActive['value'] === null) {
            return helper::error($response, 'Active must be true, false, 1 or 0.');
        }
        if ($rawActive['value'] === true) {
            $active = 1;
        } else {
            $active = 0;
        }

        $name = $requestBody['name'] ?? null;
        if (!is_string($name) || trim($name) === '') {
            return helper::error($response, 'Category name is required.');
        }

        if (mb_strlen(trim($name), 'UTF-8') > 500) {
            return helper::error($response, 'Category name is too long.');
        }

        $valueToCreate = [
            'name' => trim($name),
            'active' => $active,
        ];

        try {
            $data = $this->categoryRepository->createCategory($valueToCreate);
            return helper::success($response, $data, code: 201);
        } catch (Exception $e) {
            return helper::error($response, $e->getMessage(), 500);
        }
    }

    /**
     * validate active in response body
     * @param mixed $value
     * @return array
     */
    private function validateActive(mixed $value): array
    {
        $infoArray = [
            'isError' => false,
            'value' => null,
        ];

        if (is_string($value)) {
            $value = trim($value);
        }

        if (in_array($value, [true, 1, '1', 'true'], true)) {
            $infoArray['value'] = true;
        } elseif (in_array($value, [false, 0, '0', 'false'], true)) {
            $infoArray['value'] = false;
        } else {
            $infoArray['isError'] = true;
        }

        return $infoArray;
    }

    /**
     * update category
     * @param Request $request
     * @param Response $response
     * @param array $args
     * @return Response
     */
    #[OAT\Patch(
        path: '/api/v1/category/{category_id}',
        operationId: 'patchCategory',
        description: 'Ändert nur die übergebenen Felder name und active. Mindestens eines dieser Felder ist erforderlich; null ist für beide Felder ungültig. Nicht übergebene Felder behalten ihren Wert. Der Name wird getrimmt.',
        summary: 'Kategorie teilweise aktualisieren',
        tags: ['Kategorien'],
        parameters: [new OAT\Parameter(ref: '#/components/parameters/CategoryId')],
        requestBody: new OAT\RequestBody(description: 'Mindestens name oder active angeben. Nicht übergebene Felder bleiben unverändert.', required: true, content: new OAT\JsonContent(ref: '#/components/schemas/CategoryPatch')),
        responses: [
            new OAT\Response(response: 200, description: 'Kategorie aktualisiert; vollständige Kategorie im Response-Body.', content: new OAT\JsonContent(ref: '#/components/schemas/Category')),
            new OAT\Response(response: 400, description: 'Kategorie-ID, Request-Body oder ein übergebener Feldwert ist ungültig.', content: new OAT\JsonContent(ref: '#/components/schemas/Error', example: ['error' => 'Active must be true, false, 1 or 0.', 'code' => 400])),
            new OAT\Response(response: 404, description: 'Keine Kategorie mit dieser ID vorhanden.', content: new OAT\JsonContent(ref: '#/components/schemas/Error', example: ['error' => 'Category not found.', 'code' => 404])),
            new OAT\Response(response: 422, description: 'Weder name noch active übergeben.', content: new OAT\JsonContent(ref: '#/components/schemas/Error', example: ['error' => 'To update anything, one field is required.', 'code' => 422])),
            new OAT\Response(ref: '#/components/responses/Unauthenticated', response: 401),
            new OAT\Response(ref: '#/components/responses/ServerError', response: 500),
        ]
    )]
    public function patchCategory(Request $request, Response $response, array $args): Response
    {
        // args
        $id = $args['category_id'] ?? null;

        if (filter_var($id, FILTER_VALIDATE_INT, [
                'options' => ['min_range' => 1, 'max_range' => 2147483647],
            ]) === false) {
            return helper::error($response, "Category ID must be a positive integer.");
        }

        // request Body
        $requestBody = $request->getParsedBody();
        if (!is_array($requestBody)) {
            return helper::error($response, 'Invalid request body.');
        }

        // Validate only fields supplied in the PATCH request.
        $active = null;
        if (array_key_exists('active', $requestBody)) {
            $rawActive = $this->validateActive($requestBody['active']);
            if ($rawActive['isError']) {
                return helper::error($response, 'Active must be true, false, 1 or 0.');
            }
            $active = (int)$rawActive['value'];
        }

        $name = null;
        if (array_key_exists('name', $requestBody)) {
            $name = $requestBody['name'];
            if (!is_string($name) || trim($name) === '') {
                return helper::error($response, 'Category name is required.');
            }
            $name = trim($name);
            if (mb_strlen($name, 'UTF-8') > 500) {
                return helper::error($response, 'Category name is too long.');
            }
        }

        $valueToUpdate = [
            'name' => $name,
            'active' => $active,
        ];

        if ($valueToUpdate['name'] === null && $valueToUpdate['active'] === null) {
            return helper::error($response, 'To update anything, one field is required.', 422);
        }

        try {
            $data = $this->categoryRepository->updateCategory($id, $valueToUpdate);
            if ($data === []) {
                return helper::error($response, "Category not found.", code: 404);
            }
            return helper::success($response, $data);

        } catch (Exception $e) {
            return helper::error($response, $e->getMessage(), 500);
        }
    }

    /**
     * delete a category
     * @param Request $request
     * @param Response $response
     * @param array $args
     * @return Response
     */
    #[OAT\Delete(
        path: '/api/v1/category/{category_id}',
        operationId: 'deleteCategory',
        description: 'Löscht eine Kategorie. Bei Erfolg wird kein Antwortinhalt gesendet. Eine Löschung, die an einer Datenbankbedingung scheitert, wird aktuell als 500 gemeldet.',
        summary: 'Kategorie löschen',
        tags: ['Kategorien'],
        parameters: [new OAT\Parameter(ref: '#/components/parameters/CategoryId')],
        responses: [
            new OAT\Response(response: 204, description: 'Kategorie gelöscht; leerer Response-Body.'),
            new OAT\Response(response: 400, description: 'Kategorie-ID ist keine ganze Zahl zwischen 1 und 2147483647.', content: new OAT\JsonContent(ref: '#/components/schemas/Error', example: ['error' => 'Category ID must be a positive integer.', 'code' => 400])),
            new OAT\Response(response: 404, description: 'Keine Kategorie mit dieser ID vorhanden.', content: new OAT\JsonContent(ref: '#/components/schemas/Error', example: ['error' => 'Category not found.', 'code' => 404])),
            new OAT\Response(ref: '#/components/responses/Unauthenticated', response: 401),
            new OAT\Response(ref: '#/components/responses/ServerError', response: 500),
        ]
    )]
    public function deleteCategory(Request $request, Response $response, array $args): Response
    {
        try {
            $id = $args['category_id'] ?? null;

            if (filter_var($id, FILTER_VALIDATE_INT, [
                    'options' => ['min_range' => 1, 'max_range' => 2147483647],
                ]) === false) {
                return helper::error($response, "Category ID must be a positive integer.");
            }

            $data = $this->categoryRepository->deleteCategory($id);

            if ($data["gotDeleted"] === false) {
                return helper::error($response, "Category not found.", code: 404);
            } else {
                return helper::success($response, code: 204);
            }
        } catch (Exception $e) {
            return helper::error($response, $e->getMessage(), 500);
        }
    }
}
