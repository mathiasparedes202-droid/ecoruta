<?php

namespace App\Services;

use App\Repositories\PagoProveedorRepository;
use App\Repositories\ProveedorRepository;
use App\Repositories\CompraRepository;
use App\Repositories\CompraHistorialRepository;

class PagoProveedorService
{
    private PagoProveedorRepository $pagoProveedorRepo;
    private ProveedorRepository $proveedorRepo;
    private CompraRepository $compraRepo;
    private CompraHistorialRepository $compraHistorialRepo;

    public function __construct()
    {
        $this->pagoProveedorRepo = new PagoProveedorRepository();
        $this->proveedorRepo = new ProveedorRepository();
        $this->compraRepo = new CompraRepository();
        $this->compraHistorialRepo = new CompraHistorialRepository();
    }

    public function listarPagos(): array
    {
        return $this->pagoProveedorRepo->all();
    }

    public function obtenerProveedoresConFacturasPendientes(): array
    {
        // Obtener todas las compras vigentes con sus proveedores
        $compras = $this->compraRepo->findComprasVigentes();

        // Calcular el total pagado para cada compra
        $proveedoresMap = [];

        foreach ($compras as $compra) {
            $totalPagado = $this->pagoProveedorRepo->getTotalPagadoPorCompra($compra->id_compra);
            $saldoPendiente = $compra->total_compra - $totalPagado;

            // Solo incluir si hay saldo pendiente
            if ($saldoPendiente > 0) {
                $idProveedor = $compra->id_proveedor;

                if (!isset($proveedoresMap[$idProveedor])) {
                    $proveedoresMap[$idProveedor] = [
                        'id_proveedor' => $idProveedor,
                        'razon_social' => $compra->nombre_proveedor,
                        'ruc_proveedor' => $compra->ruc_proveedor ?? null,
                        'total_pendiente' => 0,
                        'compras_pendientes' => []
                    ];
                }

                $proveedoresMap[$idProveedor]['total_pendiente'] += $saldoPendiente;
                $proveedoresMap[$idProveedor]['compras_pendientes'][] = [
                    'id_compra' => $compra->id_compra,
                    'numero_factura' => $compra->numero_factura,
                    'fecha_emision' => $compra->fecha_emision,
                    'total_compra' => $compra->total_compra,
                    'total_pagado' => $totalPagado,
                    'saldo_pendiente' => $saldoPendiente,
                    'condicion_compra' => $compra->condicion_compra
                ];
            }
        }

        return array_values($proveedoresMap);
    }

