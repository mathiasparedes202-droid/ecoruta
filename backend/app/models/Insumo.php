<?php

namespace App\Models;

class Insumo
{
    public ?int $id_insumo = null;
    public string $nombre_insumo;
    public ?string $descripcion = null;
    public int $id_tipo_insumo;
    public ?int $id_variedad = null;
    public ?int $id_color = null;
    public int $id_categoria;
    public int $id_unidad;
    public ?float $precio_compra = null;
    public float $stock = 0;
    public int $estado = 1;
    public ?string $fecha_creacion = null;
    public ?int $creado_por = null;

    public function __construct(?array $data = null)
    {
        if ($data) {
            $this->id_insumo = $data['id_insumo'] ?? null;
            $this->nombre_insumo = $data['nombre_insumo'] ?? '';
            $this->descripcion = $data['descripcion'] ?? null;
            $this->id_tipo_insumo = $data['id_tipo_insumo'] ?? 0;
            $this->id_variedad = $data['id_variedad'] ?? null;
            $this->id_color = $data['id_color'] ?? null;
            $this->id_categoria = $data['id_categoria'] ?? 0;
            $this->id_unidad = $data['id_unidad'] ?? 0;
            $this->precio_compra = isset($data['precio_compra']) && $data['precio_compra'] !== '' ? (float) $data['precio_compra'] : null;
            $this->stock = isset($data['stock']) ? (float) $data['stock'] : 0;
            $this->estado = $data['estado'] ?? 1;
            $this->fecha_creacion = $data['fecha_creacion'] ?? null;
            $this->creado_por = $data['creado_por'] ?? null;
        }
    }
}
