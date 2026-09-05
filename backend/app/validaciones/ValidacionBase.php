<?php

namespace App\Validaciones;

class ValidacionBase
{
    public static function validarRequerido($value, string $mensaje): void
    {
        if (trim((string)$value) === '') {
            throw new \Exception($mensaje);
        }
    }

    public static function validarLongitudMax($value, int $max, string $campo): void
    {
        if (mb_strlen((string)$value) > $max) {
            throw new \Exception("El campo {$campo} no puede superar los {$max} caracteres.");
        }
    }

    public static function validarRucCi(string $value, string $campo = 'RUC/CI'): void
    {
        $value = trim($value);

        if ($value === '') {
            throw new \Exception("El campo {$campo} es obligatorio.");
        }

        if (preg_match('/^\d{7}$/', $value)) {
            return;
        }

        if (preg_match('/^\d{8}-\d$/', $value)) {
            return;
        }

        throw new \Exception("El campo {$campo} debe ser 7 dígitos o 8 dígitos seguidos de un guión y un dígito (ej. 12345678-9).");
    }

    public static function validarEmail(string $email): void
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \Exception('El correo electrónico no es válido.');
        }
    }

    public static function validarTelefono(string $telefono, string $campo): void
    {
        if (trim($telefono) === '') {
            return;
        }

        if (!preg_match('/^[0-9()+\s-]{4,20}$/', $telefono)) {
            throw new \Exception("El campo {$campo} no es válido.");
        }
    }

    public static function validarEnteroPositivo($value, string $campo): void
    {
        if (!filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])) {
            throw new \Exception("El campo {$campo} debe ser un entero positivo.");
        }
    }

    public static function validarNumeroFactura(string $numero): void
    {
        if (!preg_match('/^\d{3}-\d{3}-\d{7}$/', trim($numero))) {
            throw new \Exception('El número de comprobante debe tener el formato 001-001-0000001.');
        }
    }

    public static function validarTimbrado(string $timbrado): void
    {
        if (!preg_match('/^[0-9]{8}$/', trim($timbrado))) {
            throw new \Exception('El timbrado debe contener 8 dígitos.');
        }
    }

    public static function validarFecha(string $fecha, string $campo): void
    {
        if (trim($fecha) === '') {
            throw new \Exception("El campo {$campo} es obligatorio.");
        }

        $date = \DateTime::createFromFormat('Y-m-d', $fecha);
        if (!$date || $date->format('Y-m-d') !== $fecha) {
            throw new \Exception("El campo {$campo} debe ser una fecha válida en formato YYYY-MM-DD.");
        }
    }

    public static function validarFechaOpcional(?string $fecha, string $campo): void
    {
        if ($fecha === null || trim($fecha) === '') {
            return;
        }

        $date = \DateTime::createFromFormat('Y-m-d', $fecha);
        if (!$date || $date->format('Y-m-d') !== $fecha) {
            throw new \Exception("El campo {$campo} debe ser una fecha válida en formato YYYY-MM-DD.");
        }
    }

    public static function validarEstado($value, string $campo, array $valores = [0, 1]): void
    {
        if ($value !== null && !in_array((int)$value, $valores, true)) {
            throw new \Exception("El campo {$campo} debe ser ".implode(' o ', $valores).".");
        }
    }

    public static function validarNumeroPositivo($value, string $campo): void
    {
        if (!is_numeric($value) || $value < 0) {
            throw new \Exception("El campo {$campo} debe ser un número mayor o igual a 0.");
        }
    }
}
