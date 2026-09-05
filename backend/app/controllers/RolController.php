<?php

namespace App\Controllers;

use Core\Controller;
use Core\Request;
use Core\Response;
use App\Services\RolService;

class RolController extends Controller
{
    private RolService $rolService;

    public function __construct(Request $request, Response $response)
    {
        parent::__construct($request, $response);
        $this->rolService = new RolService();
    }

    public function index(): void
    {
        try {
            $roles = $this->rolService->listarRoles();
            $this->response->json(['success' => true, 'data' => $roles], 200);
        } catch (\Exception $e) {
            $this->response->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function store(): void
    {
        try {
            $data = $this->request->getBody();
            $rol = $this->rolService->crearRol($data);
            $this->response->json(['success' => true, 'data' => $rol], 201);
        } catch (\Exception $e) {
            $this->response->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    public function show(array $params): void
    {
        try {
            $id = (int)$params['id'];
            $rol = $this->rolService->obtenerRol($id);

            if (!$rol) {
                $this->response->json(['success' => false, 'message' => 'Rol no encontrado'], 404);
                return;
            }

            $this->response->json(['success' => true, 'data' => $rol], 200);
        } catch (\Exception $e) {
            $this->response->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function update(array $params): void
    {
        try {
            $id = (int)$params['id'];
            $data = $this->request->getBody();
            $rol = $this->rolService->actualizarRol($id, $data);
            $this->response->json(['success' => true, 'data' => $rol], 200);
        } catch (\Exception $e) {
            $this->response->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    public function destroy(array $params): void
    {
        try {
            $id = (int)$params['id'];
            $resultado = $this->rolService->eliminarRol($id);

            if (!$resultado) {
                $this->response->json([
                    'success' => false,
                    'message' => 'Rol no encontrado o no se pudo eliminar'
                ], 404);
                return;
            }

            $this->response->json(['success' => true, 'message' => 'Rol eliminado correctamente'], 200);
        } catch (\Exception $e) {
            $this->response->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }
}
