<?php

namespace App\Services;

use App\DTO\CollectionDTO;
use App\Repositories\CollectionRepository;
use App\Repositories\CopyRepository;
use PDO;
use RuntimeException;
use Throwable;

class CollectionService
{
    public function __construct(
        private CollectionRepository $repository,
        private CopyRepository $copyRepository,
        private PDO $pdo
    ) {
    }

    public function getUserCollections(int $userId): array
    {
        return $this->repository->findByUser($userId);
    }

    public function createCollection(int $userId, string $nombre, ?string $descripcion): int
    {
        $nombre = trim($nombre);

        if ($nombre === '') {
            throw new RuntimeException('El nombre es obligatorio.');
        }

        return $this->repository->create($userId, $nombre, $descripcion);
    }

    public function deleteCollection(int $userId, int $collectionId, string $strategy): void
    {
        $collection = $this->getUserCollectionOrFail($userId, $collectionId);

        if ($collection->esPrincipal) {
            throw new RuntimeException('No se puede eliminar la colección principal.');
        }

        if (!in_array($strategy, ['delete_all', 'move_to_principal'], true)) {
            throw new RuntimeException('La estrategia de borrado no es válida.');
        }

        // Delete the collection and handle its copies in a single transaction.
        $this->pdo->beginTransaction();

        try {
            if ($strategy === 'delete_all') {
                $this->copyRepository->deleteByCollectionId($collectionId);
            }

            if ($strategy === 'move_to_principal') {
                $principal = $this->repository->findPrincipalByUserId($userId);

                if ($principal === null) {
                    throw new RuntimeException('No se encontró la colección principal del usuario.');
                }

                if ($principal->id === $collectionId) {
                    throw new RuntimeException('No se puede mover a la misma colección.');
                }

                $this->copyRepository->moveByCollectionId($collectionId, $principal->id);
            }

            $deleted = $this->repository->deleteById($collectionId);

            if (!$deleted) {
                throw new RuntimeException('No se pudo eliminar la colección.');
            }

            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();

            if ($e instanceof RuntimeException) {
                throw $e;
            }

            throw new RuntimeException('No se pudo completar el borrado de la colección.', 0, $e);
        }
    }

    public function getCollectionSummary(int $userId, int $collectionId): array
    {
        $this->getUserCollectionOrFail($userId, $collectionId);

        return $this->repository->getSummaryById($collectionId);
    }

    private function getUserCollectionOrFail(int $userId, int $collectionId): CollectionDTO
    {
        $collection = $this->repository->findById($collectionId);

        if ($collection === null) {
            throw new RuntimeException('La colección no existe.');
        }

        if ($collection->usuarioId !== $userId) {
            throw new RuntimeException('No tienes permiso para acceder a esta colección.');
        }

        return $collection;
    }
}