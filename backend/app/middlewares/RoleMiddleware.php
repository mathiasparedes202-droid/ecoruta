<?php

namespace App\Middlewares;

use Core\Request;
use Core\Response;
use Exception;

class RoleMiddleware
{
    private array $allowedRoles;

    /**
     * Constructor
     *
     * @param array $allowedRoles - Lista de roles permitidos para la ruta
     */
    public function __construct(array $allowedRoles = [])
    {
        $this->allowedRoles = $allowedRoles;
    }

    /**
     * Maneja la validación de roles
     *
     * @param Request $request
     * @param Response $response
     * @return void
     */
    public function handle(Request $request, Response $response)
    {
        // Obtenemos el usuario autenticado desde el request
        $user = $request->getUser();

        // Verificamos que exista
        if (!$user) {
            $response->error('No autorizado. Usuario no autenticado', 401);
        }

        $userRole = $user['rol'] ?? null;

        if (!$userRole) {
            $response->error('Rol de usuario no definido', 403);
        }

        // Validamos si el rol del usuario está permitido
        if (!in_array($userRole, $this->allowedRoles)) {
            $response->error('Acceso denegado. Rol insuficiente', 403);
        }

        // Usuario autorizado, continúa el flujo
    }
}