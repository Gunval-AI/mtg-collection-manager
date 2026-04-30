<?php

declare(strict_types=1);

/**
 * Imports MTG sets from Scryfall into the local catalog.
 *
 * Usage:
 * php scripts/import_scryfall_sets.php
 *
 * Requirements:
 * - PHP with PDO MySQL enabled
 * - Database schema already created
 * - Network access to Scryfall API
 *
 * This script is intended for local development and manual catalog updates.
 */

$dbHost = getenv('DB_HOST') ?: 'mysql';
$dbPort = getenv('DB_PORT') ?: '3306';
$dbName = getenv('DB_DATABASE') ?: 'mtg_collection_manager';
$dbUser = getenv('DB_USERNAME') ?: 'root';
$dbPass = getenv('DB_PASSWORD') ?: 'root';

const SET_CODES = ['TMT', 'TLA', 'ECL'];

$pdo = new PDO(
    sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $dbHost, $dbPort, $dbName),
    $dbUser,
    $dbPass,
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]
);

foreach (SET_CODES as $setCode) {
    importSet($pdo, strtolower($setCode));
    updateSpanishNames($pdo, strtolower($setCode));
}

echo PHP_EOL . "Importación finalizada." . PHP_EOL;

function importSet(PDO $pdo, string $setCode): void
{
    echo PHP_EOL . "Importando set: " . strtoupper($setCode) . PHP_EOL;

    $url = 'https://api.scryfall.com/cards/search?q=e:' . urlencode($setCode) . '&unique=prints&order=set';

    $totalCards = 0;
    $insertedCards = 0;
    $updatedCards = 0;
    $insertedPrints = 0;
    $skippedPrints = 0;

    while ($url !== null) {
        $response = scryfallGet($url);

        if (!isset($response['data']) || !is_array($response['data'])) {
            throw new RuntimeException('Respuesta inválida de Scryfall para set ' . $setCode);
        }

        foreach ($response['data'] as $card) {
            $totalCards++;

            $pdo->beginTransaction();

            try {
                $editionId = upsertEdition($pdo, $card);
                $rarityId = getOrCreateRarity($pdo, $card['rarity'] ?? 'unknown');

                $cardResult = upsertCard($pdo, $card);
                $cardId = $cardResult['id'];

                if ($cardResult['created']) {
                    $insertedCards++;
                } else {
                    $updatedCards++;
                }

                $printCreated = insertPrintIfMissing(
                    $pdo,
                    $card,
                    $cardId,
                    $editionId,
                    $rarityId
                );

                if ($printCreated) {
                    $insertedPrints++;
                } else {
                    $skippedPrints++;
                }

                $pdo->commit();
            } catch (Throwable $e) {
                $pdo->rollBack();
                echo "ERROR con carta " . ($card['name'] ?? 'desconocida') . ': ' . $e->getMessage() . PHP_EOL;
            }
        }

        $url = !empty($response['has_more']) && !empty($response['next_page'])
            ? $response['next_page']
            : null;

        usleep(100000);
    }

    echo "Set " . strtoupper($setCode) . " completado:" . PHP_EOL;
    echo "- Cartas procesadas: {$totalCards}" . PHP_EOL;
    echo "- Cartas nuevas: {$insertedCards}" . PHP_EOL;
    echo "- Cartas actualizadas/existentes: {$updatedCards}" . PHP_EOL;
    echo "- Impresiones nuevas: {$insertedPrints}" . PHP_EOL;
    echo "- Impresiones ya existentes: {$skippedPrints}" . PHP_EOL;
}

function scryfallGet(string $url): array
{
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => [
                'Accept: application/json',
                'User-Agent: MTGCollectionManager/1.0',
            ],
            'timeout' => 30,
        ],
    ]);

    $json = file_get_contents($url, false, $context);

    if ($json === false) {
        throw new RuntimeException('No se pudo consultar Scryfall: ' . $url);
    }

    $data = json_decode($json, true);

    if (!is_array($data)) {
        throw new RuntimeException('JSON inválido recibido desde Scryfall.');
    }

    if (($data['object'] ?? null) === 'error') {
        throw new RuntimeException($data['details'] ?? 'Error desconocido de Scryfall.');
    }

    return $data;
}

