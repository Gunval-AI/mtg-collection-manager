<?php

namespace App\Services;

use App\Repositories\PrintRepository;
use RuntimeException;

class PrintService
{
    public function __construct(private PrintRepository $repository)
    {
    }

    public function getPrintsByCardId(int $cardId): array
    {
        if ($cardId <= 0) {
            throw new RuntimeException('El id de carta no es válido.');
        }

        return $this->repository->findByCardId($cardId);
    }

    public function searchPrintsByCardName(string $name): array
    {
        $name = trim($name);

        if ($name === '') {
            throw new RuntimeException('El nombre de búsqueda es obligatorio.');
        }

        return $this->repository->searchByCardName($name);
    }
}