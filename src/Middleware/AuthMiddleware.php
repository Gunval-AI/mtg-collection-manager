<?php

namespace App\Middleware;

use App\DTO\UserDTO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as Handler;
use Slim\Psr7\Response as SlimResponse;

class AuthMiddleware
{
    public function __invoke(Request $request, Handler $handler): Response
    {
        if (!isset($_SESSION['user'])) {
            $response = new SlimResponse();

            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'No autenticado'
            ]));

            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(401);
        }

        $userArray = $_SESSION['user'];

        $user = new UserDTO(
            (int) $userArray['id'],
            $userArray['nombreUsuario'],
            $userArray['email']
        );

        $request = $request->withAttribute('user', $user);

        return $handler->handle($request);
    }
}