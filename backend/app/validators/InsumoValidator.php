<?php

namespace App\Validators;

class InsumoValidator
{
    private const ESTADOS = [0, 1];

    public static function validarCreacion(array $data): void
    {
        self::validarNombre((string)($data['nombre_insumo'] ?? ''));
        self::validarEnteroPositivo($data['id_tipo_insumo'] ?? null, 'id_tipo_insumo');
        self::validarEnteroPositivo($data['id_categoria'] ?? null, 'id_categoria');
        self::validarEnteroPositivo($data['id_unidad'] ?? null, 'id_unidad');
        self::validarCamposOpcionales($data);
    }

    public static function validarActualizacion(array $data): void
    {
        if (array_key_exists('nombre_insumo', $data)) {
            self::validarNombre((string)$data['nombre_insumo']);
        }

        if (array_key_exists('id_tipo_insumo', $data)) {
            self::validarEnteroPositivo($data['id_tipo_insumo'], 'id_tipo_insumo');
        }

        if (array_key_exists('id_categoria', $data)) {
            self::validarEnteroPositivo($data['id_categoria'], 'id_categoria');
        }

        if (array_key_exists('id_unidad', $data)) {
            self::validarEnteroPositivo($data['id_unidad'], 'id_unidad');
        }

        self::validarCamposOpcionales($data);
    }

    private static function validarNombre(string $value): void
    {
        if (trim($value) === '') {
            throw new \Exception('El nombre del insumo es obligatorio.');
        }

        if (mb_strlen($value) > 200) {
            throw new \Exception('El nombre del insumo no puede superar los 200 caracteres.');
        }
    }

    private static function validarEnteroPositivo($value, string $campo): void
    {
        if (!filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])) {
            throw new \Exception("El campo {$campo} debe ser un entero positivo.");
        }
    }

    private static function validarCamposOpcionales(array $data): void
    {
        if (isset($data['descripcion']) && trim((string)$data['descripcion']) !== '' && mb_strlen($data['descripcion']) > 500) {
            throw new \Exception('La descripción no puede superar los 500 caracteres.');
        }

        if (isset($data['id_variedad']) && trim((string)$data['id_variedad']) !== '') {
            self::validarEnteroPositivo($data['id_variedad'], 'id_variedad');
        }

        if (isset($data['id_color']) && trim((string)$data['id_color']) !== '') {
            self::validarEnteroPositivo($data['id_color'], 'id_color');
        }

        if (isset($data['stock']) && $data['stock'] !== '' && (!is_numeric($data['stock']) || $data['stock'] < 0)) {
            throw new \Exception('El stock debe ser un número mayor o igual a 0.');
        }

        if (isset($data['precio_compra']) && $data['precio_compra'] !== '' && (!is_numeric($data['precio_compra']) || $data['precio_compra'] < 0)) {
            throw new \Exception('El precio de compra debe ser un número mayor o igual a 0.');
        }

        if (isset($data['estado']) && !in_array((int)$data['estado'], self::ESTADOS, true)) {
            throw new \Exception('El estado del insumo debe ser 0 (inactivo) o 1 (activo).');
        }

        if (isset($data['creado_por']) && !filter_var($data['creado_por'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])) {
            throw new \Exception('El campo creado_por debe ser un identificador válido.');
        }
    }
}
