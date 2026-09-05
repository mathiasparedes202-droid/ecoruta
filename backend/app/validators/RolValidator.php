<?php

namespace App\Validators;

class RolValidator
{
    public static function validarCreacion(array $data): void
    {
        if (empty(trim((string)($data['nombre_rol'] ?? '')))) {
            throw new \Exception('El nombre del rol es obligatorio.');
        }

        if (isset($data['nombre_rol']) && mb_strlen($data['nombre_rol']) > 100) {
            throw new \Exception('El nombre del rol no puede exceder los 100 caracteres.');
        }
    }

    public static function validarActualizacion(array $data): void
    {
        if (isset($data['nombre_rol']) && trim((string)$data['nombre_rol']) === '') {
            throw new \Exception('El nombre del rol no puede estar vacío.');
        }

        if (isset($data['nombre_rol']) && mb_strlen($data['nombre_rol']) > 100) {
            throw new \Exception('El nombre del rol no puede exceder los 100 caracteres.');
        }
    }
}
