<?php

namespace App\Models;

class Proveedor
{
    public ?int $id_proveedor = null;
    public string $razon_social;
    public string $ruc;
    public ?string $direccion = null;
    public ?string $telefono = null;
    public ?string $celular = null;
    public ?string $correo = null;
    public int $estado = 1;
    public ?string $fecha_creacion = null;
    public ?int $creado_por = null;

    public function __construct(?array $data = null)
    {
        if ($data) {
            $this->id_proveedor = $data['id_proveedor'] ?? null;
            $this->razon_social = $data['razon_social'] ?? '';
            $this->ruc = $data['ruc'] ?? '';
            $this->direccion = $data['direccion'] ?? null;
            $this->telefono = $data['telefono'] ?? null;
            $this->celular = $data['celular'] ?? null;
            $this->correo = $data['correo'] ?? null;
            $this->estado = $data['estado'] ?? 1;
            $this->fecha_creacion = $data['fecha_creacion'] ?? null;
            $this->creado_por = $data['creado_por'] ?? null;
        }
    }
}
