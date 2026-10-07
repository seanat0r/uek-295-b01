<?php
declare(strict_types=1);

use helpers\response as helper;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use repositories\ProductRepository;

readonly class ProductController
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
            $sku = $args['sku'] ?? '';
            if ($sku === null || $sku === '') {
                helper::error($response, "Product sku can't be empty.");
            }

            $data = $this->productRepository->getProduct($sku);

            if ($data === []) {
                return helper::success($response);
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
        $sku = trim($sku);
        if ($sku === null || $sku === '') {
            helper::error($response, "Product sku can't be empty.");
        }


        $requestBody = $request->getParsedBody();
        if (!is_array($requestBody)) {
            return helper::error($response, 'Invalid request body.');
        }

        // mandatory fields
        $name = $requestBody['name'] ?? null;
        if (!is_string($name) || trim($name) === '') {
            return helper::error($response, 'Name is mandatory', 422);
        }

        $stock = $requestBody['stock'] ?? null;
        if (!is_int($stock) && !is_string($stock)) {
            return helper::error($response, 'Stock must be a non-negative integer.', 422);
        }
        $stock = filter_var($stock, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        if ($stock === false) {
            return helper::error($response, 'Stock must be a non-negative integer.', 422);
        }

        $price = $requestBody['price'] ?? null;
        if ((!is_int($price) && !is_float($price) && !is_string($price))
            || !is_numeric($price) || !is_finite((float)$price) || (float)$price <= 0) {
            return helper::error($response, 'Price must be a number greater than zero.', 422);
        }

        // optional category
        $idCategory = $requestBody['idCategory'] ?? null;
        if ($idCategory !== null) {
            $idCategory = filter_var($idCategory, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($idCategory === false) {
                return helper::error($response, 'Category ID must be a positive integer or null.', 422);
            }
        }

        $valueToUpdate = [
            'name' => trim($name),
            'idCategory' => $idCategory,
            'stock' => $stock,
            'price' => number_format((float)$price, 2, '.', ''),
        ];

        // optional strings
        foreach (['image', 'description'] as $field) {
            $value = $requestBody[$field] ?? null;
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
        } catch (Exception $e) {
            return helper::error($response, $e->getMessage(), 500);
        }
    }

    public function deleteProduct(Request $request, Response $response, array $args): Response
    {
        try {
            $sku = $args['sku'] ?? '';
            if ($sku === null || $sku === '') {
                helper::error($response, "Product sku can't be empty.");
            }

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
