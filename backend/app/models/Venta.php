<?php

namespace App\Models;

class Venta
{
    public ?int $id_venta = null;
    public int $id_cliente;
    public ?int $id_pedido = null;
    public string $numero_factura;
    public string $timbrado;
    public string $fecha_emision;
    public string $estado_factura = 'Vigente'; // 'Vigente' o 'Anulada'
    public ?string $observaciones = null;
    public string $tipo_comprobante = 'Factura'; // 'Ticket' o 'Factura'
    public ?string $numero_comprobante = null;
    public string $delivery_option = 'Retiro';
    public ?string $direccion_entrega = null;
    public float $total_factura = 0;
    public float $iva_5 = 0;
    public float $iva_10 = 0;
    public float $subtotal_iva_exenta = 0;
    public float $subtotal_iva_5 = 0;
    public float $subtotal_iva_10 = 0;
    public float $total_iva = 0;
    public float $liquidacion_iva_5 = 0;
    public float $liquidacion_iva_10 = 0;
    public float $total_liquidacion = 0;
    public string $condicion_venta = 'Contado'; // 'Contado' o 'Crédito'
    public ?int $plazo = null;
    public ?int $creado_por = null;
    // Campos adicionales provenientes de joins (cliente)
    public ?string $nombre_cliente = null;
    public ?string $ruc_ci = null;

    public function __construct(?array $data = null)
    {
        if ($data) {
            $this->id_venta = $data['id_venta'] ?? null;
            $this->id_cliente = $data['id_cliente'] ?? 0;
            $this->id_pedido = $data['id_pedido'] ?? null;
            $this->numero_factura = $data['numero_factura'] ?? '';
            $this->timbrado = $data['timbrado'] ?? '';
            $this->fecha_emision = $data['fecha_emision'] ?? date('Y-m-d H:i:s');
            $this->estado_factura = $data['estado_factura'] ?? 'Vigente';
            $this->observaciones = $data['observaciones'] ?? null;
            $this->tipo_comprobante = $data['tipo_comprobante'] ?? 'Factura';
            $this->numero_comprobante = $data['numero_comprobante'] ?? null;
            $this->delivery_option = $data['delivery_option'] ?? 'Retiro';
            $this->direccion_entrega = $data['direccion_entrega'] ?? null;
            $this->total_factura = (float)($data['total_factura'] ?? 0);
            $this->iva_5 = (float)($data['iva_5'] ?? 0);
            $this->iva_10 = (float)($data['iva_10'] ?? 0);
            $this->subtotal_iva_exenta = (float)($data['subtotal_iva_exenta'] ?? 0);
            $this->subtotal_iva_5 = (float)($data['subtotal_iva_5'] ?? 0);
            $this->subtotal_iva_10 = (float)($data['subtotal_iva_10'] ?? 0);
            $this->total_iva = (float)($data['total_iva'] ?? 0);
            $this->liquidacion_iva_5 = (float)($data['liquidacion_iva_5'] ?? 0);
            $this->liquidacion_iva_10 = (float)($data['liquidacion_iva_10'] ?? 0);
            $this->total_liquidacion = (float)($data['total_liquidacion'] ?? 0);
            $this->condicion_venta = $data['condicion_venta'] ?? 'Contado';
            $this->plazo = $data['plazo'] ?? null;
            $this->creado_por = $data['creado_por'] ?? null;
            $this->nombre_cliente = $data['nombre_cliente'] ?? null;
            $this->ruc_ci = $data['ruc_ci'] ?? null;
        }
    }
}
