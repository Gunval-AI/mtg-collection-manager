<?php

namespace App\Services;

use App\Repositories\CardRepository;
use RuntimeException;

class CardService
{
    public function __construct(private CardRepository $repository)
    {
    }

    public function searchCardsByName(string $name): array
    {
        $name = trim($name);

        if ($name === '') {
            throw new RuntimeException('El nombre de búsqueda es obligatorio.');
        }

        return $this->repository->searchByName($name);
    }
}