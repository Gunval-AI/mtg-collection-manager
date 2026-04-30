<?php

namespace App\Controllers;

use App\Services\CardService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use RuntimeException;

class CardController
{
    public function __construct(private CardService $service)
    {
    }

    public function search(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $name = $params['name'] ?? '';

        try {
            $cards = $this->service->searchCardsByName($name);

            $data = array_map(fn($card) => [
                'id' => $card->id,
                'nombre' => $card->nombreEn,
                'tipo' => $card->tipo,
                'mana' => $card->mana,
                'cmc' => $card->cmc
            ], $cards);

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