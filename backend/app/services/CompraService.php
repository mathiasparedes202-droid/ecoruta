<?php

namespace App\Services;

use App\Models\Compra;
use App\Repositories\CompraRepository;
use App\Repositories\ProveedorRepository;
use App\Repositories\InsumoRepository;
use App\Repositories\ProductoRepository;
use App\Repositories\CompraHistorialRepository;
use App\Validators\CompraValidator;
use App\Validators\CompraEditValidator;

class CompraService
{
    private CompraRepository $compraRepo;
    private ProveedorRepository $proveedorRepo;
    private InsumoRepository $insumoRepo;
    private ProductoRepository $productoRepo;
    private CompraHistorialRepository $historialRepo;

    public function __construct()
    {
        $this->compraRepo = new CompraRepository();
        $this->proveedorRepo = new ProveedorRepository();
        $this->insumoRepo = new InsumoRepository();
        $this->productoRepo = new ProductoRepository();
        $this->historialRepo = new CompraHistorialRepository();
    }

    public function listarCompras(): array
    {
        return $this->compraRepo->all();
    }

    public function listarComprasPorProveedor(int $idProveedor): array
    {
        return $this->compraRepo->findByProveedor($idProveedor);
    }

    public function listarComprasPorInsumo(int $idInsumo): array
    {
        return $this->compraRepo->findByInsumo($idInsumo);
    }

    public function obtenerCompraConDetalles(int $id): ?array
    {
        return $this->compraRepo->findWithDetails($id);
    }

