<?php
declare(strict_types=1);

namespace repositories;

use Exception;
use PDO;
use PDOException;

/**
 * SQL statements for Category
 */
class CategoryRepository
{
    /**
     * Constructor
     * @param PDO $pdo db connection
     */
    public function __construct(private PDO $pdo)
    {
    }

    /**
     * Get all Categories
     * @return array <string, string, string>
     * @throws Exception db failure
     */
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

    /**
     * create category in the db
     * @param array $valueToCreate <string, string> Value to add
     * @return array return the added category as an assoc array
     * @throws Exception db failure
     */
    public function createCategory(array $valueToCreate): array
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

    /**
     * get one category
     * @param string $id category id
     * @return array return the category as an assoc araay
     * @throws Exception
     */
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

    /**
     * update category
     * @param string $id category id
     * @param array $valueToUpdate <string, string> value to update
     * @return array returns the full updatet category
     * @throws Exception db failure
     */
    public function updateCategory(string $id, array $valueToUpdate): array
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
            $product = $stmtLastId->fetch();
            return $product ?: [];
        } catch (PDOException $e) {
            throw new Exception("Could not update category with id: {$id}.");
        }
    }

    /**
     * delete a category in the db
     * @param string $id category db
     * @return false[]|true[] an assoc array ["gotDeleted" => <true/ false>]
     * @throws Exception db failure
     */
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