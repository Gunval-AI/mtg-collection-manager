<?php

require __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;
use Slim\Factory\AppFactory;

$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

session_start();

$settings = require __DIR__ . '/../config/settings.php';

$container = require __DIR__ . '/../config/dependencies.php';
AppFactory::setContainer($container);

$app = AppFactory::create();

$app->addRoutingMiddleware();

$app->addErrorMiddleware(
    $settings['app']['debug'],
    $settings['app']['debug'],
    $settings['app']['debug']
);

$app->addBodyParsingMiddleware();

(require __DIR__ . '/../routes/web.php')($app);

$app->run();