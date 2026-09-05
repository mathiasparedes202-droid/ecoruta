<?php

declare(strict_types=1);

require_once __DIR__ . '/cors.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

function requireAuth(): object
{
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if (!preg_match('/^Bearer\s+(.+)$/i', $header, $matches)) {
        sendJson(['message' => 'Autenticación requerida'], 401);
    }

    $secret = $_ENV['JWT_SECRET'] ?? '';
    if ($secret === '') {
        sendJson(['message' => 'JWT_SECRET no está configurado'], 500);
    }

    try {
        return JWT::decode($matches[1], new Key($secret, 'HS256'));
    } catch (Throwable) {
        sendJson(['message' => 'Tu sesión venció o no es válida. Volvé a ingresar.'], 401);
    }
}

function optionalAuth(): ?object
{
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if (preg_match('/^Bearer\s+(.+)$/i', $header, $matches)) {
        $secret = $_ENV['JWT_SECRET'] ?? '';
        if ($secret !== '') {
            try {
                return JWT::decode($matches[1], new Key($secret, 'HS256'));
            } catch (Throwable) {
                return null;
            }
        }
    }
    return null;
}
