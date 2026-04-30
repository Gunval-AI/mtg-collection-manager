<?php

namespace App\Services;

use App\DTO\CollectionDTO;
use App\DTO\CopyDTO;
use App\Repositories\CollectionRepository;
use App\Repositories\CopyRepository;
use PDO;
use RuntimeException;
use Throwable;

class CopyService
{
    public function __construct(
        private CopyRepository $copyRepository,
        private CollectionRepository $collectionRepository,
        private PDO $pdo
    ) {
    }

    public function getCopiesByCollection(int $userId, int $collectionId): array
    {
        $this->getUserCollectionOrFail($userId, $collectionId);

        return $this->copyRepository->findByCollectionId($collectionId);
    }

    public function createCopy(
        int $userId,
        int $collectionId,
        int $impresionId,
        int $condicionId,
        string $idioma,
        bool $esFoil
    ): int {
        $this->getUserCollectionOrFail($userId, $collectionId, 'No tienes permiso para añadir copias a esta colección.');

        if (!$this->copyRepository->impresionExists($impresionId)) {
            throw new RuntimeException('La impresión no existe.');
        }

        if (!$this->copyRepository->condicionExists($condicionId)) {
            throw new RuntimeException('La condición no existe.');
        }

        $idioma = $this->normalizeAndValidateIdioma($idioma);

        return $this->copyRepository->create(
            $collectionId,
            $impresionId,
            $condicionId,
            $idioma,
            $esFoil
        );
    }

    public function deleteCopy(int $userId, int $copyId): void
    {
        $this->getUserCopyOrFail($userId, $copyId, 'No tienes permiso para eliminar esta copia.');

        $deleted = $this->copyRepository->deleteById($copyId);

        if (!$deleted) {
            throw new RuntimeException('No se pudo eliminar la copia.');
        }
    }

    public function getCopiesByCollectionAndPrint(int $userId, int $collectionId, int $printId): array
    {
        $this->getUserCollectionOrFail($userId, $collectionId);

        if (!$this->copyRepository->impresionExists($printId)) {
            throw new RuntimeException('La impresión no existe.');
        }

        return $this->copyRepository->findByCollectionIdAndPrintId($collectionId, $printId);
    }

    public function updateCopy(
        int $userId,
        int $copyId,
        int $condicionId,
        string $idioma,
        bool $esFoil
    ): void {
        $this->getUserCopyOrFail($userId, $copyId, 'No tienes permiso para modificar esta copia.');

        if (!$this->copyRepository->condicionExists($condicionId)) {
            throw new RuntimeException('La condición no existe.');
        }

        $idioma = $this->normalizeAndValidateIdioma($idioma);

        $this->copyRepository->update($copyId, $condicionId, $idioma, $esFoil);
    }

    public function moveCopies(int $userId, array $copyIds, int $targetCollectionId): void
    {
        if (empty($copyIds)) {
            throw new RuntimeException('Debes indicar al menos una copia.');
        }

        if ($targetCollectionId <= 0) {
            throw new RuntimeException('La colección destino no es válida.');
        }

        $this->getUserCollectionOrFail(
            $userId,
            $targetCollectionId,
            'No tienes permiso para mover copias a esta colección.',
            'La colección destino no existe.'
        );

        foreach ($copyIds as $copyId) {
            if (!is_int($copyId) && !ctype_digit((string) $copyId)) {
                throw new RuntimeException('La lista de copias no es válida.');
            }

            $this->getUserCopyOrFail(
                $userId,
                (int) $copyId,
                'No tienes permiso para mover una de las copias seleccionadas.',
                'Una de las copias no existe.',
                'La colección asociada a una copia no existe.'
            );
        }

        // Move all selected copies in a single transaction to avoid partial moves.
        $this->pdo->beginTransaction();

        try {
            foreach ($copyIds as $copyId) {
                $this->copyRepository->moveToCollection((int) $copyId, $targetCollectionId);
            }

            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();

            throw new RuntimeException('No se pudieron mover las copias.', 0, $e);
        }
    }

    private function getUserCollectionOrFail(
        int $userId,
        int $collectionId,
        string $permissionMessage = 'No tienes permiso para acceder a esta colección.',
        string $notFoundMessage = 'La colección no existe.'
    ): CollectionDTO {
        $collection = $this->collectionRepository->findById($collectionId);

        if ($collection === null) {
            throw new RuntimeException($notFoundMessage);
        }

        if ($collection->usuarioId !== $userId) {
            throw new RuntimeException($permissionMessage);
        }

        return $collection;
    }

    private function getUserCopyOrFail(
        int $userId,
        int $copyId,
        string $permissionMessage,
        string $copyNotFoundMessage = 'La copia no existe.',
        string $collectionNotFoundMessage = 'La colección asociada no existe.'
    ): CopyDTO {
        $copy = $this->copyRepository->findById($copyId);

        if ($copy === null) {
            throw new RuntimeException($copyNotFoundMessage);
        }

        $this->getUserCollectionOrFail(
            $userId,
            $copy->coleccionId,
            $permissionMessage,
            $collectionNotFoundMessage
        );

        return $copy;
    }

    private function normalizeAndValidateIdioma(string $idioma): string
    {
        $idioma = strtoupper(trim($idioma));

        if (!in_array($idioma, ['EN', 'ES'], true)) {
            throw new RuntimeException('El idioma no es válido. Debe ser EN o ES.');
        }

        return $idioma;
    }
}