<?php

namespace App\Controllers;

use App\Services\StockService;
use Core\Controller;
use Core\Request;
use Core\Response;

class StockController extends Controller
{
    private StockService $stockService;

    public function __construct(Request $request, Response $response)
    {
        parent::__construct($request, $response);
        $this->stockService = new StockService();
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
            $this->stockService->ajustarStock($data, $usuario->id_usuario);

            $this->response->json(['success' => true, 'message' => 'Stock ajustado correctamente'], 200);
        } catch (\InvalidArgumentException $e) {
            $this->response->json(['success' => false, 'message' => $e->getMessage()], 400);
        } catch (\Exception $e) {
            $this->response->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
