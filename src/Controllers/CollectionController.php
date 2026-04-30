<?php

namespace App\Controllers;

use App\Services\CollectionService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use RuntimeException;

class CollectionController
{
    public function __construct(private CollectionService $service)
    {
    }

    public function index(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('user');

        $collections = $this->service->getUserCollections($user->id);

        $data = array_map(fn ($c) => [
            'id' => $c->id,
            'nombre' => $c->nombre,
            'descripcion' => $c->descripcion,
            'fechaCreacion' => $c->fechaCreacion,
            'esPrincipal' => $c->esPrincipal
        ], $collections);

        $response->getBody()->write(json_encode([
            'success' => true,
            'data' => $data
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }

    public function create(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('user');
        $data = $request->getParsedBody() ?? [];

        try {
            $id = $this->service->createCollection(
                $user->id,
                $data['nombre'] ?? '',
                $data['descripcion'] ?? null
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
        $collectionId = (int) $args['id'];
        $data = $request->getParsedBody() ?? [];

        try {
            $this->service->deleteCollection(
                $user->id,
                $collectionId,
                $data['strategy'] ?? ''
            );

            $response->getBody()->write(json_encode([
                'success' => true,
                'message' => 'Colección eliminada correctamente.'
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

    public function summary(Request $request, Response $response, array $args): Response
    {
        $user = $request->getAttribute('user');
        $collectionId = (int) $args['id'];

        try {
            $summary = $this->service->getCollectionSummary($user->id, $collectionId);

            $data = array_map(fn ($item) => [
                'impresionId' => $item->impresionId,
                'nombreCarta' => $item->nombreCarta,
                'nombreEdicion' => $item->nombreEdicion,
                'codigoEdicion' => $item->codigoEdicion,
                'numeroColeccion' => $item->numeroColeccion,
                'rareza' => $item->rareza,
                'cantidadCopias' => $item->cantidadCopias,
                'imagenSmall' => $item->imagenSmall,
                'imagenNormal' => $item->imagenNormal,
                'scryfallUri' => $item->scryfallUri
            ], $summary);

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
}