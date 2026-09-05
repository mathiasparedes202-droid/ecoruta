<?php

namespace App\Services;

use App\Models\Rol;
use App\Repositories\RolRepository;
use App\Validators\RolValidator;

class RolService
{
    private RolRepository $rolRepo;

    public function __construct()
    {
        $this->rolRepo = new RolRepository();
    }

    private function normalizePermisos(array $data): array
    {
        if (isset($data['permisos'])) {
            if (is_string($data['permisos'])) {
                $permisos = array_filter(array_map('trim', explode(',', $data['permisos'])));
                $data['permisos_json'] = $permisos;
            } elseif (is_array($data['permisos'])) {
                $data['permisos_json'] = array_values(array_filter(array_map('trim', $data['permisos'])));
            }
        }

        if (isset($data['permisos_json']) && is_string($data['permisos_json'])) {
            $decoded = json_decode($data['permisos_json'], true);
            if (is_array($decoded)) {
                $data['permisos_json'] = $decoded['permisos'] ?? $decoded;
            } else {
                $data['permisos_json'] = [];
            }
        }

        if (!isset($data['permisos_json']) || !is_array($data['permisos_json'])) {
            $data['permisos_json'] = [];
        }

        return $data;
    }

    public function obtenerRol(int $id): ?Rol
    {
        return $this->rolRepo->findById($id);
    }

    public function listarRoles(): array
    {
        return $this->rolRepo->all();
    }

    public function crearRol(array $data): Rol
    {
        RolValidator::validarCreacion($data);

        $data = $this->normalizePermisos($data);

        $rol = new Rol($data);
        $id = $this->rolRepo->create($rol);
        $rol->id_rol = $id;

        return $this->obtenerRol($id);
    }

    public function actualizarRol(int $id, array $data): Rol
    {
        $rol = $this->rolRepo->findById($id);
        if (!$rol) {
            throw new \Exception('Rol no encontrado');
        }

        RolValidator::validarActualizacion($data);
        $data = $this->normalizePermisos($data);

        $rol->nombre_rol = $data['nombre_rol'] ?? $rol->nombre_rol;
        $rol->descripcion = $data['descripcion'] ?? $rol->descripcion;
        $rol->permisos_json = $data['permisos_json'] ?? $rol->permisos_json;
        $rol->rol_del_sistema = $data['rol_del_sistema'] ?? $rol->rol_del_sistema;
        $rol->estado = $data['estado'] ?? $rol->estado;

        $this->rolRepo->update($rol);

        return $this->obtenerRol($id);
    }

    public function eliminarRol(int $id): bool
    {
        $rol = $this->rolRepo->findById($id);
        if (!$rol) {
            throw new \Exception('Rol no encontrado');
        }

        if ($rol->rol_del_sistema === 1) {
            throw new \Exception('No se puede eliminar un rol del sistema');
        }

        return $this->rolRepo->delete($id);
    }
}
