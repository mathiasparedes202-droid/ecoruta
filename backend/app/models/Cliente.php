<?php

namespace App\Models;

class Cliente
{
    public ?int $id_cliente = null;
    // Campos históricos / compatibilidad
    public ?string $nombre = null;
    public ?string $apellido = null;
    public ?string $ci_ruc = null;

    // Campos alternativos usados en otras migraciones
    public ?string $ruc_ci = null;
    public ?string $razon_social = null;
    public ?string $celular = null;
    public int $estado = 1;
    public ?string $fecha_creacion = null;
    public ?int $creado_por = null;

    public function __construct(?array $data = null)
    {
        if ($data) {
            $this->id_cliente = $data['id_cliente'] ?? null;

            // Nombres y apellidos (compatibilidad con frontend)
            $this->nombre = $data['nombre'] ?? ($data['razon_social'] ?? null);
            $this->apellido = $data['apellido'] ?? null;

            // CI/RUC (varía según esquema)
            $this->ci_ruc = $data['ci_ruc'] ?? ($data['ruc_ci'] ?? null);
            $this->ruc_ci = $data['ruc_ci'] ?? ($data['ci_ruc'] ?? null);
            $this->razon_social = $data['razon_social'] ?? ($data['nombre'] ?? null);

            $this->celular = $data['celular'] ?? null;
            $this->estado = $data['estado'] ?? 1;
            $this->fecha_creacion = $data['fecha_creacion'] ?? null;
            $this->creado_por = $data['creado_por'] ?? null;
        }
    }
}
