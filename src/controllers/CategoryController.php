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
        summary: 'Gibt alle Kategorien zurück',
        tags: ['Kategorien'],
        responses: [
            new OAT\Response(
                response: 200,
                description: 'JSON mit allen Kategorien',
            ),
            new OAT\Response(
                response: 401,
                description: 'Nicht authentifiziert'
            ),
            new OAT\Response(
                response: 500,
                description: 'Interner Server Error'
            )
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
        summary: 'Gibt eine Kategorie zurück',
        tags: ['Kategorien'],
        parameters: [
            new OAT\Parameter(
                name: 'category_id',
                description: 'ID der Kategorie',
                in: 'path',
                required: true,
                schema: new OAT\Schema(
                    type: 'integer',
                    example: 1
                )
            )
        ],
        responses: [
            new OAT\Response(
                response: 200,
                description: 'JSON mit einer Kategorie',
            ),
            new OAT\Response(
                response: 400,
                description: 'Ungültige Anfrage'
            ),
            new OAT\Response(
                response: 401,
                description: 'Nicht authentifiziert'
            ),
            new OAT\Response(
                response: 404,
                description: 'Kategorie nicht gefunden'
            ),
            new OAT\Response(
                response: 500,
                description: 'Interner Server Error'
            )
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
                return helper::success($response, message: "Category not found", code: 404);
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
        summary: 'Erstellt eine Kategorie',
        requestBody: new OAT\RequestBody(
            description: '`active` und `name` sind erforderlich.',
            required: true,
            content: new OAT\JsonContent(
                properties: [
                    new OAT\Property(
                        property: 'name',
                        type: 'string',
                        example: 'Backwaren'
                    ),
                    new OAT\Property(
                        property: 'active',
                        type: 'boolean',
                        example: true
                    )
                ]
            )
        ),
        tags: ['Kategorien'],
        responses: [
            new OAT\Response(
                response: 201,
                description: 'Kategorie erstellt',
            ),
            new OAT\Response(
                response: 400,
                description: 'Ungültige Anfrage',
            ),
            new OAT\Response(
                response: 401,
                description: 'Nicht authentifiziert'
            ),
            new OAT\Response(
                response: 500,
                description: 'Interner Server Error'
            )
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
     * validate active im response body
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
        summary: 'Aktualisiert eine Kategorie',
        requestBody: new OAT\RequestBody(
            description: 'Mindestens eines der Felder `active` oder `name` ist erforderlich.',
            required: true,
            content: new OAT\JsonContent(
                properties: [
                    new OAT\Property(
                        property: 'name',
                        type: 'string',
                        example: 'Backwaren'
                    ),
                    new OAT\Property(
                        property: 'active',
                        type: 'boolean',
                        example: true
                    )
                ]
            )
        ),
        tags: ['Kategorien'],
        parameters: [
            new OAT\Parameter(
                name: 'category_id',
                description: 'ID der Kategorie',
                in: 'path',
                required: true,
                schema: new OAT\Schema(
                    type: 'integer',
                    example: 1
                )
            )
        ],
        responses: [
            new OAT\Response(
                response: 200,
                description: 'Kategorie aktualisiert',
            ),
            new OAT\Response(
                response: 400,
                description: 'Ungültige Anfrage'
            ),
            new OAT\Response(
                response: 401,
                description: 'Nicht authentifiziert'
            ),
            new OAT\Response(
                response: 404,
                description: 'Kategorie nicht gefunden'
            ),
            new OAT\Response(
                response: 422,
                description: 'Validierung fehlgeschlagen'
            ),
            new OAT\Response(
                response: 500,
                description: 'Interner Server Error'
            )
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
        summary: 'Löscht eine Kategorie',
        tags: ['Kategorien'],
        parameters: [
            new OAT\Parameter(
                name: 'category_id',
                description: 'ID der Kategorie',
                in: 'path',
                required: true,
                schema: new OAT\Schema(
                    type: 'integer',
                    example: 1
                )
            )
        ],
        responses: [
            new OAT\Response(
                response: 204,
                description: 'Kategorie gelöscht',
            ),
            new OAT\Response(
                response: 400,
                description: 'Ungültige Anfrage'
            ),
            new OAT\Response(
                response: 401,
                description: 'Nicht authentifiziert'
            ),
            new OAT\Response(
                response: 404,
                description: 'Kategorie nicht gefunden'
            ),
            new OAT\Response(
                response: 500,
                description: 'Interner Server Error'
            )
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
                return helper::error($response, code: 404);
            } else {
                return helper::success($response, code: 204);
            }
        } catch (Exception $e) {
            return helper::error($response, $e->getMessage(), 500);
        }
    }
}
