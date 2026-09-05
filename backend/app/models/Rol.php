<?php

namespace App\Models;

class Rol
{
    public ?int $id_rol = null;
    public string $nombre_rol;
    public ?string $descripcion = null;
    public array $permisos_json = [];
    public int $rol_del_sistema = 0;
    public int $estado = 1;
    public ?string $fecha_creacion = null;

    /**
     * Constructor para inicializar el rol
     *
     * @param array|null $data
     */
    public function __construct(?array $data = null)
    {
        if ($data) {
            $this->id_rol = $data['id_rol'] ?? null;
            $this->nombre_rol = $data['nombre_rol'] ?? '';
            $this->descripcion = $data['descripcion'] ?? null;

            // Si viene JSON como string, decodificamos
            if (isset($data['permisos_json'])) {
                if (is_string($data['permisos_json'])) {
                    $decoded = json_decode($data['permisos_json'], true);
                    $this->permisos_json = $decoded['permisos'] ?? [];
                } elseif (is_array($data['permisos_json'])) {
                    $this->permisos_json = $data['permisos_json'];
                }
            }

            $this->rol_del_sistema = $data['rol_del_sistema'] ?? 0;
            $this->estado = $data['estado'] ?? 1;
            $this->fecha_creacion = $data['fecha_creacion'] ?? null;
        }
    }
}