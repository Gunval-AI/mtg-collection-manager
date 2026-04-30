<?php

use App\Controllers\AuthController;
use App\Controllers\CardController;
use App\Controllers\CollectionController;
use App\Controllers\ConditionController;
use App\Controllers\CopyController;
use App\Controllers\ImageRecognition\ImageRecognitionController;
use App\Controllers\PrintController;
use App\Database\Connection;
use App\Middleware\AuthMiddleware;
use App\Repositories\CardRepository;
use App\Repositories\CollectionRepository;
use App\Repositories\ConditionRepository;
use App\Repositories\CopyRepository;
use App\Repositories\PrintRepository;
use App\Repositories\UserRepository;
use App\Services\AuthService;
use App\Services\CardService;
use App\Services\CollectionService;
use App\Services\ConditionService;
use App\Services\CopyService;
use App\Services\ImageRecognition\ImageRecognitionService;
use App\Services\ImageRecognition\RecognitionApiClient;
use App\Services\PrintService;
use DI\Container;

$settings = require __DIR__ . '/settings.php';

$container = new Container();

$container->set(PDO::class, function () use ($settings) {
    return Connection::create($settings['db']);
});

$container->set(UserRepository::class, function ($c) {
    return new UserRepository($c->get(PDO::class));
});

$container->set(CollectionRepository::class, function ($c) {
    return new CollectionRepository($c->get(PDO::class));
});

$container->set(CopyRepository::class, function ($c) {
    return new CopyRepository($c->get(PDO::class));
});

$container->set(CardRepository::class, function ($c) {
    return new CardRepository($c->get(PDO::class));
});

$container->set(PrintRepository::class, function ($c) {
    return new PrintRepository($c->get(PDO::class));
});

$container->set(ConditionRepository::class, function ($c) {
    return new ConditionRepository($c->get(PDO::class));
});

$container->set(AuthService::class, function ($c) {
    return new AuthService(
        $c->get(UserRepository::class),
        $c->get(CollectionRepository::class),
        $c->get(PDO::class)
    );
});

$container->set(CollectionService::class, function ($c) {
    return new CollectionService(
        $c->get(CollectionRepository::class),
        $c->get(CopyRepository::class),
        $c->get(PDO::class)
    );
});

$container->set(CopyService::class, function ($c) {
    return new CopyService(
        $c->get(CopyRepository::class),
        $c->get(CollectionRepository::class),
        $c->get(PDO::class)
    );
});

$container->set(CardService::class, function ($c) {
    return new CardService(
        $c->get(CardRepository::class)
    );
});

$container->set(PrintService::class, function ($c) {
    return new PrintService(
        $c->get(PrintRepository::class)
    );
});

$container->set(ConditionService::class, function ($c) {
    return new ConditionService(
        $c->get(ConditionRepository::class)
    );
});

$container->set(RecognitionApiClient::class, function () use ($settings) {
    return new RecognitionApiClient($settings['recognition']['base_url']);
});

$container->set(ImageRecognitionService::class, function ($c) {
    return new ImageRecognitionService(
        $c->get(RecognitionApiClient::class),
        $c->get(PrintRepository::class)
    );
});

$container->set(AuthController::class, function ($c) {
    return new AuthController(
        $c->get(AuthService::class)
    );
});

$container->set(CollectionController::class, function ($c) {
    return new CollectionController(
        $c->get(CollectionService::class)
    );
});

$container->set(CopyController::class, function ($c) {
    return new CopyController(
        $c->get(CopyService::class)
    );
});

$container->set(CardController::class, function ($c) {
    return new CardController(
        $c->get(CardService::class)
    );
});

$container->set(PrintController::class, function ($c) {
    return new PrintController(
        $c->get(PrintService::class)
    );
});

$container->set(ConditionController::class, function ($c) {
    return new ConditionController(
        $c->get(ConditionService::class)
    );
});

$container->set(ImageRecognitionController::class, function ($c) {
    return new ImageRecognitionController(
        $c->get(ImageRecognitionService::class)
    );
});

$container->set(AuthMiddleware::class, function () {
    return new AuthMiddleware();
});

return $container;