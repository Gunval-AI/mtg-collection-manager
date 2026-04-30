<?php

namespace App\Controllers;

use App\Services\PrintService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use RuntimeException;

class PrintController
{
    public function __construct(private PrintService $service)
    {
    }

    public function indexByCard(Request $request, Response $response, array $args): Response
    {
        $cardId = (int) $args['id'];

        try {
            $prints = $this->service->getPrintsByCardId($cardId);

            $data = array_map(fn ($print) => [
                'id' => $print->id,
                'nombreEdicion' => $print->nombreEdicion,
                'codigoEdicion' => $print->codigoEdicion,
                'numeroColeccion' => $print->numeroColeccion,
                'rareza' => $print->rareza
            ], $prints);

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

    public function search(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $name = $params['name'] ?? '';

        try {
            $prints = $this->service->searchPrintsByCardName($name);

            $response->getBody()->write(json_encode([
                'success' => true,
                'data' => $prints
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