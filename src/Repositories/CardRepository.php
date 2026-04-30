<?php

namespace App\Repositories;

use App\DTO\CardDTO;
use PDO;

class CardRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function searchByName(string $name): array
    {
        $sql = "
            SELECT
                id_carta,
                nombre_en,
                nombre_es,
                tipo,
                mana,
                cmc
            FROM cartas
            WHERE nombre_en LIKE :name_en
               OR nombre_es LIKE :name_es
            ORDER BY nombre_en ASC
            LIMIT 20
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'name_en' => '%' . $name . '%',
            'name_es' => '%' . $name . '%'
        ]);

        $rows = $stmt->fetchAll();

        $cards = [];

        foreach ($rows as $row) {
            $cards[] = $this->mapToCardDTO($row);
        }

        return $cards;
    }

    public function findByExactName(string $name): ?CardDTO
    {
        $sql = "
            SELECT
                id_carta,
                nombre_en,
                nombre_es,
                tipo,
                mana,
                cmc
            FROM cartas
            WHERE nombre_en = :name_en
               OR nombre_es = :name_es
            LIMIT 1
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'name_en' => $name,
            'name_es' => $name
        ]);

        $row = $stmt->fetch();

        if (!$row) {
            return null;
        }

        return $this->mapToCardDTO($row);
    }

    private function mapToCardDTO(array $row): CardDTO
    {
        return new CardDTO(
            (int) $row['id_carta'],
            $row['nombre_en'],
            $row['nombre_es'],
            $row['tipo'],
            $row['mana'],
            $row['cmc'] !== null ? (int) $row['cmc'] : null
        );
    }
}