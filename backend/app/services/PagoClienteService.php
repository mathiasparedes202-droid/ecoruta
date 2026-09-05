<?php

namespace App\Services;

use App\Repositories\PagoClienteRepository;
use App\Repositories\VentaRepository;

class PagoClienteService
{
    private PagoClienteRepository $repo;
    private VentaRepository $ventaRepo;

    public function __construct()
    {
        $this->repo = new PagoClienteRepository();
        $this->ventaRepo = new VentaRepository();
    }

    public function registrarPago(array $data, int $usuarioId): int
    {
        if (empty($data['id_venta'])) {
            throw new \InvalidArgumentException('Venta requerida para registrar pago');
        }

        $venta = $this->ventaRepo->findById((int)$data['id_venta']);
        if (!$venta) {
            throw new \InvalidArgumentException('La venta seleccionada no existe');
        }

        $montoTotal = isset($data['monto_total']) ? (float)$data['monto_total'] : 0.0;
        if ($montoTotal <= 0) {
            throw new \InvalidArgumentException('El monto total del pago debe ser mayor a cero');
        }

        if (empty($data['fecha_pago'])) {
            throw new \InvalidArgumentException('La fecha de pago es obligatoria');
        }

        $totalPagadoActual = $this->repo->getTotalPagadoPorVenta((int)$data['id_venta']);
        $saldoPendienteActual = max(0, (float)$venta->total_factura - $totalPagadoActual);
        if ($montoTotal > $saldoPendienteActual + 0.001) {
            throw new \InvalidArgumentException('El monto total no puede exceder el saldo pendiente de ₲ ' . number_format($saldoPendienteActual, 2, ',', '.'));
        }

        $detalles = [];
        if (!empty($data['detalles']) && is_array($data['detalles'])) {
            foreach ($data['detalles'] as $detalle) {
                $detalles[] = [
                    'tipo_pago' => $detalle['tipo_pago'] ?? 'Efectivo',
                    'monto_pagado' => isset($detalle['monto_pagado']) ? (float)$detalle['monto_pagado'] : 0,
                    'numero_comprobante' => $detalle['numero_comprobante'] ?? null,
                    'cantidad_cuotas' => $detalle['cantidad_cuotas'] ?? null,
                    'numero_cuota' => $detalle['numero_cuota'] ?? null,
                    'monto_cuota' => $detalle['monto_cuota'] ?? null,
                    'fecha_vencimiento' => $detalle['fecha_vencimiento'] ?? null,
                    'saldo_pendiente' => max(0, (float)$venta->total_factura - ($totalPagadoActual + $montoTotal)),
                ];
            }
        } else {
            throw new \InvalidArgumentException('Debe enviar al menos un detalle de pago');
        }

        $totalDetalle = array_sum(array_map(fn($item) => (float)($item['monto_pagado'] ?? 0), $detalles));
        // Permitir pequeñas diferencias por redondeo
        if (abs($totalDetalle - $montoTotal) > 0.001) {
            throw new \InvalidArgumentException('El monto total debe coincidir con la suma de los detalles de pago');
        }

        $this->repo->beginTransaction();

        try {
            $idPago = $this->repo->createPayment([
                'id_venta' => $data['id_venta'],
                'fecha_pago' => $data['fecha_pago'],
                'monto_total' => $montoTotal,
                'tipo_pago_general' => $data['tipo_pago_general'] ?? 'Venta',
                'estado' => $data['estado'] ?? 'Confirmado',
                'observaciones' => $data['observaciones'] ?? null,
            ], $detalles, $usuarioId);

            $nuevoTotalPagado = $this->repo->getTotalPagadoPorVenta((int)$data['id_venta']);
            $estadoFactura = 'Vigente';
            if ($nuevoTotalPagado >= $venta->total_factura - 0.001) {
                $estadoFactura = 'Vigente';
            }
            $this->ventaRepo->update((int)$data['id_venta'], ['estado_factura' => $estadoFactura]);

            $this->repo->commit();
            return $idPago;
        } catch (\Throwable $e) {
            $this->repo->rollBack();
            // Log para ayudar a debug
            $logPath = __DIR__ . '/../../storage/logs/pago_errors.log';
            $msg = '[' . date('Y-m-d H:i:s') . '] Error registrarPago: ' . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n\n";
            @file_put_contents($logPath, $msg, FILE_APPEND);
            throw $e;
        }
    }
}
