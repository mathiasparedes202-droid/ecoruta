<?php

namespace App\Validaciones;

class InsumoValidacion
{
    public static function validarCreacion(array $data): void
    {
        self::validarNombre((string)($data['nombre_insumo'] ?? ''));
        ValidacionBase::validarEnteroPositivo($data['id_tipo_insumo'] ?? null, 'id_tipo_insumo');
        ValidacionBase::validarEnteroPositivo($data['id_categoria'] ?? null, 'id_categoria');
        ValidacionBase::validarEnteroPositivo($data['id_unidad'] ?? null, 'id_unidad');
        self::validarCamposOpcionales($data);
    }

    public static function validarActualizacion(array $data): void
    {
        if (array_key_exists('nombre_insumo', $data)) {
            self::validarNombre((string)$data['nombre_insumo']);
        }

        if (array_key_exists('id_tipo_insumo', $data)) {
            ValidacionBase::validarEnteroPositivo($data['id_tipo_insumo'], 'id_tipo_insumo');
        }

        if (array_key_exists('id_categoria', $data)) {
            ValidacionBase::validarEnteroPositivo($data['id_categoria'], 'id_categoria');
        }

        if (array_key_exists('id_unidad', $data)) {
            ValidacionBase::validarEnteroPositivo($data['id_unidad'], 'id_unidad');
        }

        self::validarCamposOpcionales($data);
    }

    private static function validarNombre(string $value): void
    {
        if (trim($value) === '') {
            throw new \Exception('El nombre del insumo es obligatorio.');
        }
        ValidacionBase::validarLongitudMax($value, 255, 'nombre_insumo');
    }

    private static function validarCamposOpcionales(array $data): void
    {
        if (isset($data['descripcion']) && trim((string)$data['descripcion']) !== '') {
            ValidacionBase::validarLongitudMax($data['descripcion'], 500, 'descripcion');
        }

        if (isset($data['id_variedad']) && trim((string)$data['id_variedad']) !== '') {
            ValidacionBase::validarEnteroPositivo($data['id_variedad'], 'id_variedad');
        }

        if (isset($data['id_color']) && trim((string)$data['id_color']) !== '') {
            ValidacionBase::validarEnteroPositivo($data['id_color'], 'id_color');
        }

        if (isset($data['stock']) && trim((string)$data['stock']) !== '') {
            ValidacionBase::validarNumeroPositivo($data['stock'], 'stock');
        }

        if (isset($data['precio_compra']) && trim((string)$data['precio_compra']) !== '') {
            ValidacionBase::validarNumeroPositivo($data['precio_compra'], 'precio_compra');
        }

        if (array_key_exists('estado', $data)) {
            ValidacionBase::validarEstado($data['estado'], 'estado');
        }

        if (isset($data['creado_por']) && trim((string)$data['creado_por']) !== '') {
            ValidacionBase::validarEnteroPositivo($data['creado_por'], 'creado_por');
        }
    }
}
