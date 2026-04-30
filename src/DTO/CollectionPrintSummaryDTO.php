<?php

namespace App\DTO;

class CollectionPrintSummaryDTO
{
    public function __construct(
        public int $impresionId,
        public string $nombreCarta,
        public string $nombreEdicion,
        public string $codigoEdicion,
        public ?string $numeroColeccion,
        public string $rareza,
        public int $cantidadCopias,
        public ?string $imagenSmall,
        public ?string $imagenNormal,
        public ?string $scryfallUri
    ) {
    }
}