<?php

namespace App\Models;

class Producto
{
    public int $id_producto;
    public string $nombre_producto;
    public ?string $descripcion;
    public ?string $tipo_producto;
    public float $precio_base;
    public float $costo_produccion;
    public int $estado;
    public int $es_personalizable;
    public ?string $imagen;
    public ?string $creado_por;

    public function __construct(array $data)
    {
        $this->id_producto = (int)($data['id_producto'] ?? 0);
        $this->nombre_producto = $data['nombre_producto'] ?? '';
        $this->descripcion = $data['descripcion'] ?? null;
        $this->tipo_producto = $data['tipo_producto'] ?? null;
        $this->precio_base = isset($data['precio_base']) ? (float)$data['precio_base'] : 0.0;
        $this->costo_produccion = isset($data['costo_produccion']) ? (float)$data['costo_produccion'] : 0.0;
        $this->estado = isset($data['estado']) ? (int)$data['estado'] : 0;
        $this->es_personalizable = isset($data['es_personalizable']) ? (int)$data['es_personalizable'] : 0;
        $this->imagen = $data['imagen'] ?? null;
        $this->creado_por = $data['creado_por'] ?? null;
    }

    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
