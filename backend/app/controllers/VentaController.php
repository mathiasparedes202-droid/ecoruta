<?php

namespace App\Controllers;

use App\Services\VentaService;
use Core\Controller;
use Core\Request;
use Core\Response;

class VentaController extends Controller
{
    private VentaService $ventaService;

    public function __construct(Request $request, Response $response)
    {
        parent::__construct($request, $response);
        $this->ventaService = new VentaService();
    }

    /**
     * Listar todas las ventas
     * GET /api/ventas
     */
    public function index(): void
    {
        try {
            $ventas = $this->ventaService->listarVentas();
            $this->response->json([
                'success' => true,
                'data' => $ventas,
                'total' => count($ventas)
            ], 200);
        } catch (\Exception $e) {
            // Log error details to file for debugging
            $logPath = __DIR__ . '/../../storage/logs/venta_errors.log';
            $msg = '[' . date('Y-m-d H:i:s') . '] ' . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n\n";
            @file_put_contents($logPath, $msg, FILE_APPEND);

            $this->response->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function nextFactura(): void
    {
        try {
            $nextFactura = $this->ventaService->getNextNumeroFactura();
            $this->response->json([
                'success' => true,
                'data' => ['numero_factura' => $nextFactura],
            ], 200);
        } catch (\Exception $e) {
            $this->response->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Listar ventas por cliente
     * GET /api/ventas/cliente?id_cliente={id}
     */
    public function getByCliente(): void
    {
        try {
            $idCliente = (int)($this->request->getQueryParam('id_cliente') ?? 0);
            if (!$idCliente) {
                $this->response->json([
                    'success' => false,
                    'message' => 'ID de cliente requerido'
                ], 400);
                return;
            }

            $ventas = $this->ventaService->listarVentasPorCliente($idCliente);
            $this->response->json([
                'success' => true,
                'data' => $ventas,
                'total' => count($ventas)
            ], 200);
        } catch (\Exception $e) {
            $this->response->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Listar ventas por producto
     * GET /api/ventas/producto?id_producto={id}
     */
    public function getByProducto(): void
    {
        try {
            $idProducto = (int)($this->request->getQueryParam('id_producto') ?? 0);
            if (!$idProducto) {
                $this->response->json([
                    'success' => false,
                    'message' => 'ID de producto requerido'
                ], 400);
                return;
            }

            $ventas = $this->ventaService->listarVentasPorProducto($idProducto);
            $this->response->json([
                'success' => true,
                'data' => $ventas,
                'total' => count($ventas)
            ], 200);
        } catch (\Exception $e) {
            $this->response->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener detalle de una venta
     * GET /api/ventas/{id}
     */
    public function show(array $params): void
    {
        try {
            $id = (int)($params['id'] ?? 0);
            if (!$id) {
                $this->response->json([
                    'success' => false,
                    'message' => 'ID de venta requerido'
                ], 400);
                return;
            }

            $data = $this->ventaService->obtenerVentaConDetalles($id);
            if (!$data) {
                $this->response->json([
                    'success' => false,
                    'message' => 'Venta no encontrada'
                ], 404);
                return;
            }

            // Convertir el objeto Venta a array para evitar problemas de serialización
            $ventaArray = [
                'id_venta' => $data['venta']->id_venta,
                'id_cliente' => $data['venta']->id_cliente,
                'id_pedido' => $data['venta']->id_pedido,
                'numero_factura' => $data['venta']->numero_factura,
                'timbrado' => $data['venta']->timbrado,
                'fecha_emision' => $data['venta']->fecha_emision,
                'estado_factura' => $data['venta']->estado_factura,
                'tipo_comprobante' => $data['venta']->tipo_comprobante,
                'numero_comprobante' => $data['venta']->numero_comprobante,
                'observaciones' => $data['venta']->observaciones,
                'condicion_venta' => $data['venta']->condicion_venta,
                'plazo' => $data['venta']->plazo,
                'total_factura' => (float)$data['venta']->total_factura,
                'iva_5' => (float)$data['venta']->iva_5,
                'iva_10' => (float)$data['venta']->iva_10,
                'subtotal_iva_exenta' => (float)$data['venta']->subtotal_iva_exenta,
                'subtotal_iva_5' => (float)$data['venta']->subtotal_iva_5,
                'subtotal_iva_10' => (float)$data['venta']->subtotal_iva_10,
                'total_iva' => (float)$data['venta']->total_iva,
                'liquidacion_iva_5' => (float)$data['venta']->liquidacion_iva_5,
                'liquidacion_iva_10' => (float)$data['venta']->liquidacion_iva_10,
                'total_liquidacion' => (float)$data['venta']->total_liquidacion,
                'creado_por' => $data['venta']->creado_por,
                'nombre_cliente' => $data['venta']->nombre_cliente ?? null,
                'ruc_ci' => $data['venta']->ruc_ci ?? null,
            ];

            $this->response->json([
                'success' => true,
                'data' => [
                    'venta' => $ventaArray,
                    'detalles' => $data['detalles']
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
     * Crear una nueva venta
     * POST /api/ventas
     */
    public function store(): void
    {
        try {
            $data = $this->request->getBody();
            $user = $this->request->getUser();
            $usuarioId = (int)($user->id_usuario ?? $user['id_usuario'] ?? 0);

            $ventaId = $this->ventaService->registrarVenta($data, $usuarioId);

            $venta = $this->ventaService->obtenerVentaConDetalles($ventaId);

            // Incluir información del usuario que creó la venta
            $usuarioInfo = null;
            if ($usuarioId > 0) {
                $userRepo = new \App\Repositories\UsuarioRepository();
                $usuarioObj = $userRepo->findById($usuarioId);
                if ($usuarioObj) {
                    $usuarioInfo = [
                        'id_usuario' => $usuarioObj->id_usuario,
                        'nombre' => $usuarioObj->nombre,
                        'apellido' => $usuarioObj->apellido,
                        'nombre_completo' => trim($usuarioObj->nombre . ' ' . $usuarioObj->apellido),
                        'email' => $usuarioObj->email,
                        'id_rol' => $usuarioObj->id_rol,
                        'rol' => [
                            'id_rol' => isset($usuarioObj->rol) ? $usuarioObj->rol->id_rol : null,
                            'nombre_rol' => isset($usuarioObj->rol) ? $usuarioObj->rol->nombre_rol : null,
                            'descripcion' => isset($usuarioObj->rol) ? $usuarioObj->rol->descripcion : null,
                        ],
                    ];
                }
            }

            $this->response->json([
                'success' => true,
                'message' => 'Venta registrada exitosamente',
                'data' => $venta,
                'creado_por_usuario' => $usuarioInfo
            ], 201);
        } catch (\InvalidArgumentException $e) {
            $this->response->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
        } catch (\Exception $e) {
            $this->response->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar una venta
     * PUT /api/ventas/{id}
     */
    public function update(array $params): void
    {
        try {
            $id = (int)($params['id'] ?? 0);
            if (!$id) {
                $this->response->json([
                    'success' => false,
                    'message' => 'ID de venta requerido'
                ], 400);
                return;
            }

            $data = $this->request->getBody();
            $this->ventaService->actualizarVenta($id, $data);

            $venta = $this->ventaService->obtenerVentaConDetalles($id);

            $this->response->json([
                'success' => true,
                'message' => 'Venta actualizada exitosamente',
                'data' => $venta
            ], 200);
        } catch (\Exception $e) {
            $this->response->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Anular una venta
     * DELETE /api/ventas/{id}
     */
    public function destroy(array $params): void
    {
        try {
            $id = (int)($params['id'] ?? 0);
            if (!$id) {
                $this->response->json([
                    'success' => false,
                    'message' => 'ID de venta requerido'
                ], 400);
                return;
            }

            $this->ventaService->anularVenta($id);

            $this->response->json([
                'success' => true,
                'message' => 'Venta anulada exitosamente'
            ], 200);
        } catch (\Exception $e) {
            $this->response->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reimprimir comprobante de una venta (devuelve datos listos para imprimir)
     * GET /api/ventas/{id}/reimprimir
     */
    public function reimprimir(array $params): void
    {
        try {
            $id = (int)($params['id'] ?? 0);
            if (!$id) {
                $this->response->json([
                    'success' => false,
                    'message' => 'ID de venta requerido'
                ], 400);
                return;
            }

            $data = $this->ventaService->obtenerVentaConDetalles($id);
            if (!$data) {
                $this->response->json([
                    'success' => false,
                    'message' => 'Venta no encontrada'
                ], 404);
                return;
            }

            $this->response->json([
                'success' => true,
                'data' => [
                    'venta' => $data['venta'],
                    'detalles' => $data['detalles'],
                    'reimpresion' => true
                ]
            ], 200);
        } catch (\Exception $e) {
            $this->response->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function facturaHtml(array $params): void
    {
        try {
            $id = (int)($params['id'] ?? 0);
            if (!$id) {
                $this->response->json([
                    'success' => false,
                    'message' => 'ID de venta requerido'
                ], 400);
                return;
            }

            $factura = $this->buildFacturaPayloadForNode($id);
            $tmpFile = $this->generateFacturaFile($factura, 'html');
            $html = @file_get_contents($tmpFile);
            @unlink($tmpFile);

            if ($html === false) {
                throw new \RuntimeException('No se pudo recuperar el HTML de la factura.');
            }

            header('Content-Type: text/html; charset=UTF-8');
            echo $html;
            exit;
        } catch (\Exception $e) {
            $this->response->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function facturaPdf(array $params): void
    {
        try {
            $id = (int)($params['id'] ?? 0);
            if (!$id) {
                $this->response->json([
                    'success' => false,
                    'message' => 'ID de venta requerido'
                ], 400);
                return;
            }

            $factura = $this->buildFacturaPayloadForNode($id);
            $numeroFactura = $factura['factura']['numeroFactura'] ?? 'factura';
            $tmpFile = $this->generateFacturaFile($factura, 'pdf');

            if (!is_file($tmpFile)) {
                throw new \RuntimeException('No se pudo generar el archivo PDF.');
            }

            header('Content-Type: application/pdf');
            header('Content-Disposition: inline; filename="factura-' . rawurlencode($numeroFactura) . '.pdf"');
            header('Content-Length: ' . filesize($tmpFile));
            readfile($tmpFile);
            @unlink($tmpFile);
            exit;
        } catch (\Exception $e) {
            $this->response->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    private function buildFacturaPayloadForNode(int $idVenta): array
    {
        $data = $this->ventaService->obtenerVentaConDetalles($idVenta);
        if (!$data || !isset($data['venta'])) {
            throw new \RuntimeException('Venta no encontrada.');
        }

        $venta = $data['venta'];
        $detalles = array_map(function ($detalle) {
            $cantidad = (float)($detalle['cantidad'] ?? 0);
            $precioUnitario = (float)($detalle['precio_unitario'] ?? 0);
            $subtotal = (float)($detalle['subtotal'] ?? ($cantidad * $precioUnitario));
            $tipoIva = (int)($detalle['iva_tipo'] ?? 10);

            return [
                'idDetalleVenta' => $detalle['id_detalle_venta'] ?? null,
                'idProducto' => $detalle['id_producto'] ?? null,
                'cantidad' => $cantidad,
                'descripcion' => $detalle['descripcion_producto'] ?? $detalle['nombre_producto'] ?? 'Producto sin descripción',
                'precioUnitario' => $precioUnitario,
                'tipoIva' => $tipoIva,
                'exenta' => $tipoIva === 0 ? $subtotal : 0,
                'iva5' => $tipoIva === 5 ? $subtotal : 0,
                'iva10' => $tipoIva === 10 ? $subtotal : 0,
                'subtotal' => $subtotal,
            ];
        }, $data['detalles'] ?? []);

        return [
            'idVenta' => (int)$venta->id_venta,
            'emisor' => [
                'razonSocial' => 'Floracia',
                'ruc' => '5590551-0',
                'direccion' => 'Ruta Quinta',
                'telefono' => '+595 991 491 815',
                'email' => 'camilavargas@hotmail.com',
                'timbrado' => $venta->timbrado ?? '',
                'vigenciaTimbrado' => '',
                'establecimiento' => '001',
                'puntoExpedicion' => '001',
            ],
            'factura' => [
                'numeroFactura' => $venta->numero_factura ?? '',
                'numeroComprobante' => $venta->numero_comprobante ?? null,
                'tipoComprobante' => $venta->tipo_comprobante ?? 'Factura',
                'timbrado' => $venta->timbrado ?? '',
                'fechaEmision' => $venta->fecha_emision ?? null,
                'estado' => $venta->estado_factura ?? 'Vigente',
                'condicionVenta' => $venta->condicion_venta ?? 'Contado',
                'plazo' => $venta->plazo ?? null,
                'observaciones' => $venta->observaciones ?? null,
            ],
            'cliente' => [
                'idCliente' => $venta->id_cliente ?? null,
                'razonSocial' => $venta->nombre_cliente ?? 'Consumidor final',
                'rucCi' => $venta->ruc_ci ?? 'Sin documento',
                'celular' => null,
            ],
            'detalles' => $detalles,
            'resumen' => [
                'subtotalExenta' => (float)($venta->subtotal_iva_exenta ?? 0),
                'subtotalIva5' => (float)($venta->subtotal_iva_5 ?? 0),
                'subtotalIva10' => (float)($venta->subtotal_iva_10 ?? 0),
                'iva5' => (float)($venta->iva_5 ?? 0),
                'iva10' => (float)($venta->iva_10 ?? 0),
                'totalIva' => (float)($venta->total_iva ?? 0),
                'liquidacionIva5' => (float)($venta->liquidacion_iva_5 ?? 0),
                'liquidacionIva10' => (float)($venta->liquidacion_iva_10 ?? 0),
                'totalLiquidacion' => (float)($venta->total_liquidacion ?? 0),
                'totalFactura' => (float)($venta->total_factura ?? 0),
            ],
        ];
    }

    private function generateFacturaFile(array $factura, string $mode): string
    {
        $baseDir = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'factura-py';
        $scriptPath = $baseDir . DIRECTORY_SEPARATOR . 'generate-factura.js';
        $outputPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'factura_' . uniqid('', true) . ($mode === 'pdf' ? '.pdf' : '.html');
        $payload = json_encode($factura, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($payload === false) {
            throw new \RuntimeException('No se pudo serializar la factura para el generador de PDF.');
        }

        $command = sprintf(
            'node %s --mode %s --input %s --output %s',
            escapeshellarg($scriptPath),
            escapeshellarg($mode),
            escapeshellarg($payload),
            escapeshellarg($outputPath)
        );

        exec($command . ' 2>&1', $lines, $exitCode);

        if ($exitCode !== 0 || !is_file($outputPath)) {
            $errorMessage = implode("\n", $lines);
            throw new \RuntimeException('Error generando la factura con Node.js: ' . trim($errorMessage));
        }

        return $outputPath;
    }
}
