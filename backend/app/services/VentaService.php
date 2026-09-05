<?php

namespace App\Services;

use App\Models\Venta;
use App\Repositories\VentaRepository;
use App\Repositories\ProductoRepository;
use App\Repositories\InsumoRepository;
use App\Repositories\ClienteRepository;
use App\Repositories\PagoClienteRepository;
use App\Services\PedidoService;

class VentaService
{
    private VentaRepository $ventaRepo;
    private ProductoRepository $productoRepo;
    private InsumoRepository $insumoRepo;
    private ClienteRepository $clienteRepo;
    private PedidoService $pedidoService;

    public function __construct()
    {
        $this->ventaRepo = new VentaRepository();
        $this->productoRepo = new ProductoRepository();
        $this->insumoRepo = new InsumoRepository();
        $this->clienteRepo = new ClienteRepository();
        $this->pedidoService = new PedidoService();
    }

    public function listarVentas(): array
    {
        return $this->ventaRepo->all();
    }

    public function listarVentasPorCliente(int $idCliente): array
    {
        return $this->ventaRepo->findByCliente($idCliente);
    }

    public function listarVentasPorProducto(int $idProducto): array
    {
        return $this->ventaRepo->findByProducto($idProducto);
    }

    public function getNextNumeroFactura(): string
    {
        return $this->ventaRepo->getNextNumeroFactura();
    }

    public function obtenerVentaConDetalles(int $id): ?array
    {
        return $this->ventaRepo->findWithDetails($id);
    }

