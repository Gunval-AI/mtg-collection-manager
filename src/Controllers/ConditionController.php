<?php

namespace App\Controllers;

use App\Services\ConditionService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class ConditionController
{
    public function __construct(private ConditionService $service)
    {
    }

    public function index(Request $request, Response $response): Response
    {
        $conditions = $this->service->getAllConditions();

        $data = array_map(fn ($condition) => [
            'id' => $condition->id,
            'descripcion' => $condition->descripcion
        ], $conditions);

        $response->getBody()->write(json_encode([
            'success' => true,
            'data' => $data
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }
}