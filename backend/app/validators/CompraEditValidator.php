<?php

namespace App\Validators;

class CompraEditValidator
{
    public static function validarDatosEdicion(array $data): array
    {
        $errores = [];

        // Solo permitir editar campos seguros de cabecera de compra
        $camposPermitidos = ['id_proveedor', 'numero_factura', 'timbrado', 'fecha_emision', 'observaciones', 'detalles'];

        foreach ($data as $campo => $valor) {
            if (!in_array($campo, $camposPermitidos)) {
                $errores[] = "Campo '$campo' no puede ser modificado";
            }
        }

        // Validar id_proveedor si se proporciona
        if (isset($data['id_proveedor'])) {
            if (!filter_var($data['id_proveedor'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])) {
                $errores[] = 'ID de proveedor inválido';
            }
        }

        // Validar número de factura si se proporciona
        if (isset($data['numero_factura'])) {
            if (empty(trim($data['numero_factura']))) {
                $errores[] = 'El número de factura no puede estar vacío';
            }
        }

        // Validar fecha de emisión si se proporciona
        if (isset($data['fecha_emision'])) {
            if (!empty($data['fecha_emision'])) {
                $fecha = strtotime($data['fecha_emision']);
                if (!$fecha || $fecha > time()) {
                    $errores[] = 'Fecha de emisión inválida o futura';
                }
            }
        }

        // Validar observaciones
        if (isset($data['observaciones'])) {
            if (strlen($data['observaciones']) > 500) {
                $errores[] = 'Observaciones no pueden exceder 500 caracteres';
            }
        }

        // Validar detalles si se proporcionan
        if (isset($data['detalles'])) {
            if (!is_array($data['detalles']) || empty($data['detalles'])) {
                $errores[] = 'La compra debe tener al menos un detalle';
            } else {
                foreach ($data['detalles'] as $index => $detalle) {
                    if (!isset($detalle['id_insumo']) || !filter_var($detalle['id_insumo'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])) {
                        $errores[] = "Detalle #" . ($index + 1) . ": insumo inválido";
                    }

                    if (!isset($detalle['cantidad']) || !is_numeric($detalle['cantidad']) || $detalle['cantidad'] <= 0) {
                        $errores[] = "Detalle #" . ($index + 1) . ": cantidad inválida";
                    }

                    if (!isset($detalle['precio_unitario']) || !is_numeric($detalle['precio_unitario']) || $detalle['precio_unitario'] < 0) {
                        $errores[] = "Detalle #" . ($index + 1) . ": precio unitario inválido";
                    }

                    if (isset($detalle['descuento']) && (!is_numeric($detalle['descuento']) || $detalle['descuento'] < 0)) {
                        $errores[] = "Detalle #" . ($index + 1) . ": descuento inválido";
                    }
                }
            }
        }

        return $errores;
    }
}
