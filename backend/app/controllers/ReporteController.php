<?php

namespace App\Controllers;

use App\Services\ReporteService;
use Core\Controller;
use Core\Request;
use Core\Response;

class ReporteController extends Controller
{
    private ReporteService $reporteService;

    public function __construct(Request $request, Response $response)
    {
        parent::__construct($request, $response);
        $this->reporteService = new ReporteService();
    }

    public function show(array $params): void
    {
        try {
            $section = trim($params['section'] ?? '');
            if (!$section) {
                $this->response->json([
                    'success' => false,
                    'message' => 'Sección de reporte requerida'
                ], 400);
                return;
            }

            $desde = $this->request->getQueryParam('desde');
            $hasta = $this->request->getQueryParam('hasta');

            $result = $this->reporteService->obtenerReporte($section, $desde, $hasta);

            $this->response->json([
                'success' => true,
                'data' => $result,
                'total' => count($result)
            ], 200);
        } catch (\InvalidArgumentException $e) {
            $this->response->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 404);
        } catch (\Throwable $e) {
            $this->response->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