    public function registrarVenta(array $data, int $usuarioId): int
    {
        // Validaciones básicas
        $errores = [];
        $deliveryOption = $data['delivery_option'] ?? 'Retiro';
        $condicionVenta = $data['condicion_venta'] ?? 'Contado';

        if (empty($data['id_cliente'])) {
            $errores[] = 'El cliente es requerido';
        }

        if (empty($data['numero_factura'])) {
            $data['numero_factura'] = $this->ventaRepo->getNextNumeroFactura();
        } elseif (!preg_match('/^\d{3}-\d{3}-\d{7}$/', $data['numero_factura'])) {
            $errores[] = 'El número de factura debe tener formato 001-001-0000001';
        }

        if (empty($data['detalles']) || !is_array($data['detalles']) || count($data['detalles']) === 0) {
            $errores[] = 'Debe incluir al menos un producto en la venta';
        }

        if (!in_array($deliveryOption, ['Retiro', 'Delivery'], true)) {
            $errores[] = 'La modalidad de entrega no es valida';
        }

        if (!in_array($condicionVenta, ['Contado', 'Crédito'], true)) {
            $errores[] = 'La condicion de venta no es valida';
        }

        if ($deliveryOption === 'Delivery' && empty(trim((string)($data['direccion_entrega'] ?? '')))) {
            $errores[] = 'La dirección de entrega es requerida para delivery';
        }

        if (!empty($errores)) {
            throw new \InvalidArgumentException(implode('; ', $errores));
        }

        // Verificar que el cliente existe
        $cliente = $this->clienteRepo->findById($data['id_cliente']);
        if (!$cliente) {
            throw new \Exception('El cliente seleccionado no existe');
        }

        if ($this->ventaRepo->numeroFacturaExiste($data['numero_factura'])) {
            throw new \InvalidArgumentException('El numero de factura ya se encuentra registrado');
        }

        $pedidoVinculadoId = !empty($data['id_pedido']) ? (int)$data['id_pedido'] : null;
        if ($pedidoVinculadoId !== null) {
            $pedidoVinculado = $this->pedidoService->obtenerOrdenProduccion($pedidoVinculadoId);
            if (!$pedidoVinculado) {
                throw new \InvalidArgumentException('La orden de producción vinculada no existe o ya fue cerrada');
            }
            if (!empty($pedidoVinculado['id_cliente']) && (int)$pedidoVinculado['id_cliente'] !== (int)$data['id_cliente']) {
                throw new \InvalidArgumentException('La orden de producción pertenece a otro cliente');
            }
        }

        $details = $data['detalles'];

        // Validar y calcular detalles
        $totalVenta = 0;
        $iva5 = 0;
        $iva10 = 0;
        $subtotalIvaExenta = 0;
        $subtotalIva5 = 0;
        $subtotalIva10 = 0;

        foreach ($details as &$detalle) {
            $producto = null;
            $insumo = null;

            if (!empty($detalle['id_producto'])) {
                $producto = $this->productoRepo->findById((int)$detalle['id_producto']);
                if (!$producto) {
                    throw new \Exception('El producto seleccionado no existe');
                }
            } elseif (!empty($detalle['id_insumo'])) {
                $insumo = $this->insumoRepo->findById((int)$detalle['id_insumo']);
                if (!$insumo) {
                    throw new \Exception('El insumo seleccionado no existe');
                }
            } else {
                throw new \Exception('Uno de los productos no tiene ID válido');
            }

            $cantidad = (int)($detalle['cantidad'] ?? 0);
            $precioUnitario = (float)($detalle['precio_unitario'] ?? 0);
            $ivaTipo = (int)($detalle['iva_tipo'] ?? 10);

            if ($cantidad <= 0) {
                throw new \Exception('La cantidad debe ser mayor a 0');
            }

            if ($producto !== null) {
                $pricing = $this->productoRepo->getCostoYPrecioVenta((int)$detalle['id_producto']);
                if ((float)$pricing['precio_venta'] <= 0) {
                    throw new \Exception('El producto no tiene un precio calculado');
                }
                $precioUnitario = (float)$pricing['precio_venta'];
                $detalle['precio_unitario'] = $precioUnitario;
                $detalle['costo_produccion'] = $pricing['costo_produccion'];
            }

            if ($insumo !== null) {
                $this->insumoRepo->validarUnidadMedida((int)$detalle['id_insumo']);
            }

            if ($precioUnitario < 0) {
                throw new \Exception('El precio no puede ser negativo');
            }

            if (!in_array($ivaTipo, [0, 5, 10], true)) {
                throw new \InvalidArgumentException('El IVA de cada detalle debe ser 0, 5 o 10');
            }

            if ($insumo !== null && $this->insumoRepo->getStockDisponible((int)$detalle['id_insumo']) < $cantidad) {
                throw new \Exception('No hay suficiente stock del insumo seleccionado');
            }

            $subtotal = $cantidad * $precioUnitario;
            $detalle['subtotal'] = $subtotal;

            if ($ivaTipo == 5) {
                $subtotalIva5 += $subtotal;
                $iva5 += $subtotal * 0.05;
            } elseif ($ivaTipo == 10) {
                $subtotalIva10 += $subtotal;
                $iva10 += $subtotal * 0.10;
            } else {
                $subtotalIvaExenta += $subtotal;
            }

            $totalVenta += $subtotal;
        }

        // Calcular totales
        $totalIva = $iva5 + $iva10;
        $totalFactura = $totalVenta; // Total factura = subtotal (sin sumar IVA)

        // Preparar datos de la venta
        $estadoFactura = 'Vigente';

        $ventaData = [
            'id_cliente' => $data['id_cliente'],
            'id_pedido' => $data['id_pedido'] ?? null,
            'numero_factura' => $data['numero_factura'],
            'timbrado' => $data['timbrado'] ?? '',
            'fecha_emision' => $data['fecha_emision'] ?? date('Y-m-d H:i:s'),
            'estado_factura' => $estadoFactura,
            'tipo_comprobante' => $data['tipo_comprobante'] ?? 'Factura',
            'numero_comprobante' => $data['numero_comprobante'] ?? null,
            'delivery_option' => $deliveryOption,
            'direccion_entrega' => $deliveryOption === 'Delivery' ? ($data['direccion_entrega'] ?? null) : null,
            'observaciones' => $data['observaciones'] ?? null,
            'condicion_venta' => $condicionVenta,
            'plazo' => $condicionVenta === 'Crédito' ? ($data['plazo'] ?? null) : null,
            'total_factura' => $totalFactura,
            'iva_5' => $iva5,
            'iva_10' => $iva10,
            'subtotal_iva_exenta' => $subtotalIvaExenta,
            'subtotal_iva_5' => $subtotalIva5,
            'subtotal_iva_10' => $subtotalIva10,
            'total_iva' => $totalIva,
            'liquidacion_iva_5' => 0,
            'liquidacion_iva_10' => 0,
            'total_liquidacion' => 0,
            'creado_por' => $usuarioId,
        ];

        $ventaId = $this->ventaRepo->create($ventaData, $details);

        try {
            $this->actualizarStockInsumos($details, $ventaId, $usuarioId);
        } catch (\Throwable $e) {
            // No existen pagos ni produccion vinculados todavia: se revierte la venta.
            $this->ventaRepo->delete($ventaId);
            throw $e;
        }

        if ($condicionVenta === 'Contado' && $totalFactura > 0) {
            try {
                $pagoRepo = new PagoClienteRepository();
                $pagoContado = is_array($data['pago_contado'] ?? null) ? $data['pago_contado'] : [];
                $formaPago = trim((string)($pagoContado['forma_pago'] ?? 'Efectivo')) ?: 'Efectivo';
                $pagoRepo->createPayment([
                    'id_venta' => $ventaId,
                    'fecha_pago' => $ventaData['fecha_emision'],
                    'monto_total' => $totalFactura,
                    'tipo_pago_general' => $formaPago,
                    'estado' => 'Confirmado',
                    'observaciones' => 'Pago automático de contado',
                ], [
                    [
                        'tipo_pago' => $formaPago,
                        'monto_pagado' => $totalFactura,
                        'numero_comprobante' => null,
                        'cantidad_cuotas' => null,
                        'numero_cuota' => null,
                        'monto_cuota' => null,
                        'fecha_vencimiento' => null,
                        'saldo_pendiente' => 0,
                    ]
                ], $usuarioId);
            } catch (\Throwable $e) {
                $this->revertirStockVenta($ventaId);
                $this->ventaRepo->delete($ventaId);
                throw new \Exception('No se pudo registrar el pago automático de contado: ' . $e->getMessage());
            }
        }

        // Crear orden de producción si algún detalle requiere producción
        $produccionItems = [];
        foreach ($details as $detalle) {
            if (!empty($detalle['id_producto'])) {
                $producto = $this->productoRepo->findById((int)$detalle['id_producto']);
                $detalleProduccion = trim((string)($detalle['detalle_produccion'] ?? ''));
                $recetaPayload = $this->productoRepo->getRecipePayload((int)$detalle['id_producto']);
                $receta = $recetaPayload['receta_detalles'] ?? [];
                $tieneReceta = count($receta) > 0;

                // Decidir si necesita producción: nota de producción, receta o tipo de producto 'Ramo'
                if (empty($data['id_pedido']) && ($detalleProduccion !== '' || $tieneReceta)) {
                    $produccionItems[] = [
                        'id_producto' => (int)$detalle['id_producto'],
                        'cantidad' => (int)$detalle['cantidad'],
                        'precio_estimado' => $this->productoRepo->getPrecioVenta((int)$detalle['id_producto']),
                        'receta' => $receta,
                    ];
                }
            }
        }

        if (!empty($produccionItems)) {
            $pedidoData = [
                'id_cliente' => $data['id_cliente'],
                'detalles' => $produccionItems,
                'observaciones' => 'Orden creada automáticamente desde venta ' . $ventaId,
                'estado' => 'Pendiente',
                'orden_estado' => 'Pendiente',
                'progreso_produccion' => 0,
            ];

            $pedidoId = $this->pedidoService->crearOrdenProduccion($pedidoData, $usuarioId);
            $idOrden = $this->pedidoService->obtenerOrdenProduccionIdPorPedido($pedidoId);

            // Asociar pedido a la venta
            $this->ventaRepo->update($ventaId, ['id_pedido' => $pedidoId]);

            // Actualizar notas de detalle con referencia a la orden
            foreach ($produccionItems as $item) {
                $note = $idOrden !== null ? "Orden {$idOrden} - Pedido {$pedidoId}" : "Pedido {$pedidoId}";
                $this->ventaRepo->setDetalleProduccionForVentaProduct($ventaId, $item['id_producto'], $note);
            }

            $pedidoVinculadoId = $pedidoId;
        }

        // Una venta termina el ciclo operativo: la orden vinculada (existente
        // o generada para un arreglo con receta) se consume y se archiva.
        if ($pedidoVinculadoId !== null) {
            $this->pedidoService->cerrarOrdenPorVenta($pedidoVinculadoId);
        }

        return $ventaId;
    }

