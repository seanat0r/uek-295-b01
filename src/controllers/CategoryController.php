<?php
declare(strict_types=1);

use repositories\CategoryRepository;

class CategoryController
{
    public function __construct(
        private CategoryRepository $categoryRepository,
    )
    {
    }
}
