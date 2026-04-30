<?php

namespace App\DTO;

class CardDTO
{
    public function __construct(
        public int $id,
        public string $nombreEn,
        public string $nombreEs,
        public ?string $tipo,
        public ?string $mana,
        public ?int $cmc
    ) {
    }
}