    public function anularVenta(int $id): bool
    {
        $venta = $this->ventaRepo->findById($id);
        if (!$venta) {
            throw new \Exception('La venta no existe');
        }

        if ($venta->estado_factura === 'Anulada') {
            throw new \InvalidArgumentException('La venta ya se encuentra anulada');
        }

        $this->revertirStockVenta($id);
        (new PagoClienteRepository())->anularPorVenta($id);

        return $this->ventaRepo->update($id, ['estado_factura' => 'Anulada']);
    }

    /** Una venta emitida sólo permite editar metadatos, no importes ni detalles. */
    public function actualizarVenta(int $id, array $data): bool
    {
        $venta = $this->ventaRepo->findById($id);
        if (!$venta) {
            throw new \InvalidArgumentException('La venta no existe');
        }
        if ($venta->estado_factura === 'Anulada') {
            throw new \InvalidArgumentException('No se puede editar una venta anulada');
        }

        $permitidos = ['observaciones', 'delivery_option', 'direccion_entrega', 'timbrado', 'numero_comprobante'];
        $cambios = array_intersect_key($data, array_flip($permitidos));
        if (isset($cambios['delivery_option']) && !in_array($cambios['delivery_option'], ['Retiro', 'Delivery'], true)) {
            throw new \InvalidArgumentException('La modalidad de entrega no es válida');
        }
        if (($cambios['delivery_option'] ?? $venta->delivery_option) === 'Delivery'
            && empty(trim((string)($cambios['direccion_entrega'] ?? $venta->direccion_entrega)))
        ) {
            throw new \InvalidArgumentException('La dirección de entrega es requerida para delivery');
        }
        if (!$cambios) {
            throw new \InvalidArgumentException('No se enviaron campos editables para la venta');
        }

        return $this->ventaRepo->update($id, $cambios);
    }

