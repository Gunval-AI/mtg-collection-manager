<?php

namespace App\DTO;

class UserDTO
{
    public function __construct(
        public int $id,
        public string $nombreUsuario,
        public string $email
    ) {}
}