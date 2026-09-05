<?php

namespace App\Controllers;

use App\Services\PedidoService;
use Core\Controller;
use Core\Request;
use Core\Response;

class PedidoController extends Controller
{
    private PedidoService $pedidoService;

    public function __construct(Request $request, Response $response)
    {
        parent::__construct($request, $response);
        $this->pedidoService = new PedidoService();
    }

    public function listarOrdenesProduccion(): void
    {
        try {
            $ordenes = $this->pedidoService->listarOrdenesProduccion();
            $this->response->json([
                'success' => true,
                'data' => $ordenes,
                'total' => count($ordenes),
            ], 200);
        } catch (\InvalidArgumentException $e) {
            $this->response->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Exception $e) {
            $this->response->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function mostrarOrdenProduccion(array $params): void
    {
        try {
            $id = (int)($params['id'] ?? 0);
            if (!$id) {
                $this->response->json([
                    'success' => false,
                    'message' => 'ID de orden de producción requerido',
                ], 400);
                return;
            }

            $orden = $this->pedidoService->obtenerOrdenProduccion($id);
            if (!$orden) {
                $this->response->json([
                    'success' => false,
                    'message' => 'Orden de producción no encontrada',
                ], 404);
                return;
            }

            $this->response->json([
                'success' => true,
                'data' => $orden,
            ], 200);
        } catch (\Exception $e) {
            $this->response->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function crearOrdenProduccion(): void
    {
        try {
            $data = $this->request->getBody();
            $user = $this->request->getUser();
            $usuarioId = (int)($user->id_usuario ?? $user['id_usuario'] ?? 0);

            error_log("PedidoController::crearOrdenProduccion - Iniciando creación de orden con " . count($data['detalles'] ?? []) . " detalles");

            // Validar datos básicos
            if (empty($data['detalles'])) {
                $this->response->json([
                    'success' => false,
                    'message' => 'Debe agregar al menos un producto a la orden de producción',
                ], 422);
                return;
            }

            $pedidoId = $this->pedidoService->crearOrdenProduccion($data, $usuarioId);
            $orden = $this->pedidoService->obtenerOrdenProduccion($pedidoId);

            error_log("PedidoController::crearOrdenProduccion - Orden creada exitosamente con ID: $pedidoId");

            $this->response->json([
                'success' => true,
                'message' => 'Orden de producción creada exitosamente',
                'data' => $orden,
            ], 201);
        } catch (\InvalidArgumentException $e) {
            error_log("PedidoController::crearOrdenProduccion - Validation Error: " . $e->getMessage());
            $this->response->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Exception $e) {
            error_log("PedidoController::crearOrdenProduccion - Exception: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            // Log error details to file for debugging
            $logPath = __DIR__ . '/../../storage/logs/pedido_errors.log';
            $msg = '[' . date('Y-m-d H:i:s') . '] ' . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n\n";
            @file_put_contents($logPath, $msg, FILE_APPEND);

            $this->response->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function actualizarOrdenProduccion(array $params): void
    {
        try {
            $id = (int)($params['id'] ?? 0);
            if (!$id) {
                $this->response->json([
                    'success' => false,
                    'message' => 'ID de orden de producción requerido',
                ], 400);
                return;
            }

            $data = $this->request->getBody();
            error_log("PedidoController::actualizarOrdenProduccion - Actualizando orden ID: $id");

            $this->pedidoService->actualizarOrdenProduccion($id, $data);
            $orden = $this->pedidoService->obtenerOrdenProduccion($id);

            error_log("PedidoController::actualizarOrdenProduccion - Orden actualizada exitosamente ID: $id");

            $this->response->json([
                'success' => true,
                'message' => 'Orden de producción actualizada exitosamente',
                'data' => $orden,
            ], 200);
        } catch (\InvalidArgumentException $e) {
            error_log("PedidoController::actualizarOrdenProduccion - Validation Error: " . $e->getMessage());
            $this->response->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Exception $e) {
            error_log("PedidoController::actualizarOrdenProduccion - Exception: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            $this->response->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function eliminarOrdenProduccion(array $params): void
    {
        try {
            $id = (int)($params['id'] ?? 0);
            if (!$id) {
                $this->response->json([
                    'success' => false,
                    'message' => 'ID de orden de producción requerido',
                ], 400);
                return;
            }

            $this->pedidoService->eliminarOrdenProduccion($id);

            $this->response->json([
                'success' => true,
                'message' => 'Orden de producción eliminada exitosamente',
            ], 200);
        } catch (\Exception $e) {
            $this->response->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
