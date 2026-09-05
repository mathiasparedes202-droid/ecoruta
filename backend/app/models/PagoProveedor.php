<?php

namespace App\Models;

class PagoProveedor
{
    public ?int $id_detalle = null;
    public int $id_pago_proveedor;
    public string $metodo_pago;
    public ?string $numero_comprobante = null;
    public ?string $banco = null;
    public ?string $fecha_vencimiento = null;
    public ?string $estado_pago = null;
    public ?string $observaciones = null;
    public ?string $fecha_creacion = null;
    public ?int $creado_por = null;

    // Campos de cabecera
    public ?string $fecha_pago = null;
    public ?string $estado = null;
    public ?string $tipo_pago = null;
    public ?float $monto_pagado = null;
    public ?float $monto_total = null;
    public ?string $tipo_pago_general = null;

    // Campos relacionados
    public ?string $numero_factura = null;
    public ?string $condicion_compra = null;
    public ?string $proveedor = null;
    public ?string $usuario_nombre = null;

    public function __construct(?array $data = null)
    {
        if ($data) {
            $this->id_detalle = $data['id_detalle'] ?? null;
            $this->id_pago_proveedor = $data['id_pago_proveedor'] ?? 0;
            $this->metodo_pago = $data['metodo_pago'] ?? '';
            $this->numero_comprobante = $data['numero_comprobante'] ?? null;
            $this->banco = $data['banco'] ?? null;
            $this->fecha_vencimiento = $data['fecha_vencimiento'] ?? null;
            $this->estado_pago = $data['estado_pago'] ?? null;
            $this->observaciones = $data['observaciones'] ?? null;
            $this->fecha_creacion = $data['fecha_creacion'] ?? null;
            $this->creado_por = $data['creado_por'] ?? null;
            $this->usuario_nombre = $data['usuario_nombre'] ?? null;

            // Campos de cabecera
            $this->fecha_pago = $data['fecha_pago'] ?? null;
            $this->estado = $data['estado'] ?? null;
            $this->tipo_pago = $data['tipo_pago'] ?? null;
            $this->monto_pagado = isset($data['monto_pagado']) ? (float)$data['monto_pagado'] : null;
            $this->monto_total = $data['monto_total'] ?? null;
            $this->tipo_pago_general = $data['tipo_pago_general'] ?? null;

            // Campos relacionados
            $this->numero_factura = $data['numero_factura'] ?? null;
            $this->condicion_compra = $data['condicion_compra'] ?? null;
            $this->proveedor = $data['proveedor'] ?? null;
        }
    }
}
