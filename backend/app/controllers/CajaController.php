<?php

namespace App\Controllers;

use App\Services\CajaService;
use Core\Controller;
use Core\Request;
use Core\Response;

class CajaController extends Controller
{
    private CajaService $cajaService;

    public function __construct(Request $request, Response $response)
    {
        parent::__construct($request, $response);
        $this->cajaService = new CajaService();
    }

    public function status(): void
    {
        try {
            $estado = $this->cajaService->obtenerEstadoCaja();
            $this->response->json(['success' => true, 'data' => $estado], 200);
        } catch (\Throwable $e) {
            $this->response->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function abrir(): void
    {
        try {
            $usuario = $this->request->getUser();
            if (!$usuario) {
                $this->response->json(['success' => false, 'message' => 'Usuario no autenticado'], 401);
                return;
            }

            $body = $this->request->getBody();
            $montoInicial = isset($body['monto_inicial']) ? (float)$body['monto_inicial'] : 0.0;
            $observacion = $body['observacion'] ?? null;

            $aperturaId = $this->cajaService->abrirCaja((int)$usuario->id_usuario, $montoInicial, $observacion);

            $this->response->json(['success' => true, 'message' => 'Caja abierta', 'data' => ['id_apertura' => $aperturaId]], 201);
        } catch (\Exception $e) {
            $this->response->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function cerrar(): void
    {
        try {
            $usuario = $this->request->getUser();
            if (!$usuario) {
                $this->response->json(['success' => false, 'message' => 'Usuario no autenticado'], 401);
                return;
            }

            $body = $this->request->getBody();
            if (!isset($body['monto_final'])) {
                $this->response->json(['success' => false, 'message' => 'Monto final es requerido'], 422);
                return;
            }

            $montoFinal = (float)$body['monto_final'];
            $observacion = $body['observacion'] ?? null;

            $cierreId = $this->cajaService->cerrarCaja((int)$usuario->id_usuario, $montoFinal, $observacion);

            $this->response->json(['success' => true, 'message' => 'Caja cerrada', 'data' => ['id_cierre' => $cierreId]], 201);
        } catch (\InvalidArgumentException $e) {
            $this->response->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            $this->response->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function movimientos(): void
    {
        try {
            $movimientos = $this->cajaService->obtenerMovimientosCaja();
            $this->response->json(['success' => true, 'data' => $movimientos], 200);
        } catch (\Exception $e) {
            $this->response->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
