<?php

namespace App\Controllers;

use App\Services\ProveedorService;
use Core\Controller;
use Core\Request;
use Core\Response;

class ProveedorController extends Controller
{
    private ProveedorService $proveedorService;

    public function __construct(Request $request, Response $response)
    {
        parent::__construct($request, $response);
        $this->proveedorService = new ProveedorService();
    }

    public function index(): void
    {
        try {
            $proveedores = $this->proveedorService->listarProveedores();
            $this->response->json(['success' => true, 'data' => $proveedores], 200);
        } catch (\Exception $e) {
            $this->response->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function store(): void
    {
        try {
            $data = $this->request->getBody();
            $proveedor = $this->proveedorService->crearProveedor($data);
            $this->response->json(['success' => true, 'data' => $proveedor], 201);
        } catch (\Exception $e) {
            $this->response->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    public function show(array $params): void
    {
        try {
            $id = (int)$params['id'];
            $proveedor = $this->proveedorService->obtenerProveedor($id);

            if (!$proveedor) {
                $this->response->json(['success' => false, 'message' => 'Proveedor no encontrado'], 404);
                return;
            }

            $this->response->json(['success' => true, 'data' => $proveedor], 200);
        } catch (\Exception $e) {
            $this->response->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function update(array $params): void
    {
        try {
            $id = (int)$params['id'];
            $data = $this->request->getBody();
            $proveedor = $this->proveedorService->actualizarProveedor($id, $data);
            $this->response->json(['success' => true, 'data' => $proveedor], 200);
        } catch (\Exception $e) {
            $this->response->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    public function destroy(array $params): void
    {
        try {
            $id = (int)$params['id'];
            $resultado = $this->proveedorService->eliminarProveedor($id);

            if (!$resultado) {
                $this->response->json(['success' => false, 'message' => 'Proveedor no encontrado o no se pudo eliminar'], 404);
                return;
            }

            $this->response->json(['success' => true, 'message' => 'Proveedor eliminado correctamente'], 200);
        } catch (\Exception $e) {
            $this->response->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
