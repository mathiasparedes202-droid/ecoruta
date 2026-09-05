<?php

namespace App\Models;

use App\Models\Proveedor;

class Compra
{
    public ?int $id_compra = null;
    public int $id_proveedor;
    public string $numero_factura;
    public string $timbrado;
    public ?string $fecha_emision = null;
    public string $estado_factura;
    public string $tipo_comprobante;
    public string $condicion_compra;
    public ?int $plazo = null;
    public ?string $observaciones = null;
    public ?float $total_compra = 0;
    public ?float $iva_5 = 0;
    public ?float $iva_10 = 0;
    public ?float $subtotal_iva_exenta = 0;
    public ?float $subtotal_iva_5 = 0;
    public ?float $subtotal_iva_10 = 0;
    public ?float $total_iva = 0;
    public ?float $liquidacion_iva_5 = 0;
    public ?float $liquidacion_iva_10 = 0;
    public ?float $total_liquidacion = 0;
    public ?string $fecha_creacion = null;
    public ?int $creado_por = null;
    public ?string $estado_pago = 'No pagada';
    public ?float $monto_pagado = 0;
    public ?string $fecha_ultima_modificacion = null;
    public ?int $modificado_por = null;
    public ?string $fecha_anulacion = null;
    public ?int $anulado_por = null;
    public ?string $motivo_anulacion = null;
    public ?string $nombre_proveedor = null;
    public ?string $ruc_proveedor = null;
    public ?Proveedor $proveedor = null;

    public function __construct(?array $data = null)
    {
        if ($data) {
            $this->id_compra = $data['id_compra'] ?? null;
            $this->id_proveedor = $data['id_proveedor'] ?? 0;
            $this->numero_factura = $data['numero_factura'] ?? '';
            $this->timbrado = $data['timbrado'] ?? '';
            $this->fecha_emision = $data['fecha_emision'] ?? null;
            $this->estado_factura = $data['estado_factura'] ?? 'Vigente';
            $this->tipo_comprobante = $data['tipo_comprobante'] ?? 'Factura';
            $this->condicion_compra = $data['condicion_compra'] ?? 'Contado';
            $this->plazo = $data['plazo'] ?? null;
            $this->observaciones = $data['observaciones'] ?? null;
            $this->total_compra = isset($data['total_compra']) ? (float) $data['total_compra'] : 0;
            $this->iva_5 = isset($data['iva_5']) ? (float) $data['iva_5'] : 0;
            $this->iva_10 = isset($data['iva_10']) ? (float) $data['iva_10'] : 0;
            $this->subtotal_iva_exenta = isset($data['subtotal_iva_exenta']) ? (float) $data['subtotal_iva_exenta'] : 0;
            $this->subtotal_iva_5 = isset($data['subtotal_iva_5']) ? (float) $data['subtotal_iva_5'] : 0;
            $this->subtotal_iva_10 = isset($data['subtotal_iva_10']) ? (float) $data['subtotal_iva_10'] : 0;
            $this->total_iva = isset($data['total_iva']) ? (float) $data['total_iva'] : 0;
            $this->liquidacion_iva_5 = isset($data['liquidacion_iva_5']) ? (float) $data['liquidacion_iva_5'] : 0;
            $this->liquidacion_iva_10 = isset($data['liquidacion_iva_10']) ? (float) $data['liquidacion_iva_10'] : 0;
            $this->total_liquidacion = isset($data['total_liquidacion']) ? (float) $data['total_liquidacion'] : 0;
            $this->fecha_creacion = $data['fecha_creacion'] ?? null;
            $this->creado_por = $data['creado_por'] ?? null;
            $this->estado_pago = $data['estado_pago'] ?? 'No pagada';
            $this->monto_pagado = isset($data['monto_pagado']) ? (float) $data['monto_pagado'] : 0;
            $this->fecha_ultima_modificacion = $data['fecha_ultima_modificacion'] ?? null;
            $this->modificado_por = $data['modificado_por'] ?? null;
            $this->fecha_anulacion = $data['fecha_anulacion'] ?? null;
            $this->anulado_por = $data['anulado_por'] ?? null;
            $this->motivo_anulacion = $data['motivo_anulacion'] ?? null;
            $this->nombre_proveedor = $data['nombre_proveedor'] ?? null;
            $this->ruc_proveedor = $data['ruc_proveedor'] ?? null;

            if (isset($data['nombre_proveedor'])) {
                $this->proveedor = new Proveedor([
                    'id_proveedor' => $data['id_proveedor'],
                    'razon_social' => $data['nombre_proveedor'] ?? '',
                ]);
            }
        }
    }
}
