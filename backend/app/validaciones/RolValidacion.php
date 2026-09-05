<?php

namespace App\Validaciones;

class RolValidacion
{
    public static function validarCreacion(array $data): void
    {
        ValidacionBase::validarRequerido($data['nombre'] ?? '', 'El nombre del rol es obligatorio.');
        ValidacionBase::validarLongitudMax($data['nombre'] ?? '', 100, 'nombre');
    }

    public static function validarActualizacion(array $data): void
    {
        if (array_key_exists('nombre', $data)) {
            if (trim((string)$data['nombre']) === '') {
                throw new \Exception('El nombre del rol no puede estar vacío.');
            }
            ValidacionBase::validarLongitudMax($data['nombre'], 100, 'nombre');
        }
    }
}
