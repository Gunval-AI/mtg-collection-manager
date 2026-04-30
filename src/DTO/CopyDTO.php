<?php

namespace App\DTO;

class CopyDTO
{
    public function __construct(
        public int $id,
        public int $coleccionId,
        public int $impresionId,
        public string $nombreCarta,
        public string $nombreEdicion,
        public string $codigoEdicion,
        public ?string $numeroColeccion,
        public string $idioma,
        public bool $esFoil,
        public string $rareza,
        public int $condicionId,
        public string $condicion,
        public string $fechaCreacion,
        public ?string $imagenSmall,
        public ?string $imagenNormal,
        public ?string $scryfallUri
    ) {
    }
}