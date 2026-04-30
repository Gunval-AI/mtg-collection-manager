<?php

namespace App\Repositories;

use PDO;

class UserRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function findByEmail(string $email): ?array
    {
        $sql = "SELECT id_usuario, nombre_usuario, email, contrasena_hash
                FROM usuarios
                WHERE email = :email
                LIMIT 1";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['email' => $email]);

        $user = $stmt->fetch();

        return $user ?: null;
    }

    public function create(string $username, string $email, string $passwordHash): int
    {
        $sql = "INSERT INTO usuarios (nombre_usuario, email, contrasena_hash)
                VALUES (:username, :email, :password)";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'username' => $username,
            'email' => $email,
            'password' => $passwordHash,
        ]);

        return (int) $this->pdo->lastInsertId();
    }
}