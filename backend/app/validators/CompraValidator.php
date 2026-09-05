<?php

namespace App\Validators;

class CompraValidator
{
    public static function validarDatosCompra(array $data): array
    {
        $errores = [];

        // Validar proveedor
        if (empty($data['id_proveedor']) || !is_numeric($data['id_proveedor'])) {
            $errores[] = 'Debe seleccionar un proveedor válido';
        }

        // Validar número de factura
        if (empty($data['numero_factura']) || !preg_match('/^\d{3}-\d{3}-\d{7}$/', $data['numero_factura'])) {
            $errores[] = 'Número de factura debe tener formato 001-001-0000001';
        }

        // Validar timbrado
        if (empty($data['timbrado']) || !preg_match('/^\d{8}$/', $data['timbrado'])) {
            $errores[] = 'Timbrado debe ser un número de 8 dígitos';
        }

        // Validar fecha de emisión
        if (!empty($data['fecha_emision'])) {
            $fecha = strtotime($data['fecha_emision']);
            if (!$fecha || $fecha > time()) {
                $errores[] = 'Fecha de emisión inválida o futura';
            }
        }

        // Validar detalles
        if (empty($data['detalles']) || !is_array($data['detalles'])) {
            $errores[] = 'La compra debe incluir detalles';
        } else {
            foreach ($data['detalles'] as $index => $detalle) {
                $erroresDetalle = self::validarDetalleCompra($detalle, $index + 1);
                $errores = array_merge($errores, $erroresDetalle);
            }
        }

        return $errores;
    }

    private static function validarDetalleCompra(array $detalle, int $numero): array
    {
        $errores = [];

        if (empty($detalle['id_insumo']) || !is_numeric($detalle['id_insumo'])) {
            $errores[] = "Detalle {$numero}: Insumo inválido";
        }

        if (!isset($detalle['cantidad']) || !is_numeric($detalle['cantidad']) || $detalle['cantidad'] <= 0) {
            $errores[] = "Detalle {$numero}: Cantidad debe ser un número positivo";
        }

        if (!isset($detalle['precio_unitario']) || !is_numeric($detalle['precio_unitario']) || $detalle['precio_unitario'] < 0) {
            $errores[] = "Detalle {$numero}: Precio unitario debe ser un número no negativo";
        }

        if (isset($detalle['iva_tipo']) && !in_array($detalle['iva_tipo'], [0, 5, 10], true)) {
            $errores[] = "Detalle {$numero}: Tipo de IVA debe ser 0, 5 o 10";
        }

        return $errores;
    }
}
