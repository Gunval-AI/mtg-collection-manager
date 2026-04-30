<?php

namespace App\Repositories;

use App\DTO\CollectionDTO;
use App\DTO\CollectionPrintSummaryDTO;
use PDO;

class CollectionRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function findByUser(int $userId): array
    {
        $sql = "
            SELECT
                id_coleccion,
                usuario_id,
                nombre,
                descripcion,
                fecha_creacion,
                es_principal
            FROM colecciones
            WHERE usuario_id = :user_id
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['user_id' => $userId]);

        $rows = $stmt->fetchAll();

        $collections = [];

        foreach ($rows as $row) {
            $collections[] = $this->mapToCollectionDTO($row);
        }

        return $collections;
    }

    public function findById(int $id): ?CollectionDTO
    {
        $sql = "
            SELECT
                id_coleccion,
                usuario_id,
                nombre,
                descripcion,
                fecha_creacion,
                es_principal
            FROM colecciones
            WHERE id_coleccion = :id
            LIMIT 1
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id]);

        $row = $stmt->fetch();

        if (!$row) {
            return null;
        }

        return $this->mapToCollectionDTO($row);
    }

    public function create(int $userId, string $nombre, ?string $descripcion): int
    {
        $sql = "
            INSERT INTO colecciones (usuario_id, nombre, descripcion, es_principal)
            VALUES (:user_id, :nombre, :descripcion, 0)
        ";

        $stmt = $this->pdo->prepare($sql);

        $stmt->execute([
            'user_id' => $userId,
            'nombre' => $nombre,
            'descripcion' => $descripcion
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function createPrincipal(int $userId): int
    {
        $sql = "
            INSERT INTO colecciones (usuario_id, nombre, descripcion, es_principal)
            VALUES (:usuario_id, :nombre, :descripcion, 1)
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'usuario_id' => $userId,
            'nombre' => 'Principal',
            'descripcion' => 'Colección principal del usuario'
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function deleteById(int $id): bool
    {
        $sql = "DELETE FROM colecciones WHERE id_coleccion = :id";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id]);

        return $stmt->rowCount() > 0;
    }

    public function getSummaryById(int $collectionId): array
    {
        $sql = "
            SELECT
                c.impresion_id,
                ca.nombre_en AS nombre_carta,
                e.nombre AS nombre_edicion,
                e.codigo AS codigo_edicion,
                i.numero_coleccion,
                r.nombre AS rareza,
                COUNT(*) AS cantidad_copias,
                i.imagen_small,
                i.imagen_normal,
                i.scryfall_uri
            FROM copias c
            INNER JOIN impresiones i ON c.impresion_id = i.id_impresion
            INNER JOIN cartas ca ON i.carta_id = ca.id_carta
            INNER JOIN ediciones e ON i.edicion_id = e.id_edicion
            INNER JOIN rarezas r ON i.rareza_id = r.id_rareza
            WHERE c.coleccion_id = :collection_id
            GROUP BY
                c.impresion_id,
                ca.nombre_en,
                e.nombre,
                e.codigo,
                i.numero_coleccion,
                r.nombre,
                i.imagen_small,
                i.imagen_normal,
                i.scryfall_uri
            ORDER BY
                ca.nombre_en ASC,
                e.fecha_lanzamiento DESC,
                i.numero_coleccion ASC
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'collection_id' => $collectionId
        ]);

        $rows = $stmt->fetchAll();

        $summary = [];

        foreach ($rows as $row) {
            $summary[] = new CollectionPrintSummaryDTO(
                (int) $row['impresion_id'],
                $row['nombre_carta'],
                $row['nombre_edicion'],
                $row['codigo_edicion'],
                $row['numero_coleccion'],
                $row['rareza'],
                (int) $row['cantidad_copias'],
                $row['imagen_small'],
                $row['imagen_normal'],
                $row['scryfall_uri']
            );
        }

        return $summary;
    }

    public function findPrincipalByUserId(int $userId): ?CollectionDTO
    {
        $sql = "
            SELECT
                id_coleccion,
                usuario_id,
                nombre,
                descripcion,
                fecha_creacion,
                es_principal
            FROM colecciones
            WHERE usuario_id = :user_id
            AND es_principal = 1
            LIMIT 1
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'user_id' => $userId
        ]);

        $row = $stmt->fetch();

        if (!$row) {
            return null;
        }

        return $this->mapToCollectionDTO($row);
    }

    private function mapToCollectionDTO(array $row): CollectionDTO
    {
        return new CollectionDTO(
            (int) $row['id_coleccion'],
            (int) $row['usuario_id'],
            $row['nombre'],
            $row['descripcion'],
            $row['fecha_creacion'],
            (bool) $row['es_principal']
        );
    }
}