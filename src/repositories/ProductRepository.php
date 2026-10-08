<?php
declare(strict_types=1);

namespace repositories;

use Exception;
use InvalidArgumentException;
use PDO;
use PDOException;

/**
 * SQL statements for Product
 */
class ProductRepository
{
    /**
     * Constructor
     * @param PDO $pdo db connection
     */
    public function __construct(private PDO $pdo)
    {
    }

    /**
     * Get all products from db
     * @return array <int, string, int, null|int, string, string, string, float, int>
     * @throws Exception db failure
     */
    public function getAllProducts(): array
    {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM product");
            $stmt->execute();

            $data = [];

            foreach ($stmt->fetchAll() as $product) {
                $data[] = [
                    'product_id' => (int)$product['product_id'],
                    'sku' => $product['sku'],
                    'active' => (int)$product['active'],
                    'id_category' => $product['id_category'] === null ? null : (int)$product['id_category'],
                    'name' => $product['name'],
                    'image' => $product['image'],
                    'description' => $product['description'],
                    'price' => (float)$product['price'],
                    'stock' => (int)$product['stock'],
                ];
            }

            return $data;
        } catch (PDOException $e) {
            throw new Exception("Could not get products table.");
        }
    }

    /**
     * get one product
     * @param string $sku
     * @return array returns the product as an assoc array
     * @throws Exception db failure
     */
    public function getProduct(string $sku): array
    {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM product WHERE sku = :sku");
            $stmt->execute(['sku' => $sku]);

            // if nothing founds, return not false, instead an empty array.
            $product = $stmt->fetch();
            if ($product === false) {
                return [];
            }
            $product['price'] = (float)$product['price'];
            return $product;

        } catch (PDOException $e) {
            throw new Exception("Could not get product with sku: {$sku}.");
        }
    }

    /**
     * update or create a new product
     * @param string $sku
     * @param array $valueToUpdate value to update
     * @return array <string, int, int, null|string, null|string, string, string, float, int>
     * @throws Exception db failure
     */
    public function upsertProduct(string $sku, array $valueToUpdate): array
    {
        try {
            // check if category id exists
            if ($valueToUpdate['idCategory'] !== null) {
                $stmtCategory = $this->pdo->prepare('SELECT 1 FROM category WHERE category_id = :id');
                $stmtCategory->execute(['id' => $valueToUpdate['idCategory']]);
                if ($stmtCategory->fetchColumn() === false) {
                    throw new InvalidArgumentException('Category does not exist.');
                }
            }

            $stmtUpsert = $this->pdo->prepare("
            INSERT INTO product (sku, active, id_category, name, image, description, price, stock)
            VALUES (:sku, :active, :id_category, :name, :image, :description, :price, :stock)
            ON DUPLICATE KEY UPDATE
                sku = :skuUpdate,
                active = :activeUpdate,
                id_category = :idCategoryUpdate,
                name = :nameUpdate,
                image = :imageUpdate,
                description = :descriptionUpdate,
                price = :priceUpdate,
                stock = :stockUpdate
                            
            ");
            $stmtUpsert->execute([
                "sku" => $sku,
                "active" => $valueToUpdate["active"],
                "id_category" => $valueToUpdate["idCategory"],
                "name" => $valueToUpdate["name"],
                "image" => $valueToUpdate["image"],
                "description" => $valueToUpdate["description"],
                "price" => $valueToUpdate["price"],
                "stock" => $valueToUpdate["stock"],

                "skuUpdate" => $sku,
                "activeUpdate" => $valueToUpdate["active"],
                "idCategoryUpdate" => $valueToUpdate["idCategory"],
                "nameUpdate" => $valueToUpdate["name"],
                "imageUpdate" => $valueToUpdate["image"],
                "descriptionUpdate" => $valueToUpdate["description"],
                "priceUpdate" => $valueToUpdate["price"],
                "stockUpdate" => $valueToUpdate["stock"],
            ]);

            $wasCreated = ($stmtUpsert->rowCount() === 1);

            $stmtLastSku = $this->pdo->prepare("SELECT * FROM product WHERE sku = :sku LIMIT 1");
            $stmtLastSku->execute(['sku' => $sku]);
            $product = $stmtLastSku->fetch();
            $product['price'] = (float)$product['price'];

            return [
                'product' => $product,
                'wasCreated' => $wasCreated,
            ];
        } catch (PDOException $e) {
            throw new Exception("Could not upsert product with sku: {$sku}.");
        }
    }

    /**
     * delete one product
     * @param string $sku
     * @return false[]|true[] ["gotDeleted" => <true/ false>]
     * @throws Exception db failure
     */
    public function deleteProduct(string $sku): array
    {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM product WHERE sku = :sku");
            $stmt->execute(['sku' => $sku]);

            $rowCount = $stmt->rowCount();

            if ($rowCount === 1) {
                return ["gotDeleted" => true];
            } else if ($rowCount === 0) {
                return ["gotDeleted" => false];
            }

            throw new Exception("Internal DB error");
        } catch (PDOException $e) {
            throw new Exception("Could not delete product with sku: {$sku}.");
        }
    }
}