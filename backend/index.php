<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

if (class_exists(Dotenv\Dotenv::class) && file_exists(__DIR__ . '/.env')) {
    Dotenv\Dotenv::createImmutable(__DIR__)->safeLoad();
}
require_once __DIR__ . '/src/config/cors.php';
require_once __DIR__ . '/src/middleware/cors.php';
require_once __DIR__ . '/src/middleware/auth.php';
require_once __DIR__ . '/src/middleware/not_found.php';
require_once __DIR__ . '/src/controllers/auth_controller.php';
require_once __DIR__ . '/src/controllers/account_controller.php';
require_once __DIR__ . '/src/controllers/admin_controller.php';
require_once __DIR__ . '/src/controllers/orders_controller.php';
require_once __DIR__ . '/src/controllers/clientes_controller.php';
require_once __DIR__ . '/src/controllers/metrics_controller.php';
require_once __DIR__ . '/src/controllers/turnos_controller.php';
require_once __DIR__ . '/src/controllers/notificaciones_controller.php';
require_once __DIR__ . '/src/routes/routes.php';

applyCors();

set_exception_handler(static function (Throwable $error): never {
    error_log($error->getMessage());
    $message = ($_ENV['APP_ENV'] ?? 'production') === 'development'
        ? $error->getMessage() : 'Error interno del servidor';
    sendJson(['message' => $message], 500);
});

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

if ($method === 'GET' && ($path === '/' || $path === '')) {
    sendJson([
        'status' => 'ok',
        'service' => 'ecoruta-api',
        'message' => 'EcoRuta API v1.0 activa',
        'health' => '/api/health',
    ]);
}

if ($method === 'GET' && $path === '/api/health') {
    $mailUser = trim((string) ($_ENV['MAIL_USERNAME'] ?? ''));
    $mailPassword = preg_replace('/\s+/', '', (string) ($_ENV['MAIL_PASSWORD'] ?? ''));
    $mailFormatReady = filter_var($mailUser, FILTER_VALIDATE_EMAIL)
        && strlen($mailPassword) === 16
        && !str_contains($mailUser, 'TU_CORREO');
    sendJson([
        'status' => 'ok',
        'service' => 'ecoruta-api',
        'database' => 'ecoruta_db',
        'mail_configured' => $mailFormatReady,
    ]);
}

if ($method === 'GET' && ($path === '/api/routes' || $path === '/api/routes/')) {
    sendJson(getRoutes());
}

if ($method === 'POST' && $path === '/api/auth/register') {
    register();
}

if ($method === 'POST' && $path === '/api/auth/login') {
    login();
}

if ($method === 'POST' && $path === '/api/auth/forgot-password') {
    forgotAccount();
}

if ($method === 'POST' && $path === '/api/auth/reset-password') {
    resetAccount();
}

if ($method === 'GET' && $path === '/api/me') {
    $auth = optionalAuth();
    if ($auth && isset($auth->sub)) {
        getCurrentAccount($auth);
    } else {
        requireAuth();
    }
}

if ($method === 'PATCH' && $path === '/api/me') {
    editCurrentAccount(requireAuth());
}

if ($method === 'PATCH' && $path === '/api/me/repartidor') {
    $auth = optionalAuth();
    if ($auth && isset($auth->sub)) {
        editRepartidorAccount($auth);
    } else {
        editRepartidorAccount(requireAuth());
    }
}

if ($method === 'PATCH' && $path === '/api/me/comercio') {
    editCurrentCommerce(requireAuth());
}

if ($method === 'POST' && $path === '/api/me/password') {
    editCurrentPassword(requireAuth());
}

if ($method === 'GET' && $path === '/api/admin/users') {
    getUsers(requireAuth());
}

if ($method === 'POST' && $path === '/api/admin/users') {
    storeUser(requireAuth());
}

if ($method === 'PATCH' && preg_match('#^/api/admin/users/(\\d+)$#', $path, $matches)) {
    patchUser(requireAuth(), (int) $matches[1]);
}

if ($method === 'GET' && $path === '/api/admin/repartidores') {
    getRepartidores(requireAuth());
}

if ($method === 'GET' && $path === '/api/clientes') {
    getClientes(requireAuth());
}

if ($method === 'POST' && $path === '/api/clientes') {
    storeCliente(requireAuth());
}

if ($method === 'PATCH' && preg_match('#^/api/clientes/(\\d+)$#', $path, $matches)) {
    patchCliente(requireAuth(), (int) $matches[1]);
}

if ($method === 'DELETE' && preg_match('#^/api/clientes/(\\d+)$#', $path, $matches)) {
    removeCliente(requireAuth(), (int) $matches[1]);
}

// Rutas de Pedidos / Entregas en base de datos ecoruta_db
if ($method === 'GET' && ($path === '/api/orders' || $path === '/api/orders/')) {
    getOrders();
}

if ($method === 'GET' && preg_match('#^/api/commerce/(\d+)/orders$#', $path, $matches)) {
    getOrdersByCommerce((int) $matches[1]);
}

if ($method === 'POST' && $path === '/api/orders') {
    storeOrder();
}

if ($method === 'PATCH' && preg_match('#^/api/orders/(\\d+)/status$#', $path, $matches)) {
    changeOrderStatus((int) $matches[1]);
}

if ($method === 'PATCH' && preg_match('#^/api/orders/(\\d+)$#', $path, $matches)) {
    updateOrderController(requireAuth(), (int) $matches[1]);
}

if ($method === 'PATCH' && preg_match('#^/api/orders/(\\d+)/assign$#', $path, $matches)) {
    assignOrder((int) $matches[1]);
}

if ($method === 'PATCH' && preg_match('#^/api/orders/(\\d+)/cancel$#', $path, $matches)) {
    cancelOrderController(requireAuth(), (int) $matches[1]);
}

if ($method === 'PATCH' && preg_match('#^/api/orders/(\\d+)/pago$#', $path, $matches)) {
    updatePaymentController(requireAuth(), (int) $matches[1]);
}

// Turnos del repartidor (requieren sesión)
if ($method === 'GET' && $path === '/api/turno/actual') {
    currentTurno(requireAuth());
}

if ($method === 'POST' && $path === '/api/turno/start') {
    startTurnController(requireAuth());
}

if ($method === 'PATCH' && $path === '/api/turno/pause') {
    pauseTurnController(requireAuth());
}

if ($method === 'PATCH' && $path === '/api/turno/resume') {
    resumeTurnController(requireAuth());
}

if ($method === 'PATCH' && $path === '/api/turno/finish') {
    finishTurnController(requireAuth());
}

// Notificaciones (requieren sesión)
if ($method === 'GET' && $path === '/api/notificaciones') {
    getNotifications(requireAuth());
}

if ($method === 'GET' && $path === '/api/notificaciones/no-leidas') {
    unreadNotifications(requireAuth());
}

if ($method === 'POST' && $path === '/api/notificaciones/leer-todas') {
    markAllNotifications(requireAuth());
}

if ($method === 'PATCH' && preg_match('#^/api/notificaciones/(\\d+)/leida$#', $path, $matches)) {
    markReadNotification(requireAuth(), (int) $matches[1]);
}

if ($method === 'GET' && $path === '/api/metrics') {
    getDailyMetrics();
}

if ($method === 'PATCH' && $path === '/api/me/comercio') {
    editCurrentCommerce(requireAuth());
}

sendNotFound();
