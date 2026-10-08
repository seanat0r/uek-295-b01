<?php
declare(strict_types=1);

use helpers\response as helper;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use repositories\CategoryRepository;

class CategoryController
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

    public function patchCategory(Request $request, Response $response, array $args): Response
    {
        // args
        $id = $args['id'] ?? null;

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
            $active = (int) $rawActive['value'];
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
            $data = $this->categoryRepository->updateProduct($id, $valueToUpdate);
            if ($data === []) {
                return helper::error($response, "Category not found.", code: 404);
            }
            return helper::success($response, $data);

        } catch (Exception $e) {
            return helper::error($response, $e->getMessage(), 500);
        }
    }

    public function deleteCategory(Request $request, Response $response, array $args): Response
    {
        try {
            $id = $args['id'] ?? null;

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
