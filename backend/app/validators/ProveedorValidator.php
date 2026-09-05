<?php

namespace App\Validators;

class ProveedorValidator
{
    private const ESTADOS = [0, 1];

    public static function validarCreacion(array $data): void
    {
        self::validarRazonSocial($data['razon_social'] ?? null);
        self::validarRuc($data['ruc'] ?? null);
        self::validarCamposOpcionales($data);
    }

    public static function validarActualizacion(array $data): void
    {
        if (array_key_exists('razon_social', $data)) {
            self::validarRazonSocial($data['razon_social']);
        }

        if (array_key_exists('ruc', $data)) {
            self::validarRuc($data['ruc']);
        }

        self::validarCamposOpcionales($data);
    }

    private static function validarRazonSocial($value): void
    {
        if (empty(trim((string)$value))) {
            throw new \Exception('La razón social es obligatoria.');
        }

        if (mb_strlen($value) > 200) {
            throw new \Exception('La razón social no puede superar los 200 caracteres.');
        }
    }

    private static function validarRuc($value): void
    {
        $value = trim((string)$value);
        if ($value === '') {
            throw new \Exception('El RUC/CI es obligatorio.');
        }

        if (!preg_match('/^[0-9\-]{5,20}$/', $value)) {
            throw new \Exception('El RUC/CI debe contener sólo números y guiones, entre 5 y 20 caracteres.');
        }
    }

    private static function validarCamposOpcionales(array $data): void
    {
        if (isset($data['telefono']) && trim((string)$data['telefono']) !== '') {
            $telefono = trim((string)$data['telefono']);
            if (!preg_match('/^[0-9()+\s-]{4,20}$/', $telefono)) {
                throw new \Exception('El teléfono no es válido.');
            }
        }

        if (isset($data['celular']) && trim((string)$data['celular']) !== '') {
            $celular = trim((string)$data['celular']);
            if (!preg_match('/^[0-9()+\s-]{4,20}$/', $celular)) {
                throw new \Exception('El celular no es válido.');
            }
        }

        if (isset($data['correo']) && trim((string)$data['correo']) !== '') {
            if (!filter_var($data['correo'], FILTER_VALIDATE_EMAIL)) {
                throw new \Exception('El correo electrónico no es válido.');
            }
        }

        if (isset($data['estado']) && !in_array((int)$data['estado'], self::ESTADOS, true)) {
            throw new \Exception('El estado del proveedor debe ser 0 (inactivo) o 1 (activo).');
        }

        if (isset($data['creado_por']) && !filter_var($data['creado_por'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])) {
            throw new \Exception('El campo creado_por debe ser un identificador válido.');
        }
    }
}
