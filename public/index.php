<?php
declare(strict_types=1);

use database\database;
use middleware\AuthMiddleware;
use Psr\Http\Message\ResponseInterface;
use repositories\CategoryRepository;
use repositories\ProductRepository;
use Slim\Factory\AppFactory;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\ResponseEmitter;
use Slim\Routing\RouteCollectorProxy;

require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/helpers/response.php';
require_once __DIR__ . '/../database/database.php';
require_once __DIR__ . '/../src/middleware/AuthMiddleware.php';
require_once __DIR__ . '/../src/repositories/CategoryRepository.php';
require_once __DIR__ . '/../src/repositories/ProductRepository.php';
require_once __DIR__ . '/../src/controllers/CategoryController.php';
require_once __DIR__ . '/../src/controllers/ProductController.php';
require_once __DIR__ . '/../src/controllers/AuthController.php';
require_once __DIR__ . '/../src/controllers/ApiGenerall.php';

// Slim initalising

/**
 * Slim 4 AppFactory
 */
$app = AppFactory::create();
$app->addBodyParsingMiddleware();

$app->setBasePath('/api/v1');

/**
 * ENV data path
 */
const CONFIG_DIR = __DIR__ . '/../config/config.json';

// Checks if config/ env file exits and is readble
if (!file_exists(CONFIG_DIR) || !is_readable(CONFIG_DIR)) {
    $responseFactory = new ResponseFactory();
    $response = $responseFactory->createResponse(500);
    $response->getBody()->write(json_encode(["error" => "ENV/ Config does not exists or is not readable.", "code" => 500]));

    $response = $response->withHeader('Content-Type', 'application/json');
    (new ResponseEmitter())->emit($response);
    exit;
}

/**
 * ENV data
 */
$config = json_decode(file_get_contents(CONFIG_DIR), true);

// $config doesn't have a json error and is an array
if (json_last_error() !== JSON_ERROR_NONE || !is_array($config)) {
    $responseFactory = new ResponseFactory();
    $response = $responseFactory->createResponse(500);
    $response->getBody()->write(json_encode(["error" => "ENV/ CONFIG not readable ", "code" => 500]));

    $response = $response->withHeader('Content-Type', 'application/json');
    (new ResponseEmitter())->emit($response);
    exit;
}

// does every mandatory field is set and is a string
if (!isset(
        $config['db_servername'],
        $config['db_username'],
        $config['db_password'],
        $config['db_dbname'],
        $config['auth_username'],
        $config['auth_password']
    ) ||
    !is_string($config['db_servername']) ||
    !is_string($config['db_username']) ||
    !is_string($config['db_password']) ||
    !is_string($config['db_dbname']) ||
    !is_string($config['auth_username']) ||
    !is_string($config['auth_password'])
) {
    $responseFactory = new ResponseFactory();
    $response = $responseFactory->createResponse(500);
    $response->getBody()->write(json_encode(["error" => "Not all required Field are set", "code" => 500]));

    $response = $response->withHeader('Content-Type', 'application/json');
    (new ResponseEmitter())->emit($response);
    exit;
}
/**
 * DB connection
 */
$database = database::getInstance($config);

if ($database instanceof ResponseInterface) {
    (new ResponseEmitter())->emit($database);
    exit;
}

/**
 * Authentication Middleware
 */
$authentication = new AuthMiddleware($config);

/**
 * Authentication Controller
 */
$authenticationController = new AuthController($config);

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

// AUTHENTICATION ENDPOINTS
$app->post('/authenticate', [$authenticationController, 'authenticate']);

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
    $group->get('/{category_id}', [$categoryController, "getCategory"]);
    $group->post('', [$categoryController, "postCategory"]);
    $group->patch('/{category_id}', [$categoryController, "patchCategory"]);
    $group->delete('/{category_id}', [$categoryController, "deleteCategory"]);
})->addMiddleware($authentication);

// ALL OTHER ENDPOINTS THAT NOT IMPLEMENTED; 405
$app->any('{route:.*}', [ApiGeneral::class, 'index']);

$app->run();