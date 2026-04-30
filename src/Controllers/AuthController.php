<?php

namespace App\Controllers;

use App\Services\AuthService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use RuntimeException;

class AuthController
{
    public function __construct(private AuthService $authService)
    {
    }

    public function register(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody() ?? [];

        try {
            $user = $this->authService->register(
                $data['username'] ?? '',
                $data['email'] ?? '',
                $data['password'] ?? ''
            );

            $response->getBody()->write(json_encode([
                'success' => true,
                'data' => $this->mapUserResponse($user)
            ]));

            return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
        } catch (RuntimeException $e) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]));

            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }
    }

    public function login(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody() ?? [];

        try {
            $user = $this->authService->login(
                $data['email'] ?? '',
                $data['password'] ?? ''
            );

            $_SESSION['user'] = $this->mapUserResponse($user);

            $response->getBody()->write(json_encode([
                'success' => true,
                'data' => $this->mapUserResponse($user)
            ]));

            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        } catch (RuntimeException $e) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]));

            return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
        }
    }

    public function logout(Request $request, Response $response): Response
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();

            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        session_destroy();

        $response->getBody()->write(json_encode([
            'success' => true,
            'message' => 'Logout correcto'
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }

    public function me(Request $request, Response $response): Response
    {
        if (!isset($_SESSION['user'])) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'No autenticado'
            ]));

            return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
        }

        $response->getBody()->write(json_encode([
            'success' => true,
            'data' => $_SESSION['user']
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }

    private function mapUserResponse(object $user): array
    {
        return [
            'id' => $user->id,
            'nombreUsuario' => $user->nombreUsuario,
            'email' => $user->email
        ];
    }
}