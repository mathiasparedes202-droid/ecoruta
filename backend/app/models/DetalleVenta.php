<?php

namespace App\Models;

class DetalleVenta
{
    public ?int $id_detalle_venta = null;
    public int $id_venta;
    public int $id_producto;
    public int $cantidad;
    public float $precio_unitario = 0;
    public ?int $iva_tipo = null;
    public float $subtotal = 0;
    public ?string $detalle_produccion = null;
    public ?string $detalle_receta = null;
    public ?string $fecha_creacion = null;
    public ?int $creado_por = null;

    public function __construct(?array $data = null)
    {
        if ($data) {
            $this->id_detalle_venta = $data['id_detalle_venta'] ?? null;
            $this->id_venta = $data['id_venta'] ?? 0;
            $this->id_producto = $data['id_producto'] ?? 0;
            $this->cantidad = (int)($data['cantidad'] ?? 0);
            $this->precio_unitario = (float)($data['precio_unitario'] ?? 0);
            $this->iva_tipo = $data['iva_tipo'] ?? null;
            $this->subtotal = (float)($data['subtotal'] ?? 0);
            $this->detalle_produccion = $data['detalle_produccion'] ?? null;
            $this->detalle_receta = $data['detalle_receta'] ?? null;
            $this->fecha_creacion = $data['fecha_creacion'] ?? null;
            $this->creado_por = $data['creado_por'] ?? null;
        }
    }
}
