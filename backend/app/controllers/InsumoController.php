<?php

namespace App\Controllers;

use App\Services\InsumoService;
use Core\Controller;
use Core\Request;
use Core\Response;

class InsumoController extends Controller
{
    private InsumoService $insumoService;

    public function __construct(Request $request, Response $response)
    {
        parent::__construct($request, $response);
        $this->insumoService = new InsumoService();
    }

    public function index(): void
    {
        try {
            $insumos = $this->insumoService->listarInsumos();
            $this->response->json(['success' => true, 'data' => $insumos], 200);
        } catch (\Exception $e) {
            $this->response->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function show(array $params): void
    {
        try {
            $id = (int)$params['id'];
            $insumo = $this->insumoService->obtenerInsumo($id);

            if (!$insumo) {
                $this->response->json(['success' => false, 'message' => 'Insumo no encontrado'], 404);
                return;
            }

            $this->response->json(['success' => true, 'data' => $insumo], 200);
        } catch (\Exception $e) {
            $this->response->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function store(): void
    {
        try {
            $data = $this->request->getBody();
            $insumoId = $this->insumoService->crearInsumo($data);
            $insumo = $this->insumoService->obtenerInsumo($insumoId);
            
            $this->response->json(['success' => true, 'data' => $insumo], 201);
        } catch (\Exception $e) {
            $this->response->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    public function update(array $params): void
    {
        try {
            $id = (int)$params['id'];
            $data = $this->request->getBody();
            $this->insumoService->actualizarInsumo($id, $data);
            $insumo = $this->insumoService->obtenerInsumo($id);
            
            $this->response->json(['success' => true, 'data' => $insumo], 200);
        } catch (\Exception $e) {
            $this->response->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    public function destroy(array $params): void
    {
        try {
            $id = (int)$params['id'];
            $resultado = $this->insumoService->eliminarInsumo($id);

            if (!$resultado) {
                $this->response->json(['success' => false, 'message' => 'Insumo no encontrado'], 404);
                return;
            }

            $this->response->json(['success' => true, 'message' => 'Insumo eliminado correctamente'], 200);
        } catch (\Exception $e) {
            $this->response->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