    public function registrarCompra(array $data, int $usuarioId): int
    {
        // Usar validador
        $errores = CompraValidator::validarDatosCompra($data);
        if (!empty($errores)) {
            throw new \InvalidArgumentException(implode('; ', $errores));
        }

        // Verificar que el proveedor existe
        $proveedor = $this->proveedorRepo->findById($data['id_proveedor']);
        if (!$proveedor) {
            throw new \Exception('El proveedor seleccionado no existe');
        }

        $details = $data['detalles'];

        // Validar y calcular detalles
        $totalCompra = 0;
        $iva5 = 0;
        $iva10 = 0;
        $subtotalIvaExenta = 0;
        $subtotalIva5 = 0;
        $subtotalIva10 = 0;

        foreach ($details as &$detalle) {
            // Verificar que el insumo existe
            $insumo = $this->insumoRepo->findById((int)($detalle['id_insumo'] ?? 0));
            if (!$insumo) {
                throw new \Exception('Uno de los insumos seleccionados no existe');
            }

            $cantidad = (float)($detalle['cantidad'] ?? 0);
            $precioUnitario = (float)($detalle['precio_unitario'] ?? 0);
            $ivaTipo = (int)($detalle['iva_tipo'] ?? 10);

            $this->insumoRepo->validarUnidadMedida((int)$detalle['id_insumo']);

            if ($cantidad <= 0) {
                throw new \Exception('La cantidad de cada detalle de compra debe ser mayor a 0');
            }

            if ($precioUnitario < 0) {
                throw new \Exception('El precio unitario de cada detalle de compra no puede ser negativo');
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

            $totalCompra += $subtotal;
        }

        // Calcular totales
        $totalIva = $iva5 + $iva10;
        $data['total_compra'] = $totalCompra + $totalIva;
        $data['iva_5'] = $iva5;
        $data['iva_10'] = $iva10;
        $data['subtotal_iva_exenta'] = $subtotalIvaExenta;
        $data['subtotal_iva_5'] = $subtotalIva5;
        $data['subtotal_iva_10'] = $subtotalIva10;
        $data['total_iva'] = $totalIva;

        // Liquidación IVA: ajustar según reglas fiscales (ejemplo: 30% deducible)
        $data['liquidacion_iva_5'] = $iva5 * 0.30;
        $data['liquidacion_iva_10'] = $iva10 * 0.30;
        $data['total_liquidacion'] = $data['liquidacion_iva_5'] + $data['liquidacion_iva_10'];

        // Valores por defecto
        $data['estado_factura'] = $data['estado_factura'] ?? 'Vigente';
        $data['tipo_comprobante'] = $data['tipo_comprobante'] ?? 'Factura';
        $data['condicion_compra'] = $data['condicion_compra'] ?? 'Contado';
        $data['creado_por'] = $usuarioId;

        $compraId = $this->compraRepo->create($data, $details);

        $this->actualizarStockInsumos($details, $compraId);

        // Actualizar costos de compra de los insumos
        $this->actualizarPreciosCompraInsumos($details);

        return $compraId;
    }

    public function actualizarCompra(int $id, array $data): bool
    {
        $errores = CompraEditValidator::validarDatosEdicion($data);
        if (!empty($errores)) {
            throw new \InvalidArgumentException(implode('; ', $errores));
        }

        $compra = $this->compraRepo->findById($id);
        if (!$compra) {
            throw new \Exception('La compra no existe');
        }

        if (isset($data['id_proveedor'])) {
            $proveedor = $this->proveedorRepo->findById((int)$data['id_proveedor']);
            if (!$proveedor) {
                throw new \Exception('El proveedor seleccionado no existe');
            }
        }

        $detallesNuevos = $data['detalles'] ?? null;
        unset($data['detalles']);

        try {
            $this->compraRepo->beginTransaction();

            if ($detallesNuevos !== null) {
                $detallesActuales = $this->compraRepo->getDetallesByCompraId($id);
                $this->ajustarStockPorCambiosDetalles($detallesActuales, $detallesNuevos);

                $this->compraRepo->deleteDetallesByCompraId($id);
                $this->compraRepo->insertDetalles($id, $detallesNuevos);

                $totales = $this->calcularTotalesDeDetalles($detallesNuevos);
                $data['total_compra'] = $totales['total_compra'];
                $data['total_iva'] = $totales['total_iva'];
            }

            if ($detallesNuevos !== null) {
                $this->actualizarPreciosCompraInsumos($detallesNuevos);
            }

            $resultado = $this->compraRepo->update($id, $data);
            if (!$resultado) {
                throw new \Exception('No se pudo actualizar la compra');
            }

            $this->compraRepo->commit();
            return true;
        } catch (\Throwable $e) {
            $this->compraRepo->rollBack();
            throw $e;
        }
    }

    private function calcularTotalesDeDetalles(array $detalles): array
    {
        $subtotal = 0;
        $totalIva = 0;

        foreach ($detalles as $detalle) {
            $cantidad = $detalle['cantidad'];
            $precioUnitario = $detalle['precio_unitario'];
            $descuento = isset($detalle['descuento']) ? $detalle['descuento'] : 0;
            $lineTotal = ($cantidad * $precioUnitario) - $descuento;
            $subtotal += $lineTotal;
            $totalIva += $lineTotal * 0.1;
        }

        return [
            'total_compra' => $subtotal + $totalIva,
            'total_iva' => $totalIva,
        ];
    }

    private function ajustarStockPorCambiosDetalles(array $detallesActuales, array $detallesNuevos): void
    {
        foreach ($detallesActuales as $detalle) {
            $idInsumo = (int)($detalle['id_insumo'] ?? 0);
            $cantidad = (float)($detalle['cantidad'] ?? 0);

            if ($idInsumo <= 0 || $cantidad <= 0) {
                continue;
            }

            $insumo = $this->insumoRepo->findById($idInsumo);
            if (!$insumo) {
                throw new \Exception('No se encontró el insumo asociado a la compra');
            }

            if ((float)$insumo->stock < $cantidad) {
                throw new \Exception('No hay suficiente stock para ajustar la compra');
            }

            $this->insumoRepo->decrementarStock($idInsumo, $cantidad);
        }

        foreach ($detallesNuevos as $detalle) {
            $this->insumoRepo->incrementarStock((int)($detalle['id_insumo'] ?? 0), (float)($detalle['cantidad'] ?? 0));
        }
    }

    private function actualizarStockInsumos(array $details, int $compraId): void
    {
        foreach ($details as $detalle) {
            $idInsumo = (int)($detalle['id_insumo'] ?? 0);
            $cantidad = (float)($detalle['cantidad'] ?? 0);
            if ($idInsumo <= 0 || $cantidad <= 0) {
                continue;
            }

            $this->insumoRepo->incrementarStock($idInsumo, $cantidad);
            $this->insumoRepo->registrarMovimientoInsumo($idInsumo, 'Compra', $cantidad, $compraId, null, 'Compra de insumo', null);
        }
    }

    private function actualizarPreciosCompraInsumos(array $details): void
    {
        foreach ($details as $detalle) {
            $idInsumo = (int)($detalle['id_insumo'] ?? 0);
            if ($idInsumo <= 0) {
                continue;
            }

            $precioUnitario = isset($detalle['precio_unitario']) ? (float)$detalle['precio_unitario'] : 0;
            $this->insumoRepo->actualizarPrecioCompra($idInsumo, $precioUnitario);
            $this->productoRepo->actualizarCostosProductosPorInsumo($idInsumo);
        }
    }

    public function anularCompra(int $id, int $usuarioId, string $motivo = '', bool $crearNotaCredito = false): bool
    {
        // Verificar que la compra existe
        $compra = $this->compraRepo->findById($id);
        if (!$compra) {
            throw new \Exception('La compra no existe');
        }

        // Verificar que no esté ya anulada
        if (strtolower($compra->estado_factura) === 'anulada') {
            throw new \Exception('La compra ya está anulada');
        }

        // Validar motivo
        if (empty(trim($motivo))) {
            throw new \Exception('El motivo de anulación es requerido');
        }

        // Verificar si la compra está pagada
        $estaPagada = strtolower($compra->estado_pago) === 'pagada' || $compra->monto_pagado > 0;

        // Nota: Ya no se requiere crear nota de crédito para compras pagadas

        $detalles = $this->compraRepo->getDetallesByCompraId($id);

        try {
            $this->compraRepo->beginTransaction();

            // Revertir stock
            foreach ($detalles as $detalle) {
                $resultado = $this->insumoRepo->decrementarStock($detalle['id_insumo'], $detalle['cantidad']);
                if (!$resultado) {
                    throw new \Exception('No se pudo actualizar el stock del insumo');
                }
            }

            // Anular la compra con el motivo
            $success = $this->compraRepo->anular($id, $usuarioId, $motivo);
            if (!$success) {
                throw new \Exception('No se pudo anular la compra');
            }

            // Registrar en historial con motivo de anulación
            $this->historialRepo->registrarCambio(
                $id,
                'Anulada',
                $usuarioId,
                'Activa',
                'Anulada',
                $motivo,
                $crearNotaCredito
            );

            // Si está pagada y se crea nota de crédito, registrar eso en el historial
            if ($estaPagada && $crearNotaCredito) {
                // TODO: Crear nota de crédito de proveedor
                // $this->crearNotaCreditoProveedor($compra, $usuarioId, $motivo);

                // Registrar en historial
                $this->historialRepo->registrarCambio(
                    $id,
                    'Nota de Crédito',
                    $usuarioId,
                    null,
                    'Creada',
                    'Nota de crédito creada por anulación de compra pagada',
                    true
                );
            }

            $this->compraRepo->commit();
            return true;
        } catch (\Throwable $e) {
            $this->compraRepo->rollBack();
            throw $e;
        }
    }

    /**
     * Crear una nota de crédito de proveedor cuando se anula una compra pagada
     */
    private function crearNotaCreditoProveedor(\App\Models\Compra $compra, int $usuarioId, string $motivo): void
    {
        // La nota de crédito se registra como un tipo de comprobante en la tabla compra
        // o se puede crear como un pago negativo/devolución
        // Por ahora, registramos el evento en el historial

        // En una implementación más completa, esto podría:
        // 1. Crear un nuevo registro en tabla nota_credito_proveedor
        // 2. O crear un pago negativo
        // 3. O crear una compra de tipo "Nota de Crédito"

        // Para esta versión, simplemente registramos el evento
    }
}
