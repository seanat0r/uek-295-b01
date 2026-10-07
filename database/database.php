<?php
declare(strict_types=1);

namespace database;

use PDO;
use PDOException;
use Psr\Http\Message\ResponseInterface;
use Slim\Psr7\Factory\ResponseFactory;

class database
{

    private static ?PDO $instance = null;

    private function __construct()
    {
    }

    public static function getInstance(array $config): PDO|ResponseInterface
    {
        if (self::$instance !== null) {
            return self::$instance;
        }

        $servername = $config['db_servername'] ?? null;
        $username = $config['db_username'] ?? null;
        $password = $config['db_password'] ?? null;
        $dbname = $config['db_dbname'] ?? null;

        try {

            // Create the connection with exceptions and associative fetch results
            self::$instance = new PDO(
                "mysql:host=$servername;dbname=$dbname;charset=utf8mb4",
                $username,
                $password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );

            return self::$instance;


        } catch (PDOException $e) {
            $responseFactory = new ResponseFactory();
            $response = $responseFactory->createResponse(500);
            $response->getBody()->write(json_encode(["error" => "DB connection error", "code" => 500]));

            return $response
                ->withHeader('Content-Type', 'application/json');
        }
    }
}