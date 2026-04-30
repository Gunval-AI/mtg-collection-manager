<?php

namespace App\Repositories;

use App\DTO\PrintDTO;
use PDO;

class PrintRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function findByCardId(int $cardId): array
    {
        $sql = "
            SELECT
                i.id_impresion,
                e.nombre AS nombre_edicion,
                e.codigo AS codigo_edicion,
                i.numero_coleccion,
                r.nombre AS rareza
            FROM impresiones i
            INNER JOIN ediciones e ON i.edicion_id = e.id_edicion
            INNER JOIN rarezas r ON i.rareza_id = r.id_rareza
            WHERE i.carta_id = :card_id
            ORDER BY e.fecha_lanzamiento DESC, i.numero_coleccion ASC
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'card_id' => $cardId
        ]);

        $rows = $stmt->fetchAll();

        $prints = [];

        foreach ($rows as $row) {
            $prints[] = $this->mapToPrintDTO($row);
        }

        return $prints;
    }

    public function findByCardIdAndEditionCode(int $cardId, string $editionCode): array
    {
        $sql = "
            SELECT
                i.id_impresion,
                e.nombre AS nombre_edicion,
                e.codigo AS codigo_edicion,
                i.numero_coleccion,
                r.nombre AS rareza
            FROM impresiones i
            INNER JOIN ediciones e ON i.edicion_id = e.id_edicion
            INNER JOIN rarezas r ON i.rareza_id = r.id_rareza
            WHERE i.carta_id = :card_id
              AND e.codigo = :edition_code
            ORDER BY e.fecha_lanzamiento DESC, i.numero_coleccion ASC
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'card_id' => $cardId,
            'edition_code' => $editionCode
        ]);

        $rows = $stmt->fetchAll();

        $prints = [];

        foreach ($rows as $row) {
            $prints[] = $this->mapToPrintDTO($row);
        }

        return $prints;
    }

    public function searchByCardName(string $name): array
    {
        $sql = "
            SELECT
                i.id_impresion,
                e.nombre AS nombre_edicion,
                e.codigo AS codigo_edicion,
                i.numero_coleccion,
                r.nombre AS rareza,
                ca.nombre_en AS nombre_carta,
                i.imagen_small,
                i.imagen_normal,
                i.scryfall_uri
            FROM impresiones i
            INNER JOIN cartas ca ON i.carta_id = ca.id_carta
            INNER JOIN ediciones e ON i.edicion_id = e.id_edicion
            INNER JOIN rarezas r ON i.rareza_id = r.id_rareza
            WHERE ca.nombre_en LIKE :name_en
               OR ca.nombre_es LIKE :name_es
            ORDER BY ca.nombre_en ASC, e.fecha_lanzamiento DESC, i.numero_coleccion ASC
            LIMIT 50
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'name_en' => '%' . $name . '%',
            'name_es' => '%' . $name . '%'
        ]);

        $rows = $stmt->fetchAll();

        $prints = [];

        foreach ($rows as $row) {
            $prints[] = [
                'id' => (int) $row['id_impresion'],
                'nombreCarta' => $row['nombre_carta'],
                'nombreEdicion' => $row['nombre_edicion'],
                'codigoEdicion' => $row['codigo_edicion'],
                'numeroColeccion' => $row['numero_coleccion'],
                'rareza' => $row['rareza'],
                'imagenSmall' => $row['imagen_small'],
                'imagenNormal' => $row['imagen_normal'],
                'scryfallUri' => $row['scryfall_uri']
            ];
        }

        return $prints;
    }

    public function findBySetCodeAndCollectorNumber(
        string $setCode,
        string $collectorNumber
    ): ?array {
        $collectorNumber = trim($collectorNumber);
        $normalizedCollectorNumber = ltrim($collectorNumber, '0');

        if ($normalizedCollectorNumber === '') {
            $normalizedCollectorNumber = $collectorNumber;
        }

        $sql = "
            SELECT
                i.id_impresion,
                i.carta_id AS id_carta,
                i.numero_coleccion,
                i.imagen_small,
                i.imagen_normal,
                i.scryfall_uri,
                c.nombre_en,
                c.nombre_es,
                e.codigo AS codigo_edicion,
                e.nombre AS nombre_edicion,
                r.nombre AS rareza
            FROM impresiones i
            INNER JOIN cartas c ON c.id_carta = i.carta_id
            INNER JOIN ediciones e ON e.id_edicion = i.edicion_id
            INNER JOIN rarezas r ON r.id_rareza = i.rareza_id
            WHERE UPPER(e.codigo) = :set_code
            AND (
                i.numero_coleccion = :collector_number
                OR i.numero_coleccion = :normalized_collector_number
            )
            LIMIT 1
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'set_code' => strtoupper(trim($setCode)),
            'collector_number' => $collectorNumber,
            'normalized_collector_number' => $normalizedCollectorNumber
        ]);

        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function findRecognitionCandidatesByEditionCode(string $editionCode): array
    {
        $sql = "
            SELECT
                i.id_impresion,
                i.carta_id AS id_carta,
                i.numero_coleccion,
                i.imagen_small,
                i.imagen_normal,
                i.scryfall_uri,
                c.nombre_en,
                c.nombre_es,
                e.codigo AS codigo_edicion,
                e.nombre AS nombre_edicion,
                r.nombre AS rareza
            FROM impresiones i
            INNER JOIN cartas c ON c.id_carta = i.carta_id
            INNER JOIN ediciones e ON e.id_edicion = i.edicion_id
            INNER JOIN rarezas r ON r.id_rareza = i.rareza_id
            WHERE UPPER(e.codigo) = :edition_code
            ORDER BY c.nombre_en ASC
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'edition_code' => strtoupper(trim($editionCode))
        ]);

        return $stmt->fetchAll();
    }

    public function findCardCandidatesByTitle(string $title): array
    {
        $sql = "
            SELECT
                c.id_carta,
                c.nombre_en,
                c.nombre_es
            FROM cartas c
            WHERE c.nombre_en IS NOT NULL
            OR c.nombre_es IS NOT NULL
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function findFullPrintsByCardId(int $cardId): array
    {
        $sql = "
            SELECT
                i.id_impresion,
                i.carta_id AS id_carta,
                i.numero_coleccion,
                i.imagen_small,
                i.imagen_normal,
                i.scryfall_uri,
                c.nombre_en,
                c.nombre_es,
                e.codigo AS codigo_edicion,
                e.nombre AS nombre_edicion,
                r.nombre AS rareza
            FROM impresiones i
            INNER JOIN cartas c ON c.id_carta = i.carta_id
            INNER JOIN ediciones e ON e.id_edicion = i.edicion_id
            INNER JOIN rarezas r ON r.id_rareza = i.rareza_id
            WHERE i.carta_id = :card_id
            ORDER BY e.fecha_lanzamiento DESC, i.numero_coleccion ASC
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'card_id' => $cardId
        ]);

        return $stmt->fetchAll();
    }

    private function mapToPrintDTO(array $row): PrintDTO
    {
        return new PrintDTO(
            (int) $row['id_impresion'],
            $row['nombre_edicion'],
            $row['codigo_edicion'],
            $row['numero_coleccion'],
            $row['rareza']
        );
    }
}