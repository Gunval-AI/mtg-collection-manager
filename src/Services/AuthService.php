<?php

namespace App\Services;

use App\DTO\UserDTO;
use App\Repositories\CollectionRepository;
use App\Repositories\UserRepository;
use PDO;
use RuntimeException;
use Throwable;

class AuthService
{
    public function __construct(
        private UserRepository $userRepository,
        private CollectionRepository $collectionRepository,
        private PDO $pdo
    ) {
    }

    public function register(string $username, string $email, string $password): UserDTO
    {
        $username = trim($username);
        $email = trim(strtolower($email));

        if ($username === '' || $email === '' || $password === '') {
            throw new RuntimeException('Todos los campos son obligatorios.');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Email inválido.');
        }

        if (strlen($password) < 8) {
            throw new RuntimeException('La contraseña debe tener al menos 8 caracteres.');
        }

        if ($this->userRepository->findByEmail($email)) {
            throw new RuntimeException('El email ya está registrado.');
        }

        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        // Create user and main collection in a single transaction to keep data consistent.
        $this->pdo->beginTransaction();

        try {
            $userId = $this->userRepository->create($username, $email, $passwordHash);

            $this->collectionRepository->createPrincipal($userId);

            $this->pdo->commit();

            return new UserDTO(
                $userId,
                $username,
                $email
            );
        } catch (Throwable $e) {
            $this->pdo->rollBack();

            throw new RuntimeException('Error al registrar usuario.', 0, $e);
        }
    }

    public function login(string $email, string $password): UserDTO
    {
        $email = trim(strtolower($email));

        if ($email === '' || $password === '') {
            throw new RuntimeException('Email y contraseña obligatorios.');
        }

        $user = $this->userRepository->findByEmail($email);

        if (!$user) {
            throw new RuntimeException('Credenciales inválidas.');
        }

        if (!password_verify($password, $user['contrasena_hash'])) {
            throw new RuntimeException('Credenciales inválidas.');
        }

        return new UserDTO(
            (int) $user['id_usuario'],
            $user['nombre_usuario'],
            $user['email']
        );
    }
}