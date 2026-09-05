<?php

namespace App\Validaciones;

class ClienteValidacion
{
    public static function validarCreacion(array $data): void
    {
        ValidacionBase::validarRucCi($data['ruc_ci'] ?? '', 'RUC/CI del cliente');
        ValidacionBase::validarRequerido($data['razon_social'] ?? '', 'La razón social es obligatoria.');
        ValidacionBase::validarLongitudMax($data['razon_social'] ?? '', 100, 'razón social');
        if (isset($data['celular']) && trim((string)$data['celular']) !== '') {
            ValidacionBase::validarTelefono($data['celular'], 'celular');
        }
        if (array_key_exists('estado', $data)) {
            ValidacionBase::validarEstado($data['estado'], 'estado');
        }
        if (isset($data['creado_por']) && trim((string)$data['creado_por']) !== '') {
            ValidacionBase::validarEnteroPositivo($data['creado_por'], 'creado_por');
        }
    }

    public static function validarActualizacion(array $data): void
    {
        if (array_key_exists('ruc_ci', $data)) {
            ValidacionBase::validarRucCi($data['ruc_ci'] ?? '', 'RUC/CI del cliente');
        }

        if (array_key_exists('razon_social', $data)) {
            if (trim((string)$data['razon_social']) === '') {
                throw new \Exception('La razón social no puede estar vacía.');
            }
            ValidacionBase::validarLongitudMax($data['razon_social'], 100, 'razón social');
        }

        if (isset($data['celular']) && trim((string)$data['celular']) !== '') {
            ValidacionBase::validarTelefono($data['celular'], 'celular');
        }

        if (array_key_exists('estado', $data)) {
            ValidacionBase::validarEstado($data['estado'], 'estado');
        }

        if (isset($data['creado_por']) && trim((string)$data['creado_por']) !== '') {
            ValidacionBase::validarEnteroPositivo($data['creado_por'], 'creado_por');
        }
    }
}
