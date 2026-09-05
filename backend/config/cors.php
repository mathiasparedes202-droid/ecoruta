<?php
// Configuración de CORS para permitir solicitudes desde el frontend

// Orígenes permitidos
$allowedOrigins = [
    'http://localhost:5173',  
    'http://localhost:5174',  
    'http://localhost:5176',  
    'http://127.0.0.1:3000',  
    $_ENV['APP_URL'] ?? 'http://localhost:3000'
];

// Detecta el origen de la request
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

if (in_array($origin, $allowedOrigins)) {
    header("Access-Control-Allow-Origin: $origin");
}

// Cabeceras permitidas
header("Access-Control-Allow-Headers: Authorization, Content-Type, X-Requested-With");

// Métodos permitidos
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");

// Permitir cookies (opcional, si usas JWT en cookies)
header("Access-Control-Allow-Credentials: true");

// Responder a preflight requests (OPTIONS)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}
