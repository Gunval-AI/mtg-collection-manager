<?php

namespace App\DTO;

class ConditionDTO
{
    public function __construct(
        public int $id,
        public string $descripcion
    ) {
    }
}