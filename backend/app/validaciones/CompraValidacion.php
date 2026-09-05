<?php

namespace App\Validaciones;

class CompraValidacion
{
    private const TIPOS_COMPROBANTE = ['Factura', 'Boleta de Entrega', 'Nota de Remisión'];
    private const IVA_TIPOS = [0, 5, 10];

    public static function validarDatosCompra(array $data): array
    {
        $errores = [];

        try {
            ValidacionBase::validarEnteroPositivo($data['id_proveedor'] ?? null, 'id_proveedor');
        } catch (\Exception $e) {
            $errores[] = $e->getMessage();
        }

        if (empty($data['tipo_comprobante']) || !in_array($data['tipo_comprobante'], self::TIPOS_COMPROBANTE, true)) {
            $errores[] = 'El tipo de comprobante no es válido.';
        }

        if (empty($data['numero_factura'])) {
            $errores[] = 'El número de comprobante es obligatorio.';
        } else {
            try {
                ValidacionBase::validarNumeroFactura($data['numero_factura']);
            } catch (\Exception $e) {
                $errores[] = $e->getMessage();
            }
        }

        if ($data['tipo_comprobante'] !== 'Boleta de Entrega') {
            if (empty($data['timbrado'])) {
                $errores[] = 'El timbrado es obligatorio para el tipo de comprobante seleccionado.';
            } else {
                try {
                    ValidacionBase::validarTimbrado($data['timbrado']);
                } catch (\Exception $e) {
                    $errores[] = $e->getMessage();
                }
            }
        }

        if ($data['tipo_comprobante'] === 'Nota de Remisión') {
            if (empty(trim((string)($data['motivo_remision'] ?? '')))) {
                $errores[] = 'El motivo de remisión es obligatorio para nota de remisión.';
            } elseif (mb_strlen($data['motivo_remision']) > 255) {
                $errores[] = 'El motivo de remisión no puede superar los 255 caracteres.';
            }
        }

        try {
            ValidacionBase::validarFecha($data['fecha_emision'] ?? '', 'fecha_emision');
        } catch (\Exception $e) {
            $errores[] = $e->getMessage();
        }

        if (isset($data['fecha_vencimiento']) && trim((string)$data['fecha_vencimiento']) !== '') {
            try {
                ValidacionBase::validarFechaOpcional($data['fecha_vencimiento'], 'fecha_vencimiento');
            } catch (\Exception $e) {
                $errores[] = $e->getMessage();
            }
        }

        if (!isset($data['detalles']) || !is_array($data['detalles']) || count($data['detalles']) === 0) {
            $errores[] = 'Debe agregar al menos un detalle de compra.';
        } else {
            foreach ($data['detalles'] as $index => $detalle) {
                $detalleErrores = self::validarDetalle($detalle, $index + 1);
                $errores = array_merge($errores, $detalleErrores);
            }
        }

        return $errores;
    }

    public static function validarDetalle(array $detalle, int $indice): array
    {
        $errores = [];

        try {
            ValidacionBase::validarEnteroPositivo($detalle['id_insumo'] ?? null, "id_insumo del detalle {$indice}");
        } catch (\Exception $e) {
            $errores[] = $e->getMessage();
        }

        try {
            ValidacionBase::validarNumeroPositivo($detalle['cantidad'] ?? null, "cantidad del detalle {$indice}");
            if ((float)$detalle['cantidad'] <= 0) {
                throw new \Exception("La cantidad del detalle {$indice} debe ser mayor a 0.");
            }
        } catch (\Exception $e) {
            $errores[] = $e->getMessage();
        }

        try {
            ValidacionBase::validarNumeroPositivo($detalle['precio_unitario'] ?? null, "precio_unitario del detalle {$indice}");
            if ((float)$detalle['precio_unitario'] <= 0) {
                throw new \Exception("El precio unitario del detalle {$indice} debe ser mayor a 0.");
            }
        } catch (\Exception $e) {
            $errores[] = $e->getMessage();
        }

        if (isset($detalle['iva_tipo'])) {
            $ivaTipo = (int)$detalle['iva_tipo'];
            if (!in_array($ivaTipo, self::IVA_TIPOS, true)) {
                $errores[] = "El iva_tipo del detalle {$indice} debe ser 0, 5 o 10.";
            }
        }

        if (isset($detalle['descuento']) && trim((string)$detalle['descuento']) !== '') {
            try {
                ValidacionBase::validarNumeroPositivo($detalle['descuento'], "descuento del detalle {$indice}");
            } catch (\Exception $e) {
                $errores[] = $e->getMessage();
            }
        }

        return $errores;
    }
}
