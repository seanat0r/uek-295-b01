<?php
declare(strict_types=1);

namespace repositories;

use PDO;

class CategoryRepository
{
    public function __construct(private PDO $pdo)
    {
    }
}