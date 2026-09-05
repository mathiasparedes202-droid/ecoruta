<?php

namespace App\Middlewares;

use Core\Request;
use Core\Response;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use App\Repositories\UsuarioRepository;
use Exception;

class AuthMiddleware
{
    private $userRepository;

    // Constructor
    public function __construct()
    {
        $this->userRepository = new UsuarioRepository();
    }

    /**
     * Maneja la autenticación del usuario mediante JWT
     *
     * @param Request $request
     * @param Response $response
     * @return void
     */
    public function handle(Request $request, Response $response)
    {
        $token = $request->getBearerToken();

        if (!$token) {
            $response->error('No autorizado. Token no proporcionado', 401);
        }

        try {
            $secret = $_ENV['JWT_SECRET'] ?? null;

            if (!$secret) {
                throw new Exception('JWT_SECRET no definido');
            }

            // Decodificar token
            $decoded = JWT::decode($token, new Key($secret, 'HS256'));
            $payload = (array) $decoded;

            // Obtener usuario desde repository
            $user = $this->userRepository->findById($payload['sub']);

            if (!$user) {
                $response->error('Usuario no válido', 401);
            }

            // Adjuntar usuario al request
            $request->setUser($user);

        } catch (Exception $e) {
            $response->error('Token inválido o expirado', 401, $e->getMessage());
        }
    }
}