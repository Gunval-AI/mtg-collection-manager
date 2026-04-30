<?php

use App\Controllers\AuthController;
use App\Controllers\CardController;
use App\Controllers\CollectionController;
use App\Controllers\ConditionController;
use App\Controllers\CopyController;
use App\Controllers\HealthController;
use App\Controllers\ImageRecognition\ImageRecognitionController;
use App\Controllers\PrintController;
use App\Middleware\AuthMiddleware;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;

return function (App $app): void {
    $app->get('/', function (Request $request, Response $response) {
        $response->getBody()->write('HOME OK');

        return $response;
    });

    //Public routes
    $app->get('/health', HealthController::class . ':check');

    $app->post('/register', AuthController::class . ':register');
    $app->post('/login', AuthController::class . ':login');

    $app->get('/cards', CardController::class . ':search');
    $app->get('/cards/{id}/prints', PrintController::class . ':indexByCard');
    $app->get('/prints/search', PrintController::class . ':search');
    $app->get('/conditions', ConditionController::class . ':index');

    //Private routes
    $app->group('', function ($group) {
        $group->get('/me', AuthController::class . ':me');
        $group->post('/logout', AuthController::class . ':logout');

        $group->get('/collections', CollectionController::class . ':index');
        $group->post('/collections', CollectionController::class . ':create');
        $group->delete('/collections/{id}', CollectionController::class . ':delete');
        $group->get('/collections/{id}/summary', CollectionController::class . ':summary');
        $group->get(
            '/collections/{collectionId}/prints/{printId}/copies',
            CopyController::class . ':indexByCollectionAndPrint'
        );

        $group->get('/collections/{id}/copies', CopyController::class . ':indexByCollection');
        $group->post('/copies', CopyController::class . ':create');
        $group->patch('/copies/move', CopyController::class . ':move');
        $group->patch('/copies/{id}', CopyController::class . ':update');
        $group->delete('/copies/{id}', CopyController::class . ':delete');

        $group->post('/image-recognition/analyze', ImageRecognitionController::class . ':analyze');
    })->add(AuthMiddleware::class);
};