function upsertEdition(PDO $pdo, array $card): int
{
    $code = strtoupper($card['set']);
    $name = $card['set_name'] ?? $code;
    $releasedAt = $card['released_at'] ?? null;

    $stmt = $pdo->prepare("
        INSERT INTO ediciones (nombre, codigo, fecha_lanzamiento)
        VALUES (:nombre, :codigo, :fecha_lanzamiento)
        ON DUPLICATE KEY UPDATE
            nombre = VALUES(nombre),
            fecha_lanzamiento = VALUES(fecha_lanzamiento)
    ");

    $stmt->execute([
        ':nombre' => $name,
        ':codigo' => $code,
        ':fecha_lanzamiento' => $releasedAt,
    ]);

    return getIdByUniqueField($pdo, 'ediciones', 'id_edicion', 'codigo', $code);
}

function getOrCreateRarity(PDO $pdo, string $rarity): int
{
    $rarity = ucfirst(strtolower($rarity));

    $stmt = $pdo->prepare("
        INSERT INTO rarezas (nombre)
        VALUES (:nombre)
        ON DUPLICATE KEY UPDATE nombre = nombre
    ");

    $stmt->execute([
        ':nombre' => $rarity,
    ]);

    return getIdByUniqueField($pdo, 'rarezas', 'id_rareza', 'nombre', $rarity);
}

function upsertCard(PDO $pdo, array $card): array
{
    if (empty($card['oracle_id'])) {
        throw new RuntimeException('Carta sin oracle_id: ' . ($card['name'] ?? 'desconocida'));
    }

    $existingId = findIdByUniqueField($pdo, 'cartas', 'id_carta', 'oracle_id', $card['oracle_id']);

    $nameEn = $card['name'] ?? '';
    $nameEs = $card['printed_name'] ?? null;

    $type = $card['type_line'] ?? null;
    $oracleText = $card['oracle_text'] ?? null;
    $mana = $card['mana_cost'] ?? null;
    $cmc = isset($card['cmc']) ? (int)$card['cmc'] : null;

    $power = $card['power'] ?? null;
    $toughness = $card['toughness'] ?? null;
    $loyalty = $card['loyalty'] ?? null;

    if (isset($card['card_faces']) && is_array($card['card_faces'])) {
        $faces = $card['card_faces'];

        $type = $type ?? implode(' // ', array_filter(array_column($faces, 'type_line')));
        $oracleText = implode("\n//\n", array_filter(array_column($faces, 'oracle_text')));
        $mana = implode(' // ', array_filter(array_column($faces, 'mana_cost')));

        $power = $power ?? firstFaceValue($faces, 'power');
        $toughness = $toughness ?? firstFaceValue($faces, 'toughness');
        $loyalty = $loyalty ?? firstFaceValue($faces, 'loyalty');
    }

    $stmt = $pdo->prepare("
        INSERT INTO cartas (
            oracle_id,
            nombre_en,
            nombre_es,
            tipo,
            oracle_texto,
            mana,
            cmc,
            fuerza,
            resistencia,
            lealtad
        )
        VALUES (
            :oracle_id,
            :nombre_en,
            :nombre_es,
            :tipo,
            :oracle_texto,
            :mana,
            :cmc,
            :fuerza,
            :resistencia,
            :lealtad
        )
        ON DUPLICATE KEY UPDATE
            nombre_en = VALUES(nombre_en),
            nombre_es = COALESCE(VALUES(nombre_es), nombre_es),
            tipo = VALUES(tipo),
            oracle_texto = VALUES(oracle_texto),
            mana = VALUES(mana),
            cmc = VALUES(cmc),
            fuerza = VALUES(fuerza),
            resistencia = VALUES(resistencia),
            lealtad = VALUES(lealtad)
    ");

    $stmt->execute([
        ':oracle_id' => $card['oracle_id'],
        ':nombre_en' => $nameEn,
        ':nombre_es' => $nameEs,
        ':tipo' => $type,
        ':oracle_texto' => $oracleText,
        ':mana' => $mana,
        ':cmc' => $cmc,
        ':fuerza' => $power,
        ':resistencia' => $toughness,
        ':lealtad' => $loyalty,
    ]);

    $id = getIdByUniqueField($pdo, 'cartas', 'id_carta', 'oracle_id', $card['oracle_id']);

    return [
        'id' => $id,
        'created' => $existingId === null,
    ];
}

function insertPrintIfMissing(
    PDO $pdo,
    array $card,
    int $cardId,
    int $editionId,
    int $rarityId
): bool {
    if (empty($card['id'])) {
        throw new RuntimeException('Impresión sin scryfall_id.');
    }

    $imageSmall = getCardImageUri($card, 'small');
    $imageNormal = getCardImageUri($card, 'normal');
    $scryfallUri = $card['scryfall_uri'] ?? null;

    $existingId = findIdByUniqueField($pdo, 'impresiones', 'id_impresion', 'scryfall_id', $card['id']);

    if ($existingId !== null) {
        $stmt = $pdo->prepare("
            UPDATE impresiones
            SET 
                carta_id = :carta_id,
                edicion_id = :edicion_id,
                rareza_id = :rareza_id,
                numero_coleccion = :numero_coleccion,
                imagen_small = :imagen_small,
                imagen_normal = :imagen_normal,
                scryfall_uri = :scryfall_uri
            WHERE scryfall_id = :scryfall_id
        ");

        $stmt->execute([
            ':scryfall_id' => $card['id'],
            ':carta_id' => $cardId,
            ':edicion_id' => $editionId,
            ':rareza_id' => $rarityId,
            ':numero_coleccion' => $card['collector_number'] ?? null,
            ':imagen_small' => $imageSmall,
            ':imagen_normal' => $imageNormal,
            ':scryfall_uri' => $scryfallUri,
        ]);

        return false;
    }

    $stmt = $pdo->prepare("
        INSERT INTO impresiones (
            scryfall_id,
            carta_id,
            edicion_id,
            rareza_id,
            numero_coleccion,
            imagen_small,
            imagen_normal,
            scryfall_uri
        )
        VALUES (
            :scryfall_id,
            :carta_id,
            :edicion_id,
            :rareza_id,
            :numero_coleccion,
            :imagen_small,
            :imagen_normal,
            :scryfall_uri
        )
    ");

    $stmt->execute([
        ':scryfall_id' => $card['id'],
        ':carta_id' => $cardId,
        ':edicion_id' => $editionId,
        ':rareza_id' => $rarityId,
        ':numero_coleccion' => $card['collector_number'] ?? null,
        ':imagen_small' => $imageSmall,
        ':imagen_normal' => $imageNormal,
        ':scryfall_uri' => $scryfallUri,
    ]);

    return true;
}

function getIdByUniqueField(
    PDO $pdo,
    string $table,
    string $idColumn,
    string $field,
    string $value
): int {
    $id = findIdByUniqueField($pdo, $table, $idColumn, $field, $value);

    if ($id === null) {
        throw new RuntimeException("No se encontró {$table}.{$field} = {$value}");
    }

    return $id;
}

function findIdByUniqueField(
    PDO $pdo,
    string $table,
    string $idColumn,
    string $field,
    string $value
): ?int {
    $allowedTables = ['cartas', 'ediciones', 'rarezas', 'impresiones'];

    if (!in_array($table, $allowedTables, true)) {
        throw new InvalidArgumentException('Tabla no permitida: ' . $table);
    }

    $sql = "SELECT {$idColumn} FROM {$table} WHERE {$field} = :value LIMIT 1";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':value' => $value,
    ]);

    $row = $stmt->fetch();

    return $row ? (int)$row[$idColumn] : null;
}

function firstFaceValue(array $faces, string $key): ?string
{
    foreach ($faces as $face) {
        if (!empty($face[$key])) {
            return (string)$face[$key];
        }
    }

    return null;
}

function updateSpanishNames(PDO $pdo, string $setCode): void
{
    echo PHP_EOL . "Actualizando nombres ES para set: " . strtoupper($setCode) . PHP_EOL;

    $url = 'https://api.scryfall.com/cards/search?q=e:' . urlencode($setCode) . '+lang:es&unique=prints&order=set';

    $updated = 0;
    $withoutPrintedName = 0;
    $withoutOracleId = 0;

    while ($url !== null) {
        $response = scryfallGet($url);

        if (!isset($response['data']) || !is_array($response['data'])) {
            throw new RuntimeException('Respuesta inválida de Scryfall para nombres ES del set ' . $setCode);
        }

        foreach ($response['data'] as $card) {
            if (empty($card['oracle_id'])) {
                $withoutOracleId++;
                continue;
            }

            if (empty($card['printed_name'])) {
                $withoutPrintedName++;
                continue;
            }

            $stmt = $pdo->prepare("
                UPDATE cartas
                SET nombre_es = :nombre_es
                WHERE oracle_id = :oracle_id
            ");

            $stmt->execute([
                ':nombre_es' => $card['printed_name'],
                ':oracle_id' => $card['oracle_id'],
            ]);

            if ($stmt->rowCount() > 0) {
                $updated++;
            }
        }

        $url = !empty($response['has_more']) && !empty($response['next_page'])
            ? $response['next_page']
            : null;

        usleep(100000);
    }

    echo "Nombres ES actualizados: {$updated}" . PHP_EOL;
    echo "Sin printed_name: {$withoutPrintedName}" . PHP_EOL;
    echo "Sin oracle_id: {$withoutOracleId}" . PHP_EOL;
}

function getCardImageUri(array $card, string $size): ?string
{
    if (!empty($card['image_uris'][$size])) {
        return $card['image_uris'][$size];
    }

    if (!empty($card['card_faces'][0]['image_uris'][$size])) {
        return $card['card_faces'][0]['image_uris'][$size];
    }

    return null;
}