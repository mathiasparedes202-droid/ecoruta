<?php

namespace App\Controllers;

use App\Services\CatalogoService;
use Core\Controller;
use Core\Request;
use Core\Response;

class CatalogoController extends Controller
{
    private CatalogoService $catalogoService;

    public function __construct(Request $request, Response $response)
    {
        parent::__construct($request, $response);
        $this->catalogoService = new CatalogoService();
    }

    public function index(array $params): void
    {
        try {
            $catalogos = $this->catalogoService->listarCatalogos($params['section']);
            $this->response->json(['success' => true, 'data' => $catalogos], 200);
        } catch (\Exception $e) {
            $this->response->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    public function show(array $params): void
    {
        try {
            $catalogo = $this->catalogoService->obtenerCatalogo($params['section'], (int)$params['id']);

            if (!$catalogo) {
                $this->response->json(['success' => false, 'message' => 'Elemento no encontrado'], 404);
                return;
            }

            $this->response->json(['success' => true, 'data' => $catalogo], 200);
        } catch (\Exception $e) {
            $this->response->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    public function store(array $params): void
    {
        try {
            $data = $this->request->getBody();
            $catalogo = $this->catalogoService->crearCatalogo($params['section'], $data);
            $this->response->json(['success' => true, 'data' => $catalogo], 201);
        } catch (\Exception $e) {
            $this->response->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    public function update(array $params): void
    {
        try {
            $data = $this->request->getBody();
            $catalogo = $this->catalogoService->actualizarCatalogo($params['section'], (int)$params['id'], $data);
            $this->response->json(['success' => true, 'data' => $catalogo], 200);
        } catch (\Exception $e) {
            $this->response->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    public function destroy(array $params): void
    {
        try {
            $resultado = $this->catalogoService->eliminarCatalogo($params['section'], (int)$params['id']);

            if (!$resultado) {
                $this->response->json(['success' => false, 'message' => 'Elemento no encontrado o no se pudo eliminar'], 404);
                return;
            }

            $this->response->json(['success' => true, 'message' => 'Elemento eliminado correctamente'], 200);
        } catch (\Exception $e) {
            $this->response->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }
}
