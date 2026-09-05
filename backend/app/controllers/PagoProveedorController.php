<?php

namespace App\Controllers;

use App\Services\PagoProveedorService;
use Core\Controller;
use Core\Request;
use Core\Response;

class PagoProveedorController extends Controller
{
    private PagoProveedorService $pagoProveedorService;

    public function __construct(Request $request, Response $response)
    {
        parent::__construct($request, $response);
        $this->pagoProveedorService = new PagoProveedorService();
    }

    public function index(): void
    {
        try {
            $pagos = $this->pagoProveedorService->listarPagos();
            $this->response->json(['success' => true, 'data' => $pagos], 200);
        } catch (\Exception $e) {
            $this->response->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function proveedoresPendientes(): void
    {
        try {
            $proveedores = $this->pagoProveedorService->obtenerProveedoresConFacturasPendientes();
            $this->response->json(['success' => true, 'data' => $proveedores], 200);
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
            $this->pagoProveedorService->registrarPago($data, $usuario->id_usuario);

            $this->response->json(['success' => true, 'message' => 'Pago registrado correctamente'], 201);
        } catch (\InvalidArgumentException $e) {
            $this->response->json(['success' => false, 'message' => $e->getMessage()], 400);
        } catch (\Exception $e) {
            $this->response->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function destroy(array $params): void
    {
        try {
            $usuario = $this->request->getUser();
            if (!$usuario) {
                $this->response->json(['success' => false, 'message' => 'Usuario no autenticado'], 401);
                return;
            }

            $id = (int)($params['id'] ?? 0);
            if (!$id) {
                $this->response->json(['success' => false, 'message' => 'ID de pago requerido'], 400);
                return;
            }

            $this->pagoProveedorService->anularPago($id);

            $this->response->json(['success' => true, 'message' => 'Pago anulado correctamente'], 200);
        } catch (\Exception $e) {
            $this->response->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
