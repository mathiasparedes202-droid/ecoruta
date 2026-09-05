<?php

namespace App\Validaciones;

class UsuarioValidacion
{
    public static function validarCreacion(array $data): void
    {
        ValidacionBase::validarRucCi($data['ci_usuario'] ?? '', 'CI del usuario');
        ValidacionBase::validarRequerido($data['nombre'] ?? '', 'El nombre es obligatorio.');
        ValidacionBase::validarLongitudMax($data['nombre'] ?? '', 50, 'nombre');
        ValidacionBase::validarRequerido($data['apellido'] ?? '', 'El apellido es obligatorio.');
        ValidacionBase::validarLongitudMax($data['apellido'] ?? '', 50, 'apellido');
        ValidacionBase::validarRequerido($data['email'] ?? '', 'El correo es obligatorio.');
        ValidacionBase::validarEmail($data['email'] ?? '');
        ValidacionBase::validarRequerido($data['password'] ?? '', 'La contraseña es obligatoria.');
        self::validarPassword($data['password']);
        ValidacionBase::validarEnteroPositivo($data['id_rol'] ?? null, 'id_rol');
        if (array_key_exists('estado', $data)) {
            ValidacionBase::validarEstado($data['estado'], 'estado');
        }
        if (isset($data['celular']) && trim((string)$data['celular']) !== '') {
            ValidacionBase::validarTelefono($data['celular'], 'celular');
        }
    }

    public static function validarActualizacion(array $data): void
    {
        if (array_key_exists('ci_usuario', $data)) {
            ValidacionBase::validarRucCi($data['ci_usuario'] ?? '', 'CI del usuario');
        }

        if (array_key_exists('nombre', $data)) {
            if (trim((string)$data['nombre']) === '') {
                throw new \Exception('El nombre no puede estar vacío.');
            }
            ValidacionBase::validarLongitudMax($data['nombre'], 50, 'nombre');
        }

        if (array_key_exists('apellido', $data)) {
            if (trim((string)$data['apellido']) === '') {
                throw new \Exception('El apellido no puede estar vacío.');
            }
            ValidacionBase::validarLongitudMax($data['apellido'], 50, 'apellido');
        }

        if (array_key_exists('email', $data)) {
            ValidacionBase::validarEmail((string)$data['email']);
        }

        if (array_key_exists('password', $data) && trim((string)$data['password']) !== '') {
            self::validarPassword($data['password']);
        }

        if (array_key_exists('id_rol', $data)) {
            ValidacionBase::validarEnteroPositivo($data['id_rol'], 'id_rol');
        }

        if (array_key_exists('estado', $data)) {
            ValidacionBase::validarEstado($data['estado'], 'estado');
        }

        if (isset($data['celular']) && trim((string)$data['celular']) !== '') {
            ValidacionBase::validarTelefono($data['celular'], 'celular');
        }
    }

    private static function validarPassword(string $password): void
    {
        if (mb_strlen($password) < 8) {
            throw new \Exception('La contraseña debe tener al menos 8 caracteres.');
        }
    }
}
