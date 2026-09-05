<?php

namespace App\Controllers;

use App\Services\ProductoService;
use Core\Controller;
use Core\Request;
use Core\Response;

class ProductoController extends Controller
{
    private ProductoService $productoService;

    public function __construct(Request $request, Response $response)
    {
        parent::__construct($request, $response);
        $this->productoService = new ProductoService();
    }

    public function index(): void
    {
        try {
            $productos = $this->productoService->listarProductos();
            $this->response->json(['success' => true, 'data' => $productos], 200);
        } catch (\Exception $e) {
            $this->response->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function show(array $params): void
    {
        try {
            $id = (int)$params['id'];
            $producto = $this->productoService->obtenerProducto($id);

            if (!$producto) {
                $this->response->json(['success' => false, 'message' => 'Producto no encontrado'], 404);
                return;
            }

            $this->response->json(['success' => true, 'data' => $producto], 200);
        } catch (\Exception $e) {
            $this->response->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function store(): void
    {
        try {
            $usuario = $this->request->getUser();
            if (!$usuario) {
                $this->response->json(['success' => false, 'message' => 'Usuario no autenticado'], 401);
                return;
            }

            $data = $this->request->getBody();
            $productoId = $this->productoService->crearProductoConReceta($data, (int)$usuario->id_usuario);

            $this->response->json(['success' => true, 'message' => 'Producto creado', 'data' => ['id_producto' => $productoId]], 201);
        } catch (\InvalidArgumentException $e) {
            $this->response->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            $this->response->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function update(array $params): void
    {
        try {
            $id = (int)($params['id'] ?? 0);
            if (!$id) {
                $this->response->json(['success' => false, 'message' => 'ID de producto requerido'], 400);
                return;
            }

            $usuario = $this->request->getUser();
            if (!$usuario) {
                $this->response->json(['success' => false, 'message' => 'Usuario no autenticado'], 401);
                return;
            }

            $data = $this->request->getBody();
            $this->productoService->actualizarProductoConReceta($id, $data, (int)$usuario->id_usuario);

            $this->response->json(['success' => true, 'message' => 'Producto actualizado'], 200);
        } catch (\InvalidArgumentException $e) {
            $this->response->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            $this->response->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
