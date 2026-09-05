<?php

namespace App\Validaciones;

class ProveedorValidacion
{
    public static function validarCreacion(array $data): void
    {
        ValidacionBase::validarRequerido($data['razon_social'] ?? '', 'La razón social es obligatoria.');
        self::validarRazonSocial($data['razon_social']);
        ValidacionBase::validarRucCi($data['ruc'] ?? '', 'RUC/CI');
        self::validarCamposOpcionales($data);
    }

    public static function validarActualizacion(array $data): void
    {
        if (array_key_exists('razon_social', $data)) {
            self::validarRazonSocial($data['razon_social']);
        }

        if (array_key_exists('ruc', $data)) {
            ValidacionBase::validarRucCi($data['ruc'] ?? '', 'RUC/CI');
        }

        self::validarCamposOpcionales($data);
    }

    private static function validarRazonSocial($value): void
    {
        if (trim((string)$value) === '') {
            throw new \Exception('La razón social es obligatoria.');
        }

        ValidacionBase::validarLongitudMax($value, 200, 'razón social');
    }

    private static function validarCamposOpcionales(array $data): void
    {
        if (isset($data['telefono']) && trim((string)$data['telefono']) !== '') {
            ValidacionBase::validarTelefono($data['telefono'], 'teléfono');
        }

        if (isset($data['celular']) && trim((string)$data['celular']) !== '') {
            ValidacionBase::validarTelefono($data['celular'], 'celular');
        }

        if (isset($data['correo']) && trim((string)$data['correo']) !== '') {
            ValidacionBase::validarEmail((string)$data['correo']);
        }

        if (array_key_exists('estado', $data)) {
            ValidacionBase::validarEstado($data['estado'], 'estado');
        }

        if (isset($data['creado_por']) && trim((string)$data['creado_por']) !== '') {
            ValidacionBase::validarEnteroPositivo($data['creado_por'], 'creado_por');
        }
    }
}
