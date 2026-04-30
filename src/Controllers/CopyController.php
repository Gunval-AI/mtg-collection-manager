<?php

namespace App\Controllers;

use App\Services\CopyService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use RuntimeException;

class CopyController
{
    public function __construct(private CopyService $service)
    {
    }

    public function indexByCollection(Request $request, Response $response, array $args): Response
    {
        $user = $request->getAttribute('user');
        $collectionId = (int) $args['id'];

        try {
            $copies = $this->service->getCopiesByCollection($user->id, $collectionId);

            $data = array_map(fn ($copy) => $this->mapCopyResponse($copy), $copies);

            $response->getBody()->write(json_encode([
                'success' => true,
                'data' => $data
            ]));

            return $response->withHeader('Content-Type', 'application/json');
        } catch (RuntimeException $e) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]));

            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(400);
        }
    }

    public function create(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('user');
        $data = $request->getParsedBody() ?? [];

        try {
            $id = $this->service->createCopy(
                $user->id,
                (int) ($data['collectionId'] ?? 0),
                (int) ($data['impresionId'] ?? 0),
                (int) ($data['condicionId'] ?? 0),
                (string) ($data['idioma'] ?? ''),
                filter_var($data['esFoil'] ?? false, FILTER_VALIDATE_BOOL)
            );

            $response->getBody()->write(json_encode([
                'success' => true,
                'id' => $id
            ]));

            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(201);
        } catch (RuntimeException $e) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]));

            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(400);
        }
    }

    public function delete(Request $request, Response $response, array $args): Response
    {
        $user = $request->getAttribute('user');
        $copyId = (int) $args['id'];

        try {
            $this->service->deleteCopy($user->id, $copyId);

            $response->getBody()->write(json_encode([
                'success' => true,
                'message' => 'Copia eliminada correctamente.'
            ]));

            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(200);
        } catch (RuntimeException $e) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]));

            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(400);
        }
    }

    public function indexByCollectionAndPrint(Request $request, Response $response, array $args): Response
    {
        $user = $request->getAttribute('user');
        $collectionId = (int) $args['collectionId'];
        $printId = (int) $args['printId'];

        try {
            $copies = $this->service->getCopiesByCollectionAndPrint($user->id, $collectionId, $printId);

            $data = array_map(fn ($copy) => $this->mapCopyResponse($copy), $copies);

            $response->getBody()->write(json_encode([
                'success' => true,
                'data' => $data
            ]));

            return $response->withHeader('Content-Type', 'application/json');
        } catch (RuntimeException $e) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]));

            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(400);
        }
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        $user = $request->getAttribute('user');
        $copyId = (int) $args['id'];
        $data = $request->getParsedBody() ?? [];

        try {
            $this->service->updateCopy(
                $user->id,
                $copyId,
                (int) ($data['condicionId'] ?? 0),
                (string) ($data['idioma'] ?? ''),
                filter_var($data['esFoil'] ?? false, FILTER_VALIDATE_BOOL)
            );

            $response->getBody()->write(json_encode([
                'success' => true,
                'message' => 'Copia actualizada correctamente.'
            ]));

            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(200);
        } catch (RuntimeException $e) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]));

            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(400);
        }
    }

    public function move(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('user');
        $data = $request->getParsedBody() ?? [];

        try {
            $this->service->moveCopies(
                $user->id,
                $data['copyIds'] ?? [],
                (int) ($data['targetCollectionId'] ?? 0)
            );

            $response->getBody()->write(json_encode([
                'success' => true,
                'message' => 'Copias movidas correctamente.'
            ]));

            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(200);
        } catch (RuntimeException $e) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]));

            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(400);
        }
    }

    private function mapCopyResponse(object $copy): array
    {
        return [
            'id' => $copy->id,
            'coleccionId' => $copy->coleccionId,
            'impresionId' => $copy->impresionId,
            'nombreCarta' => $copy->nombreCarta,
            'nombreEdicion' => $copy->nombreEdicion,
            'codigoEdicion' => $copy->codigoEdicion,
            'numeroColeccion' => $copy->numeroColeccion,
            'idioma' => $copy->idioma,
            'esFoil' => $copy->esFoil,
            'rareza' => $copy->rareza,
            'condicionId' => $copy->condicionId,
            'condicion' => $copy->condicion,
            'fechaCreacion' => $copy->fechaCreacion,
            'imagenSmall' => $copy->imagenSmall,
            'imagenNormal' => $copy->imagenNormal,
            'scryfallUri' => $copy->scryfallUri
        ];
    }
}