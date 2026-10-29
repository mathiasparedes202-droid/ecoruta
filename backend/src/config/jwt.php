<?php

declare(strict_types=1);

/**
 * Fuente única del secreto JWT.
 *
 * Firmar (issueToken) y validar (requireAuth / optionalAuth) DEBEN resolver el
 * mismo secreto: si cada uno tenía el suyo, un token emitido por el login no se
 * podía validar en los endpoints protegidos (p. ej. descargar HTML/PDF de una
 * factura devolvía "JWT_SECRET no está configurado").
 *
 * Prioriza la variable de entorno JWT_SECRET (Render / XAMPP / .env); si no
 * existe, cae al secreto por defecto para no dejar el sistema inoperativo.
 * En producción conviene definir JWT_SECRET en el entorno del servidor.
 */
if (!function_exists('jwtSecret')) {
    function jwtSecret(): string
    {
        $secret = $_ENV['JWT_SECRET'] ?? getenv('JWT_SECRET') ?: '';

        return $secret !== ''
            ? $secret
            : '721181d4304ecbe50c420aada6df6a4592f0213e264e764f3f2f2aee86abe019';
    }
}
