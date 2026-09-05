<?php

namespace App\Validators;

class UsuarioValidator
{
    /**
     * Valida los datos para crear un usuario
     *
     * @param array $data
     * @return void
     * @throws \Exception
     */
    public static function validarCreacion(array $data): void
    {
        // Campos obligatorios
        $required = ['nombre', 'apellido', 'email', 'password', 'id_rol'];

        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new \Exception("El campo '{$field}' es obligatorio.");
            }
        }

        // Validar email
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            throw new \Exception("El email '{$data['email']}' no es válido.");
        }

        // Validar longitud de password
        if (strlen($data['password']) < 6) {
            throw new \Exception("La contraseña debe tener al menos 6 caracteres.");
        }

        // Validar rol_id como entero positivo
        if (!is_int($data['id_rol']) || $data['id_rol'] <= 0) {
            throw new \Exception("El rol_id debe ser un entero positivo.");
        }

        // Validar ci_usuario si existe
        if (isset($data['ci_usuario']) && strlen($data['ci_usuario']) > 15) {
            throw new \Exception("El ci_usuario no puede tener más de 15 caracteres.");
        }

        // Validar celular si existe
        if (isset($data['celular']) && strlen($data['celular']) > 20) {
            throw new \Exception("El celular no puede tener más de 20 caracteres.");
        }
    }

    /**
     * Valida los datos para actualizar un usuario
     *
     * @param array $data
     * @return void
     * @throws \Exception
     */
    public static function validarActualizacion(array $data): void
    {
        if (isset($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            throw new \Exception("El email '{$data['email']}' no es válido.");
        }

        if (isset($data['password']) && strlen($data['password']) < 6) {
            throw new \Exception("La contraseña debe tener al menos 6 caracteres.");
        }

        if (isset($data['id_rol']) && (!is_int($data['id_rol']) || $data['id_rol'] <= 0)) {
            throw new \Exception("El rol_id debe ser un entero positivo.");
        }

        if (isset($data['estado']) && !in_array($data['estado'], [0,1], true)) {
            throw new \Exception("El estado debe ser 0 (inactivo) o 1 (activo).");
        }

        if (isset($data['ci_usuario']) && strlen($data['ci_usuario']) > 15) {
            throw new \Exception("El ci_usuario no puede tener más de 15 caracteres.");
        }

        if (isset($data['celular']) && strlen($data['celular']) > 20) {
            throw new \Exception("El celular no puede tener más de 20 caracteres.");
        }
    }
}