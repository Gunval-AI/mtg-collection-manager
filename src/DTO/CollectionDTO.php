<?php

namespace App\DTO;

class CollectionDTO
{
    public function __construct(
        public int $id,
        public int $usuarioId,
        public string $nombre,
        public ?string $descripcion,
        public string $fechaCreacion,
        public bool $esPrincipal
    ) {
    }
}