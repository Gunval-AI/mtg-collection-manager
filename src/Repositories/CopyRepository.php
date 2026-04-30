<?php

namespace App\Repositories;

use App\DTO\CopyDTO;
use PDO;

class CopyRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function findByCollectionId(int $collectionId): array
    {
        $sql = $this->copySelectSql() . "
            WHERE c.coleccion_id = :collection_id
            ORDER BY c.fecha_creacion DESC
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'collection_id' => $collectionId
        ]);

        $rows = $stmt->fetchAll();
        $copies = [];

        foreach ($rows as $row) {
            $copies[] = $this->mapRowToCopyDTO($row);
        }

        return $copies;
    }

    public function impresionExists(int $impresionId): bool
    {
        $sql = "SELECT 1 FROM impresiones WHERE id_impresion = :id LIMIT 1";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $impresionId]);

        return (bool) $stmt->fetchColumn();
    }

    public function condicionExists(int $condicionId): bool
    {
        $sql = "SELECT 1 FROM condiciones WHERE id_condicion = :id LIMIT 1";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $condicionId]);

        return (bool) $stmt->fetchColumn();
    }

    public function create(
        int $collectionId,
        int $impresionId,
        int $condicionId,
        string $idioma,
        bool $esFoil
    ): int {
        $sql = "
            INSERT INTO copias (coleccion_id, impresion_id, condicion_id, idioma, es_foil)
            VALUES (:collection_id, :impresion_id, :condicion_id, :idioma, :es_foil)
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'collection_id' => $collectionId,
            'impresion_id' => $impresionId,
            'condicion_id' => $condicionId,
            'idioma' => $idioma,
            'es_foil' => $esFoil ? 1 : 0
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function findById(int $id): ?CopyDTO
    {
        $sql = $this->copySelectSql() . "
            WHERE c.id_copia = :id
            LIMIT 1
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id]);

        $row = $stmt->fetch();

        if (!$row) {
            return null;
        }

        return $this->mapRowToCopyDTO($row);
    }

    public function deleteById(int $id): bool
    {
        $sql = "DELETE FROM copias WHERE id_copia = :id";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id]);

        return $stmt->rowCount() > 0;
    }

    public function findByCollectionIdAndPrintId(int $collectionId, int $printId): array
    {
        $sql = $this->copySelectSql() . "
            WHERE c.coleccion_id = :collection_id
              AND c.impresion_id = :print_id
            ORDER BY c.fecha_creacion DESC
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'collection_id' => $collectionId,
            'print_id' => $printId
        ]);

        $rows = $stmt->fetchAll();
        $copies = [];

        foreach ($rows as $row) {
            $copies[] = $this->mapRowToCopyDTO($row);
        }

        return $copies;
    }

    public function update(int $id, int $condicionId, string $idioma, bool $esFoil): void
    {
        $sql = "
            UPDATE copias
            SET condicion_id = :condicion_id,
                idioma = :idioma,
                es_foil = :es_foil
            WHERE id_copia = :id
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'id' => $id,
            'condicion_id' => $condicionId,
            'idioma' => $idioma,
            'es_foil' => $esFoil ? 1 : 0
        ]);
    }

    public function moveToCollection(int $copyId, int $targetCollectionId): void
    {
        $sql = "
            UPDATE copias
            SET coleccion_id = :target_collection_id
            WHERE id_copia = :copy_id
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'copy_id' => $copyId,
            'target_collection_id' => $targetCollectionId
        ]);
    }

    public function deleteByCollectionId(int $collectionId): void
    {
        $sql = "DELETE FROM copias WHERE coleccion_id = :collection_id";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'collection_id' => $collectionId
        ]);
    }

    public function moveByCollectionId(int $sourceCollectionId, int $targetCollectionId): void
    {
        $sql = "
            UPDATE copias
            SET coleccion_id = :target_collection_id
            WHERE coleccion_id = :source_collection_id
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'source_collection_id' => $sourceCollectionId,
            'target_collection_id' => $targetCollectionId
        ]);
    }

    private function copySelectSql(): string
    {
        return "
            SELECT
                c.id_copia,
                c.coleccion_id,
                c.impresion_id,
                ca.nombre_en AS nombre_carta,
                e.nombre AS nombre_edicion,
                e.codigo AS codigo_edicion,
                i.numero_coleccion,
                c.idioma,
                c.es_foil,
                r.nombre AS rareza,
                c.condicion_id,
                co.descripcion AS condicion,
                c.fecha_creacion,
                i.imagen_small,
                i.imagen_normal,
                i.scryfall_uri
            FROM copias c
            INNER JOIN impresiones i ON c.impresion_id = i.id_impresion
            INNER JOIN cartas ca ON i.carta_id = ca.id_carta
            INNER JOIN ediciones e ON i.edicion_id = e.id_edicion
            INNER JOIN rarezas r ON i.rareza_id = r.id_rareza
            INNER JOIN condiciones co ON c.condicion_id = co.id_condicion
        ";
    }

    private function mapRowToCopyDTO(array $row): CopyDTO
    {
        return new CopyDTO(
            (int) $row['id_copia'],
            (int) $row['coleccion_id'],
            (int) $row['impresion_id'],
            $row['nombre_carta'],
            $row['nombre_edicion'],
            $row['codigo_edicion'],
            $row['numero_coleccion'],
            $row['idioma'],
            (bool) $row['es_foil'],
            $row['rareza'],
            (int) $row['condicion_id'],
            $row['condicion'],
            $row['fecha_creacion'],
            $row['imagen_small'],
            $row['imagen_normal'],
            $row['scryfall_uri']
        );
    }
}