    public function registrarPago(array $data, int $usuarioId): int
    {
        $idCompra = isset($data['id_compra']) && $data['id_compra'] ? (int)$data['id_compra'] : null;
        $fechaPago = $data['fecha_pago'] ?? null;

        if (!$idCompra) {
            throw new \InvalidArgumentException('Seleccione una compra válida');
        }

        if (!$fechaPago) {
            throw new \InvalidArgumentException('La fecha de pago es obligatoria');
        }

        // Verificar que la compra existe
        $compra = $this->compraRepo->findById($idCompra);
        if (!$compra) {
            throw new \Exception('La compra seleccionada no existe');
        }

        $detalles = [];
        if (!empty($data['detalles']) && is_array($data['detalles'])) {
            foreach ($data['detalles'] as $detalle) {
                $detalles[] = [
                    'metodo_pago' => trim($detalle['metodo_pago'] ?? 'Efectivo'),
                    'monto_pagado' => isset($detalle['monto']) ? (float)$detalle['monto'] : (float)($detalle['monto_pagado'] ?? 0),
                    'numero_comprobante' => $detalle['numero_comprobante'] ?? null,
                    'banco' => $detalle['banco'] ?? null,
                    'fecha_vencimiento' => $detalle['fecha_vencimiento'] ?? null,
                    'estado_pago' => $detalle['estado_pago'] ?? 'Confirmado',
                    'observaciones' => $detalle['observaciones'] ?? $data['observaciones'] ?? null,
                ];
            }
        } else {
            $detalles[] = [
                'metodo_pago' => trim($data['metodo_pago'] ?? 'Efectivo'),
                'monto_pagado' => isset($data['monto']) ? (float)$data['monto'] : 0,
                'numero_comprobante' => $data['numero_comprobante'] ?? null,
                'banco' => $data['banco'] ?? null,
                'fecha_vencimiento' => $data['fecha_vencimiento'] ?? null,
                'estado_pago' => $data['estado_pago'] ?? 'Confirmado',
                'observaciones' => $data['observaciones'] ?? null,
            ];
        }

        $totalPagos = array_sum(array_map(fn($item) => $item['monto_pagado'], $detalles));
        if ($totalPagos <= 0) {
            throw new \InvalidArgumentException('El monto total de las formas de pago debe ser mayor a cero');
        }

        $totalPagado = $this->pagoProveedorRepo->getTotalPagadoPorCompra($idCompra);
        $saldoPendiente = $compra->total_compra - $totalPagado;

        if ($totalPagos > $saldoPendiente) {
            throw new \InvalidArgumentException("El monto total no puede exceder el saldo pendiente de ₲ " . number_format($saldoPendiente, 2, ',', '.'));
        }

        // Iniciar transacción
        $this->pagoProveedorRepo->beginTransaction();

        try {
            // Crear el pago
            $idPago = $this->pagoProveedorRepo->create([
                'id_compra' => $idCompra,
                'fecha_pago' => $fechaPago,
                'detalles' => $detalles,
                'tipo_pago_general' => 'Compra',
                'observaciones' => $data['observaciones'] ?? null,
                'creado_por' => $usuarioId,
            ]);

            // Calcular el nuevo total pagado después del pago
            $nuevoTotalPagado = $this->compraRepo->getTotalPagadoPorCompra($idCompra);

            // Actualizar el estado de pago de la compra
            $this->compraRepo->actualizarEstadoPago($idCompra, $nuevoTotalPagado, $usuarioId);

            // Registrar en el historial de la compra
            foreach ($detalles as $detalle) {
                $this->compraHistorialRepo->create([
                    'id_compra' => $idCompra,
                    'usuario_id' => $usuarioId,
                    'campo_modificado' => 'Pago registrado',
                    'valor_anterior' => null,
                    'valor_nuevo' => "Monto: ₲" . number_format($detalle['monto_pagado'], 2, ',', '.') . " - Método: " . $detalle['metodo_pago'] . " - Fecha: " . $fechaPago,
                ]);
            }

            // Confirmar transacción
            $this->pagoProveedorRepo->commit();
            return $idPago;
        } catch (\Exception $e) {
            // Revertir transacción en caso de error
            $this->pagoProveedorRepo->rollback();
            throw $e;
        }
    }

    public function anularPago(int $idPago): void
    {
        $pago = $this->pagoProveedorRepo->findByPagoId($idPago);
        if (!$pago) {
            throw new \Exception('El pago no existe');
        }

        $idCompra = $pago->id_compra ?? null;

        // Iniciar transacción
        $this->pagoProveedorRepo->beginTransaction();

        try {
            if ($idCompra) {
                // Registrar en el historial antes de anular
                $this->compraHistorialRepo->create([
                    'id_compra' => $idCompra,
                    'usuario_id' => null, // Podríamos necesitar pasar el usuario que anula
                    'campo_modificado' => 'Pago anulado',
                    'valor_anterior' => "Monto: ₲" . number_format($pago->monto_total ?? 0, 2, ',', '.') . " - Método: " . ($pago->tipo_pago ?? 'N/A'),
                    'valor_nuevo' => null,
                ]);
            }

            $this->pagoProveedorRepo->delete($idPago);

            // Recalcular el estado de pago después de anular
            if ($idCompra) {
                $nuevoTotalPagado = $this->compraRepo->getTotalPagadoPorCompra($idCompra);
                $this->compraRepo->actualizarEstadoPago($idCompra, $nuevoTotalPagado, 0); // Usuario 0 para anulaciones
            }

            // Confirmar transacción
            $this->pagoProveedorRepo->commit();
        } catch (\Exception $e) {
            // Revertir transacción en caso de error
            $this->pagoProveedorRepo->rollback();
            throw $e;
        }
    }
}
