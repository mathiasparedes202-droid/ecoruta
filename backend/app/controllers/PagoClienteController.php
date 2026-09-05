<?php

namespace App\Controllers;

use App\Services\PagoClienteService;
use Core\Controller;
use Core\Request;
use Core\Response;

class PagoClienteController extends Controller
{
    private PagoClienteService $service;

    public function __construct(Request $request, Response $response)
    {
        parent::__construct($request, $response);
        $this->service = new PagoClienteService();
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
            $id = $this->service->registrarPago($data, (int)$usuario->id_usuario);

            $this->response->json(['success' => true, 'message' => 'Pago registrado', 'data' => ['id_pago' => $id]], 201);
        } catch (\InvalidArgumentException $e) {
            $this->response->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            $this->response->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function index(): void
    {
        try {
            $idVenta = (int)($this->request->getQueryParam('venta_id') ?? $this->request->getQueryParam('id_venta') ?? 0);
            $repo = new \App\Repositories\PagoClienteRepository();
            if ($idVenta) {
                $pagos = $repo->findByVenta($idVenta);
                $this->response->json(['success' => true, 'data' => $pagos], 200);
                return;
            }

            // Si no se envía idVenta, devolver todos los pagos
            $query = $this->request->getQueryParam('q') ?? null;
            $pdo = (new \Config\Database())->connect();
            $stmt = $pdo->prepare("SELECT pc.*, u.nombre AS cobrador_nombre, u.apellido AS cobrador_apellido FROM pago_cliente_cabecera pc LEFT JOIN usuario u ON pc.creado_por = u.id_usuario ORDER BY pc.fecha_pago DESC, pc.id_pago DESC");
            $stmt->execute();
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            $this->response->json(['success' => true, 'data' => $rows], 200);
        } catch (\Throwable $e) {
            $this->response->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function show(array $params): void
    {
        try {
            $id = (int)($params['id'] ?? 0);
            if (!$id) {
                $this->response->json(['success' => false, 'message' => 'ID de pago requerido'], 400);
                return;
            }

            $repo = new \App\Repositories\PagoClienteRepository();
            $data = $repo->findById($id);
            if (!$data) {
                $this->response->json(['success' => false, 'message' => 'Pago no encontrado'], 404);
                return;
            }

            $this->response->json(['success' => true, 'data' => $data], 200);
        } catch (\Throwable $e) {
            $this->response->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
