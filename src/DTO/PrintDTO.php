<?php

namespace App\DTO;

class PrintDTO
{
    public function __construct(
        public int $id,
        public string $nombreEdicion,
        public string $codigoEdicion,
        public ?string $numeroColeccion,
        public string $rareza
    ) {
    }
}