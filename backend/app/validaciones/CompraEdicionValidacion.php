<?php

namespace App\Validaciones;

class CompraEdicionValidacion
{
    private const TIPOS_COMPROBANTE = ['Factura', 'Boleta de Entrega', 'Nota de Remisión'];
    private const IVA_TIPOS = [0, 5, 10];

    public static function validarDatosEdicion(array $data): array
    {
        $errores = [];

        if (array_key_exists('id_proveedor', $data)) {
            try {
                ValidacionBase::validarEnteroPositivo($data['id_proveedor'], 'id_proveedor');
            } catch (\Exception $e) {
                $errores[] = $e->getMessage();
            }
        }

        if (array_key_exists('tipo_comprobante', $data) && !in_array($data['tipo_comprobante'], self::TIPOS_COMPROBANTE, true)) {
            $errores[] = 'El tipo de comprobante no es válido.';
        }

        if (array_key_exists('numero_factura', $data)) {
            try {
                ValidacionBase::validarNumeroFactura($data['numero_factura']);
            } catch (\Exception $e) {
                $errores[] = $e->getMessage();
            }
        }

        if (array_key_exists('timbrado', $data)) {
            $tipoComprobante = $data['tipo_comprobante'] ?? null;
            if ($tipoComprobante !== 'Boleta de Entrega' && trim((string)$data['timbrado']) !== '') {
                try {
                    ValidacionBase::validarTimbrado($data['timbrado']);
                } catch (\Exception $e) {
                    $errores[] = $e->getMessage();
                }
            }
        }

        if ($data['tipo_comprobante'] === 'Nota de Remisión' && array_key_exists('motivo_remision', $data)) {
            if (trim((string)$data['motivo_remision']) === '') {
                $errores[] = 'El motivo de remisión no puede estar vacío.';
            } elseif (mb_strlen($data['motivo_remision']) > 255) {
                $errores[] = 'El motivo de remisión no puede superar los 255 caracteres.';
            }
        }

        if (array_key_exists('fecha_emision', $data)) {
            try {
                ValidacionBase::validarFecha($data['fecha_emision'], 'fecha_emision');
            } catch (\Exception $e) {
                $errores[] = $e->getMessage();
            }
        }

        if (array_key_exists('fecha_vencimiento', $data) && trim((string)$data['fecha_vencimiento']) !== '') {
            try {
                ValidacionBase::validarFechaOpcional($data['fecha_vencimiento'], 'fecha_vencimiento');
            } catch (\Exception $e) {
                $errores[] = $e->getMessage();
            }
        }

        if (array_key_exists('detalles', $data)) {
            if (!is_array($data['detalles']) || count($data['detalles']) === 0) {
                $errores[] = 'Debe agregar al menos un detalle de compra.';
            } else {
                foreach ($data['detalles'] as $index => $detalle) {
                    $errores = array_merge($errores, self::validarDetalle($detalle, $index + 1));
                }
            }
        }

        return $errores;
    }

    public static function validarDetalle(array $detalle, int $indice): array
    {
        $errores = [];

        if (array_key_exists('id_insumo', $detalle)) {
            try {
                ValidacionBase::validarEnteroPositivo($detalle['id_insumo'], "id_insumo del detalle {$indice}");
            } catch (\Exception $e) {
                $errores[] = $e->getMessage();
            }
        }

        if (array_key_exists('cantidad', $detalle)) {
            try {
                ValidacionBase::validarNumeroPositivo($detalle['cantidad'], "cantidad del detalle {$indice}");
                if ((float)$detalle['cantidad'] <= 0) {
                    $errores[] = "La cantidad del detalle {$indice} debe ser mayor a 0.";
                }
            } catch (\Exception $e) {
                $errores[] = $e->getMessage();
            }
        }

        if (array_key_exists('precio_unitario', $detalle)) {
            try {
                ValidacionBase::validarNumeroPositivo($detalle['precio_unitario'], "precio_unitario del detalle {$indice}");
                if ((float)$detalle['precio_unitario'] <= 0) {
                    $errores[] = "El precio unitario del detalle {$indice} debe ser mayor a 0.";
                }
            } catch (\Exception $e) {
                $errores[] = $e->getMessage();
            }
        }

        if (array_key_exists('iva_tipo', $detalle)) {
            $ivaTipo = (int)$detalle['iva_tipo'];
            if (!in_array($ivaTipo, self::IVA_TIPOS, true)) {
                $errores[] = "El iva_tipo del detalle {$indice} debe ser 0, 5 o 10.";
            }
        }

        if (array_key_exists('descuento', $detalle) && trim((string)$detalle['descuento']) !== '') {
            try {
                ValidacionBase::validarNumeroPositivo($detalle['descuento'], "descuento del detalle {$indice}");
            } catch (\Exception $e) {
                $errores[] = $e->getMessage();
            }
        }

        return $errores;
    }
}
