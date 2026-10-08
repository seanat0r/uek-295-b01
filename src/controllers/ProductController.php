<?php
declare(strict_types=1);

use helpers\response as helper;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use repositories\ProductRepository;

class ProductController
{
    public function __construct(
        private ProductRepository $productRepository,
    )
    {
    }

    public function getProducts(Request $request, Response $response, array $args): Response
    {
        try {
            $data = $this->productRepository->getAllProducts();
            return helper::success($response, $data);

        } catch (Exception $e) {
            return helper::error($response, $e->getMessage(), 500);
        }
    }

    public function getProduct(Request $request, Response $response, array $args): Response
    {
        try {
            $sku = $args['sku'] ?? null;
            if (!is_string($sku) || trim($sku) === '' || mb_strlen($sku, 'UTF-8') > 100) {
                return helper::error($response, 'SKU must contain 1 to 100 characters.', 400);
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
        $stock = filter_var($stock, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => PHP_INT_MAX]]);
        if ($stock === false) {
            return helper::error($response, 'Stock must be an integer between 0 and 2147483647.', 422);
        }

        $price = $requestBody['price'] ?? null;
        if ((!is_int($price) && !is_float($price) && !is_string($price))
            || !is_numeric($price) || !is_finite((float)$price) || (float)$price <= 0) {
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
