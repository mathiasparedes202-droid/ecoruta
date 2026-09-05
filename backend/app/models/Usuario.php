<?php

namespace App\Models;

class Usuario
{
    public ?int $id_usuario = null;
    public ?string $ci_usuario = null;
    public string $nombre;
    public string $apellido;
    public string $email;
    public ?string $celular = null;
    public string $password;
    public int $id_rol;
    public int $estado = 1;
    public ?string $fecha_creacion = null;
    public ?int $creado_por = null;

    // Append de objeto Rol
    public ?Rol $rol = null;

    /**
     * Constructor para inicializar el usuario
     *
     * @param array|null $data
     */
    public function __construct(?array $data = null)
    {
        if ($data) {
            $this->id_usuario = $data['id_usuario'] ?? null;
            $this->ci_usuario = $data['ci_usuario'] ?? null;
            $this->nombre = $data['nombre'] ?? '';
            $this->apellido = $data['apellido'] ?? '';
            $this->email = $data['email'] ?? '';
            $this->celular = $data['celular'] ?? null;
            $this->password = $data['password'] ?? '';
            $this->id_rol = $data['id_rol'] ?? 1;
            $this->estado = $data['estado'] ?? 1;
            $this->fecha_creacion = $data['fecha_creacion'] ?? null;
            $this->creado_por = $data['creado_por'] ?? null;

            // Si viene rol completo (array), lo convertimos en objeto Rol
            if (isset($data['rol']) && is_array($data['rol'])) {
                $this->rol = new Rol($data['rol']);
            }
        }
    }
}