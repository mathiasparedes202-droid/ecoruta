<?php

declare(strict_types=1);

function applyCors(): void
{
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    $allowedOrigins = [
        'http://localhost:5173',
        'http://localhost:5174',
        'http://localhost:5175',
        'http://localhost:5176',
        'http://127.0.0.1:3000',
        'http://192.168.0.4:8000',
        'http://127.0.0.1:5173',
        'http://127.0.0.1:5174',
        'http://127.0.0.1:5175',
        $_ENV['APP_URL'] ?? 'http://localhost:8000',
        $_ENV['BACKEND_URL'] ?? 'http://192.168.0.12:8000',
        $_ENV['FRONTEND_URL'] ?? 'http://192.168.0.12:5173',
        $_ENV['EcoRuta_URL'] ?? 'http://192.168.0.12:5173',
        $_ENV['ANOTHER_URL'] ?? 'http://192.168.0.12:5173'
    ];

    $isAllowedOrigin = $origin !== '' && (
        in_array($origin, $allowedOrigins, true)
        || preg_match('/^https?:\/\/(localhost|127\.0\.0\.1)(:\d+)?$/', $origin) === 1
        || preg_match('/^https:\/\/.*\.vercel\.app$/', $origin) === 1
    );

    if ($isAllowedOrigin) {
        header("Access-Control-Allow-Origin: $origin");
        header('Vary: Origin');
    }

    header('Access-Control-Allow-Headers: Authorization, Content-Type, X-Requested-With, Accept');
    header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
    header('Access-Control-Allow-Credentials: true');

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
        http_response_code(200);
        exit;
    }
}

function sendJson(mixed $data, int $statusCode = 200): never
{
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=UTF-8');
        http_response_code($statusCode);
    }

    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
