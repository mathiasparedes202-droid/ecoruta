<?php

namespace App\Controllers;

use Core\Controller;
use Core\Request;
use Core\Response;
use App\Services\UsuarioService;
use App\Services\RolService;

class UsuarioController extends Controller
{
    private UsuarioService $usuarioService;
    private RolService $rolService;

    public function __construct(Request $request, Response $response)
    {
        parent::__construct($request, $response);
        $this->usuarioService = new UsuarioService();
        $this->rolService = new RolService();
    }

    public function index(): void
    {
        try {
            $usuarios = $this->usuarioService->listarUsuarios();
            $this->response->json(['success' => true, 'data' => $usuarios], 200);
        } catch (\Exception $e) {
            $this->response->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function store(): void
    {
        try {
            $data = $this->request->getBody();
            $usuario = $this->usuarioService->crearUsuario($data);
            $this->response->json(['success' => true, 'data' => $usuario], 201);
        } catch (\Exception $e) {
            $this->response->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    public function show(array $params): void
    {
        try {
            $id = (int)$params['id'];
            $usuario = $this->usuarioService->obtenerUsuario($id);
            $rol = $this->rolService->obtenerRol($usuario->id_rol);

            $usuario->rol = $rol;
            
            if (!$usuario) {
                $this->response->json(['success' => false, 'message' => 'Usuario no encontrado'], 404);
                return;
            }

            $this->response->json(['success' => true, 'data' => $usuario], 200);
        } catch (\Exception $e) {
            $this->response->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function update(array $params): void
    {
        try {
            $id = (int)$params['id'];
            $data = $this->request->getBody();
            $usuario = $this->usuarioService->actualizarUsuario($id, $data);

            $this->response->json(['success' => true, 'data' => $usuario], 200);
        } catch (\Exception $e) {
            $this->response->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    public function checkDependencies(array $params): void
    {
        try {
            $id = (int)($params['id'] ?? 0);
            if ($id <= 0) {
                $this->response->json(['success' => false, 'message' => 'ID inválido'], 400);
                return;
            }

            $usuario = $this->usuarioService->obtenerUsuario($id);
            if (!$usuario) {
                $this->response->json(['success' => false, 'message' => 'Usuario no encontrado'], 404);
                return;
            }

            // Verificar dependencias: ventas, compras registradas por este usuario
            $dependencies = [];

            // Todos los usuarios se pueden eliminar (soft delete si tienen dependencias)
            // Aquí se puede agregar lógica para contar registros del usuario si es necesario

            $this->response->json([
                'success' => true,
                'data' => [
                    'canDelete' => true, // Los usuarios siempre se pueden soft-delete
                    'hardAllowed' => empty($dependencies),
                    'dependencies' => $dependencies,
                    'usuario' => $usuario
                ]
            ], 200);
        } catch (\Exception $e) {
            $this->response->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function destroy(array $params): void
    {
        try {
            $id = (int)$params['id'];
            $resultado = $this->usuarioService->eliminarUsuario($id);

            if (!$resultado) {
                $this->response->json([
                    'success' => false,
                    'message' => 'Usuario no encontrado o no se pudo eliminar'
                ], 404);
                return;
            }

            $this->response->json(['success' => true, 'message' => 'Usuario eliminado correctamente'], 200);
        } catch (\Exception $e) {
            $this->response->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}