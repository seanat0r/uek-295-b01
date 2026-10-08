<?php
declare(strict_types=1);

namespace repositories;

use Exception;
use PDO;
use PDOException;

class CategoryRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function getAllCategories(): array
    {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM category");
            $stmt->execute();

            $data = [];

            foreach ($stmt->fetchAll() as $category) {
                $data[] = [
                    "category_id" => $category["category_id"],
                    "active" => $category["active"],
                    "name" => $category["name"],
                ];
            }

            return $data;
        } catch (PDOException $e) {
            throw new Exception("Could not get category table.");
        }
    }

    public function createCategory(array $valueToCreate)
    {
        try {
            $stmt = $this->pdo->prepare("INSERT INTO category (name, active) VALUES (:name, :active)");
            $stmt->execute([
                'name' => $valueToCreate['name'],
                'active' => $valueToCreate['active']
            ]);

            $lastId = $this->pdo->lastInsertId();
            return $this->getCategory($lastId);

        } catch (PDOException $e) {
            throw new Exception("Could not create category with name: {$valueToCreate['name']}.");
        }
    }

    public function getCategory(string $id): array
    {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM category WHERE category_id = :id");
            $stmt->execute(['id' => $id]);

            // if nothing founds, return not false, instead an empty array.
            return $stmt->fetch() ?: [];
        } catch (PDOException $e) {
            throw new Exception("Could not get category with id: {$id}.");
        }
    }

    public function updateProduct(string $id, array $valueToUpdate): array
    {
        try {
            $stmtUpdate = $this->pdo->prepare(
                "UPDATE category 
                    SET active = COALESCE(:active, active),
                        name = COALESCE(:name, name)
                    WHERE category_id = :id"
            );
            $stmtUpdate->execute([
                'id' => $id,
                'active' => $valueToUpdate['active'] ?? null,
                'name' => $valueToUpdate['name'] ?? null,
            ]);

            $stmtLastId = $this->pdo->prepare("SELECT * FROM category WHERE category_id = :id LIMIT 1");
            $stmtLastId->execute(['id' => $id]);
            return $stmtLastId->fetch();
        } catch (PDOException $e) {
            throw new Exception("Could not update category with id: {$id}.");
        }
    }

    public function deleteCategory(string $id): array
    {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM category WHERE category_id = :id");
            $stmt->execute(['id' => $id]);

            $rowCount = $stmt->rowCount();

            if ($rowCount === 1) {
                return ["gotDeleted" => true];
            } else if ($rowCount === 0) {
                return ["gotDeleted" => false];
            }

            throw new Exception("Internal DB error");
        } catch (PDOException $e) {
            throw new Exception("Could not delete category with id: {$id}.");
        }
    }
}