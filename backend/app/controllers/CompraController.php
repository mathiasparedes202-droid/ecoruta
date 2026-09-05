<?php

namespace App\Controllers;

use App\Services\CompraService;
use Core\Controller;
use Core\Request;
use Core\Response;

class CompraController extends Controller
{
    private CompraService $compraService;

    public function __construct(Request $request, Response $response)
    {
        parent::__construct($request, $response);
        $this->compraService = new CompraService();
    }

    /**
     * Listar todas las compras
     * GET /api/compras
     */
    public function index(): void
    {
        try {
            $compras = $this->compraService->listarCompras();
            $this->response->json([
                'success' => true,
                'data' => $compras,
                'total' => count($compras)
            ], 200);
        } catch (\Exception $e) {
            $this->response->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Listar compras por proveedor
     * GET /api/compras/proveedor?id_proveedor={id}
     */
    public function getByProveedor(): void
    {
        try {
            $idProveedor = (int)($this->request->getQueryParam('id_proveedor') ?? 0);
            if (!$idProveedor) {
                $this->response->json([
                    'success' => false,
                    'message' => 'ID de proveedor requerido'
                ], 400);
                return;
            }

            $compras = $this->compraService->listarComprasPorProveedor($idProveedor);
            $this->response->json([
                'success' => true,
                'data' => $compras,
                'total' => count($compras)
            ], 200);
        } catch (\Exception $e) {
            $this->response->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function getByInsumo(): void
    {
        try {
            $idInsumo = (int)($this->request->getQueryParam('id_insumo') ?? 0);
            if (!$idInsumo) {
                $this->response->json([
                    'success' => false,
                    'message' => 'ID de insumo requerido'
                ], 400);
                return;
            }

            $compras = $this->compraService->listarComprasPorInsumo($idInsumo);
            $this->response->json([
                'success' => true,
                'data' => $compras,
                'total' => count($compras)
            ], 200);
        } catch (\Exception $e) {
            $this->response->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener detalle de una compra
     * GET /api/compras/{id}
     */
    public function show(array $params): void
    {
        try {
            $id = (int)($params['id'] ?? 0);
            if (!$id) {
                $this->response->json([
                    'success' => false,
                    'message' => 'ID de compra requerido'
                ], 400);
                return;
            }

            $data = $this->compraService->obtenerCompraConDetalles($id);
            if (!$data) {
                $this->response->json([
                    'success' => false,
                    'message' => 'Compra no encontrada'
                ], 404);
                return;
            }

            // Convertir el objeto Compra a array para evitar problemas de serialización
            $compraArray = [
                'id_compra' => $data['compra']->id_compra,
                'id_proveedor' => $data['compra']->id_proveedor,
                'numero_factura' => $data['compra']->numero_factura,
                'timbrado' => $data['compra']->timbrado,
                'fecha_emision' => $data['compra']->fecha_emision,
                'estado_factura' => $data['compra']->estado_factura,
                'tipo_comprobante' => $data['compra']->tipo_comprobante,
                'condicion_compra' => $data['compra']->condicion_compra,
                'plazo' => $data['compra']->plazo,
                'observaciones' => $data['compra']->observaciones,
                'total_compra' => (float)$data['compra']->total_compra,
                'iva_5' => (float)$data['compra']->iva_5,
                'iva_10' => (float)$data['compra']->iva_10,
                'subtotal_iva_exenta' => (float)$data['compra']->subtotal_iva_exenta,
                'subtotal_iva_5' => (float)$data['compra']->subtotal_iva_5,
                'subtotal_iva_10' => (float)$data['compra']->subtotal_iva_10,
                'total_iva' => (float)$data['compra']->total_iva,
                'liquidacion_iva_5' => (float)$data['compra']->liquidacion_iva_5,
                'liquidacion_iva_10' => (float)$data['compra']->liquidacion_iva_10,
                'total_liquidacion' => (float)$data['compra']->total_liquidacion,
                'fecha_creacion' => $data['compra']->fecha_creacion,
                'creado_por' => $data['compra']->creado_por,
                'nombre_proveedor' => $data['compra']->nombre_proveedor ?? null,
                'ruc_proveedor' => $data['compra']->ruc_proveedor ?? null,
            ];

            // Formatear detalles
            $detalles = array_map(function ($detalle) {
                return [
                    'id_compra_detalle' => $detalle['id_detalle'] ?? null,
                    'id_compra' => $detalle['id_compra'] ?? null,
                    'id_insumo' => $detalle['id_insumo'] ?? null,
                    'nombre_insumo' => $detalle['nombre_insumo'] ?? '',
                    'item' => $detalle['item'] ?? null,
                    'cantidad' => (int)($detalle['cantidad'] ?? 0),
                    'precio_unitario' => (float)($detalle['precio_unitario'] ?? 0),
                    'preuni' => (float)($detalle['precio_unitario'] ?? 0),
                    'descuento' => isset($detalle['descuento']) ? (float)$detalle['descuento'] : 0,
                    'lote' => $detalle['lote'] ?? null,
                    'fecha_caducidad' => $detalle['fecha_caducidad'] ?? null,
                    'observaciones' => $detalle['observaciones'] ?? null,
                    'iva_tipo' => isset($detalle['iva_tipo']) ? (int)$detalle['iva_tipo'] : 10,
                    'tiva' => isset($detalle['iva_tipo']) ? (int)$detalle['iva_tipo'] : 10,
                    'subtotal' => (float)($detalle['subtotal'] ?? 0)
                ];
            }, $data['detalles']);

            $this->response->json([
                'success' => true,
                'data' => [
                    'compra' => $compraArray,
                    'detalles' => $detalles
                ]
            ], 200);
        } catch (\Exception $e) {
            $this->response->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Crear nueva compra
     * POST /api/compras
     */
    public function store(): void
    {
        try {
            $usuario = $this->request->getUser();
            if (!$usuario) {
                $this->response->json([
                    'success' => false,
                    'message' => 'Usuario no autenticado'
                ], 401);
                return;
            }

            $data = $this->request->getBody();

            // Validaciones básicas
            if (empty($data['id_proveedor'])) {
                throw new \InvalidArgumentException('El proveedor es requerido');
            }
            if (empty($data['numero_factura'])) {
                throw new \InvalidArgumentException('El número de factura es requerido');
            }
            if (empty($data['detalles']) || !is_array($data['detalles'])) {
                throw new \InvalidArgumentException('Debe incluir al menos un detalle');
            }

            $compraId = $this->compraService->registrarCompra($data, $usuario->id_usuario);

            $this->response->json([
                'success' => true,
                'data' => ['id_compra' => $compraId],
                'message' => 'Compra registrada correctamente'
            ], 201);
        } catch (\InvalidArgumentException $e) {
            $this->response->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        } catch (\Exception $e) {
            $this->response->json([
                'success' => false,
                'message' => 'Error al registrar: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar datos de una compra
     * PUT /api/compras/{id}
     */
    public function update(array $params): void
    {
        try {
            $id = (int)($params['id'] ?? 0);
            if (!$id) {
                $this->response->json([
                    'success' => false,
                    'message' => 'ID de compra requerido'
                ], 400);
                return;
            }

            $usuario = $this->request->getUser();
            if (!$usuario) {
                $this->response->json([
                    'success' => false,
                    'message' => 'Usuario no autenticado'
                ], 401);
                return;
            }

            $data = $this->request->getBody();
            $resultado = $this->compraService->actualizarCompra($id, $data);

            if (!$resultado) {
                $this->response->json([
                    'success' => false,
                    'message' => 'No se pudo actualizar la compra'
                ], 400);
                return;
            }

            $this->response->json([
                'success' => true,
                'message' => 'Compra actualizada correctamente'
            ], 200);
        } catch (\InvalidArgumentException $e) {
            $this->response->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        } catch (\Exception $e) {
            $this->response->json([
                'success' => false,
                'message' => 'Error al actualizar: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Anular una compra
     * DELETE /api/compras/{id}
     */
    public function destroy(array $params): void
    {
        try {
            $id = (int)($params['id'] ?? 0);
            if (!$id) {
                $this->response->json([
                    'success' => false,
                    'message' => 'ID de compra requerido'
                ], 400);
                return;
            }

            $usuario = $this->request->getUser();
            if (!$usuario) {
                $this->response->json([
                    'success' => false,
                    'message' => 'Usuario no autenticado'
                ], 401);
                return;
            }

            $body = $this->request->getBody();
            $motivo = trim($body['motivo'] ?? '');
            $crearNotaCredito = $body['crear_nota_credito'] ?? false;

            if (empty($motivo)) {
                $this->response->json([
                    'success' => false,
                    'message' => 'El motivo de anulación es requerido'
                ], 400);
                return;
            }

            $resultado = $this->compraService->anularCompra($id, $usuario->id_usuario, $motivo, $crearNotaCredito);

            if (!$resultado) {
                $this->response->json([
                    'success' => false,
                    'message' => 'Compra no encontrada o no se pudo anular'
                ], 404);
                return;
            }

            $this->response->json([
                'success' => true,
                'message' => 'Compra anulada correctamente'
            ], 200);
        } catch (\InvalidArgumentException $e) {
            $this->response->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        } catch (\Exception $e) {
            $this->response->json([
                'success' => false,
                'message' => 'Error al anular: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generar vista previa/HTML imprimible del comprobante (Boleta/Recibo/Factura)
     * POST /api/compras/preview
     */
    public function preview(): void
    {
        try {
            $data = $this->request->getBody();

            $compra = $data['compra'] ?? null;
            $detalles = $data['detalles'] ?? [];
            $tipo = $data['tipo'] ?? ($compra['tipo_comprobante'] ?? 'Factura');

            if (!$compra) {
                $this->response->json(['success' => false, 'message' => 'Datos de compra requeridos'], 400);
                return;
            }

            // Construir HTML simple
            $html = '<!doctype html><html><head><meta charset="utf-8"><title>Comprobante - ' . htmlspecialchars($tipo) . '</title>';
            $html .= '<style>body{font-family: Arial,Helvetica,sans-serif;padding:20px} .header{display:flex;justify-content:space-between;align-items:center} .items{width:100%;border-collapse:collapse;margin-top:16px} .items th,.items td{border:1px solid #ddd;padding:8px;text-align:left} .totales{margin-top:12px;text-align:right}</style>';
            $html .= '</head><body>';
            $html .= '<div class="header"><div><h2>Floracia</h2><div>Comprobante: ' . htmlspecialchars($tipo) . '</div></div>';
            $html .= '<div><strong>Fecha:</strong> ' . htmlspecialchars($compra['fecha_emision'] ?? '') . '<br><strong>Nº:</strong> ' . htmlspecialchars($compra['numero_factura'] ?? '') . '</div></div>';

            $html .= '<div style="margin-top:12px"><strong>Proveedor:</strong> ' . htmlspecialchars($compra['nombre_proveedor'] ?? '') . '<br><strong>RUC:</strong> ' . htmlspecialchars($compra['ruc_proveedor'] ?? '') . '</div>';

            $html .= '<table class="items"><thead><tr><th>Producto</th><th>Cant.</th><th>Precio</th><th>Subtotal</th></tr></thead><tbody>';
            $subtotal = 0;
            foreach ($detalles as $d) {
                $nombre = htmlspecialchars($d['nombre_insumo'] ?? $d['descripcion'] ?? '');
                $cant = (int)($d['cantidad'] ?? 0);
                $precio = number_format((float)($d['precio_unitario'] ?? 0), 2, '.', ',');
                $sub = $cant * (float)($d['precio_unitario'] ?? 0);
                $subtotal += $sub;
                $html .= "<tr><td>$nombre</td><td>$cant</td><td>₲ $precio</td><td>₲ " . number_format($sub, 2, '.', ',') . "</td></tr>";
            }
            $html .= '</tbody></table>';

            $iva = isset($compra['total_iva']) ? (float)$compra['total_iva'] : round($subtotal * 0.1, 2);
            $total = isset($compra['total_compra']) ? (float)$compra['total_compra'] : round($subtotal + $iva, 2);

            $html .= '<div class="totales"><div>Subtotal: ₲ ' . number_format($subtotal, 2, '.', ',') . '</div>';
            $html .= '<div>IVA: ₲ ' . number_format($iva, 2, '.', ',') . '</div>';
            $html .= '<div style="font-weight:700;margin-top:8px">Total: ₲ ' . number_format($total, 2, '.', ',') . '</div></div>';

            $html .= '<script>window.onload = function(){ window.print(); };</script>';

            // Enviar HTML como texto
            $this->response->addHeader('Content-Type', 'text/html; charset=UTF-8');
            echo $html;
            exit;
        } catch (\Exception $e) {
            $this->response->json(['success' => false, 'message' => 'Error generando preview: ' . $e->getMessage()], 500);
        }
    }
}