    public function eliminarVenta(int $id): bool
    {
        return $this->anularVenta($id);
    }

    private function actualizarStockInsumos(array $details, int $ventaId, int $usuarioId): void
    {
        foreach ($details as $detalle) {
            $idInsumo = (int)($detalle['id_insumo'] ?? 0);
            $idProducto = (int)($detalle['id_producto'] ?? 0);
            $cantidad = (float)($detalle['cantidad'] ?? 0);
            $precioUnitario = (float)($detalle['precio_unitario'] ?? 0);

            if ($idProducto > 0) {
                $this->insumoRepo->registrarMovimientoProducto($idProducto, $cantidad, $precioUnitario, 'Salida', $usuarioId, 'Venta');
                continue;
            }

            if ($idInsumo <= 0 || $cantidad <= 0) {
                continue;
            }

            $insumo = $this->insumoRepo->findById($idInsumo);
            if (!$insumo) {
                throw new \Exception('No se encontró el insumo asociado a la venta');
            }

            if ((float)$insumo->stock < $cantidad) {
                throw new \Exception('No hay suficiente stock del insumo seleccionado');
            }

            if (!$this->insumoRepo->decrementarStock($idInsumo, $cantidad)) {
                throw new \Exception('No hay suficiente stock del insumo seleccionado');
            }

            $this->insumoRepo->registrarMovimientoInsumo($idInsumo, 'Consumo', $cantidad, null, null, 'Venta', $usuarioId);
        }
    }

    private function revertirStockVenta(int $ventaId): void
    {
        $detalles = $this->ventaRepo->getDetallesByVentaId($ventaId);

        foreach ($detalles as $detalle) {
            $idInsumo = (int)($detalle['id_insumo'] ?? 0);
            $cantidad = (float)($detalle['cantidad'] ?? 0);

            if ($idInsumo <= 0 || $cantidad <= 0) {
                continue;
            }

            $this->insumoRepo->incrementarStock($idInsumo, $cantidad);
        }
    }
}
