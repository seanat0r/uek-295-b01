<?php
declare(strict_types=1);

use database\database;
use middleware\AuthMiddleware;
use Psr\Http\Message\ResponseInterface;
use repositories\CategoryRepository;
use repositories\ProductRepository;
use Slim\Factory\AppFactory;
use Slim\Routing\RouteCollectorProxy;

require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/helpers/response.php';
require_once __DIR__ . '/../database/database.php';
require_once __DIR__ . '/../src/middleware/AuthMiddleware.php';
require_once __DIR__ . '/../src/repositories/CategoryRepository.php';
require_once __DIR__ . '/../src/repositories/ProductRepository.php';
require_once __DIR__ . '/../src/controllers/CategoryController.php';
require_once __DIR__ . '/../src/controllers/ProductController.php';

/**
 * ENV data
 */
$config = json_decode(file_get_contents(__DIR__ . '/../config/config.json'), true);

/**
 * DB connection
 */
$database = database::getInstance($config);

if ($database instanceof ResponseInterface) {
    return $database;
}

/**
 * Authentication Middleware
 */
$authentication = new AuthMiddleware($config);

/**
 * Product Repository
 */
$productRepository = new ProductRepository($database);
/**
 * Category Repository
 */
$categoryRepository = new CategoryRepository($database);

/**
 * Product Controller
 */
$productController = new ProductController($productRepository);

/**
 * Category Controller
 */
$categoryController = new CategoryController($categoryRepository);


$app = AppFactory::create();
$app->addBodyParsingMiddleware();

$app->setBasePath('/api/v1');


// AUTHENTICATION ENDPOINTS
$app->post('/authenticate', [$authentication, 'authenticate']);

// PRODUCTS ENDPOINTS
$app->group('/products', function (RouteCollectorProxy $group) use ($productController) {
    $group->get('', [$productController, "getProducts"]);
})->addMiddleware($authentication);

$app->group('/product', function (RouteCollectorProxy $group) use ($productController) {
    $group->get('/{sku}', [$productController, "getProduct"]);
    $group->put('/{sku}', [$productController, "putProduct"]);
    $group->delete('/{sku}', [$productController, "deleteProduct"]);
})->addMiddleware($authentication);


// CATEGORIES ENDPOINTS
$app->group('/categories', function (RouteCollectorProxy $group) use ($categoryController) {
    $group->get('', [$categoryController, "getCategories"]);
})->addMiddleware($authentication);

$app->group('/category', function (RouteCollectorProxy $group) use ($categoryController) {
    $group->get('/{id}', [$categoryController, "getCategory"]);
    $group->post('', [$categoryController, "postCategory"]);
    $group->patch('/{id}', [$categoryController, "patchCategory"]);
    $group->delete('/{id}', [$categoryController, "deleteCategory"]);
})->addMiddleware($authentication);


$app->run();