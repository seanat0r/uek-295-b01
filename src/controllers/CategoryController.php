<?php
declare(strict_types=1);

use helpers\response as helper;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use repositories\CategoryRepository;

readonly class CategoryController
{
    public function __construct(
        private CategoryRepository $categoryRepository,
    )
    {
    }

    public function getCategories(Request $request, Response $response, array $args): Response
    {
        try {
            $data = $this->categoryRepository->getAllCategories();
            return helper::success($response, $data);

        } catch (Exception $e) {
            return helper::error($response, $e->getMessage(), 500);
        }
    }

    public function getCategory(Request $request, Response $response, array $args): Response
    {
        try {
            $id = $args['id'] ?? null;

            if ($id === null || trim($id) === "") {
                return helper::error($response, "Category ID is required");
            }

            $data = $this->categoryRepository->getCategory($id);

            // Nothing was found.
            if ($data === []) {
                return helper::success($response);
            }

            return helper::success($response, $data);
        } catch (Exception $e) {
            return helper::error($response, $e->getMessage(), 500);
        }
    }

    public function postCategory(Request $request, Response $response, array $args): Response
    {
        // request body
        $requestBody = $request->getParsedBody();
        if (!is_array($requestBody)) {
            return helper::error($response, 'Invalid request body.');
        }

        // validation
        $rawActive = $requestBody['active'] ?? 'null';
        $rawActive = $this->validateActive($rawActive);
        if ($rawActive['isError'] || $rawActive['value'] === null) {
            return helper::error($response, 'Active must be true, false, 1 or 0.');
        }
        $active = $rawActive['value'];

        $name = $requestBody['name'] ?? null;
        if ($name === null || trim($name) === "") {
            return helper::error($response, 'Category name is required.');
        }

        if (strlen($name) > 500) {
            return helper::error($response, 'Category name is too long.');
        }

        $valueToCreate = [
            'name' => $name,
            'active' => $active,
        ];

        try {
            $data = $this->categoryRepository->createCategory($valueToCreate);
            return helper::success($response, $data, code: 201);
        } catch (Exception $e) {
            return helper::error($response, $e->getMessage(), 500);
        }
    }

    private function validateActive(string|int|bool $value): array
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

    public function patchCategory(Request $request, Response $response, array $args): Response
    {
        // args
        $id = $args['id'] ?? null;

        if ($id === null || trim($id) === "") {
            return helper::error($response, "Category ID is required");
        }

        $valueToUpdate = [];

        // request Body
        $requestBody = $request->getParsedBody();
        if (!is_array($requestBody)) {
            return helper::error($response, 'Invalid request body.');
        }

        //validate
        $active = null;
        $rawActive = $requestBody['active'] ?? 'not set';

        if ($rawActive !== 'not set') {
            $rawActive = $this->validateActive($rawActive);
            if ($rawActive['isError'] || $rawActive['value'] === null) {
                return helper::error($response, 'Active must be true, false, 1 or 0.');
            }
            if ($rawActive['value'] === true) {
                $active = '1';
            } else {
                $active = '0';
            }
        }

        $name = $requestBody['name'] ?? 'not set';

        if ($name !== 'not set') {
            if ($name === null || trim($name) === "") {
                return helper::error($response, 'Category name is required.');
            }

            if (strlen($name) > 500) {
                return helper::error($response, 'Category name is too long.');
            }
        } else {
            $name = null;
        }

        $valueToUpdate = [
            'name' => $name,
            'active' => $active,
        ];

        if ($valueToUpdate['name'] === null && $valueToUpdate['active'] === null) {
            return helper::error($response, 'To update anything, one field is required.', 422);
        }

        try {
            $data = $this->categoryRepository->updateProduct($id, $valueToUpdate);
            return helper::success($response, $data);

        } catch (Exception $e) {
            return helper::error($response, $e->getMessage(), 500);
        }
    }

    public function deleteCategory(Request $request, Response $response, array $args): Response
    {
        try {
            $id = $args['id'] ?? null;

            if ($id === null || trim($id) === "") {
                return helper::error($response, "Category ID is required");
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
