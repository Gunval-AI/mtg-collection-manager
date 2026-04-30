<?php

namespace App\Repositories;

use App\DTO\ConditionDTO;
use PDO;

class ConditionRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function findAll(): array
    {
        $sql = "
            SELECT
                id_condicion,
                descripcion
            FROM condiciones
            ORDER BY id_condicion ASC
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();

        $rows = $stmt->fetchAll();

        $conditions = [];

        foreach ($rows as $row) {
            $conditions[] = new ConditionDTO(
                (int) $row['id_condicion'],
                $row['descripcion']
            );
        }

        return $conditions;
    }
}