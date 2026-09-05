<?php

declare(strict_types=1);

require_once __DIR__ . '/cors.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

function authorizationHeader(): string
{
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if ($header !== '') {
        return $header;
    }

    if (function_exists('getallheaders')) {
        foreach (getallheaders() as $name => $value) {
            if (strcasecmp($name, 'Authorization') === 0) {
                return (string) $value;
            }
        }
    }

    $fallbackToken = trim((string) ($_GET['access_token'] ?? ''));
    return $fallbackToken !== '' ? "Bearer {$fallbackToken}" : (string) ($_SERVER['HTTP_X_AUTHORIZATION'] ?? '');
}

function requireAuth(): object
{
    $header = authorizationHeader();
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
    $header = authorizationHeader();
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
