<?php
declare(strict_types=1);

namespace repositories;

use Exception;
use PDO;
use PDOException;

class ProductRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /**
     * @throws Exception
     */
    public function getAllProducts(): array
    {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM product");
            $stmt->execute();

            $data = [];

            foreach ($stmt->fetchAll() as $product) {
                $data[] = [
                    'active' => $product['active'],
                    'id_category' => $product['id_category'],
                    'name' => $product['name'],
                    'image' => $product['image'] ?? 'No image',
                    'description' => $product['description'] ?? 'No Description',
                    'price' => $product['price'],
                    'stock' => $product['stock'],
                ];
            }

            return $data;
        } catch (PDOException $e) {
            throw new Exception("Could not get products table.");
        }
    }

    public function getProduct(string $sku): array
    {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM product WHERE sku = :sku");
            $stmt->execute(['sku' => $sku]);

            // if nothing founds, return not false, instead an empty array.
            return $stmt->fetch() ?: [];

        } catch (PDOException $e) {
            throw new Exception("Could not get product with sku: {$sku}.");
        }
    }

    /**
     * @throws Exception
     */
    public function upsertProduct(string $sku, array $valueToUpdate): array
    {
        try {
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

            return [
                'product' => $product,
                'wasCreated' => $wasCreated,
            ];
        } catch (PDOException $e) {
            throw new Exception("Could not upsert product with sku: {$sku}.");
        }
    }

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