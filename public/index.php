<?php
declare(strict_types=1);

use database\database;
use middleware\AuthMiddleware;
use Psr\Http\Message\ResponseInterface;
use repositories\CategoryRepository;
use repositories\ProductRepository;
use Slim\Factory\AppFactory;

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
$authentication = new AuthMiddleware();

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

$app->get('/products', [$productController, "getProducts"]);
$app->get('/product/{sku}', [$productController, "getProduct"]);
$app->put('/product/{sku}', [$productController, "putProduct"]);
$app->delete('/product/{sku}', [$productController, "deleteProduct"]);

$app->get('/categories', [$categoryController, "getCategories"]);
$app->get('/category/{id}', [$categoryController, "getCategory"]);
$app->post('/category', [$categoryController, "postCategory"]);
$app->patch('/category/{id}', [$categoryController, "patchCategory"]);
$app->delete('/category/{id}', [$categoryController, "deleteCategory"]);

$app->run();