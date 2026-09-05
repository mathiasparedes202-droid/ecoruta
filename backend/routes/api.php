<?php

use App\Controllers\AuthController;
use App\Controllers\RolController;
use App\Controllers\UsuarioController;
use App\Controllers\ProveedorController;
use App\Controllers\ClienteController;
use App\Controllers\CompraController;
use App\Controllers\InsumoController;
use App\Controllers\StockController;
use App\Controllers\PagoProveedorController;
use App\Controllers\CatalogoController;
use App\Controllers\VentaController;
use App\Controllers\PedidoController;
use App\Controllers\ProductoController;
use App\Controllers\ProductoMantenimientoController;
use App\Controllers\CajaController;
use App\Controllers\ReporteController;
use App\Middlewares\AuthMiddleware;
use App\Middlewares\RoleMiddleware;
use Core\Router;

// Instanciamos request y response.
$request = new Core\Request();
$response = new Core\Response();

// Instanciamos el router.
$router = new Router($request, $response);

// Middlewares.
$auth = AuthMiddleware::class;
$adminRole = RoleMiddleware::class;

// Ruta de prueba.
$router->get('/api/test', function ($request, $response) {
    $response->success('API funcionando correctamente');
});

$router->post('/api/debug-request', function ($request, $response) {
    $response->json([
        'headers' => $request->getHeaders(),
        'body' => $request->getBody(),
        'raw_input' => file_get_contents('php://input'),
        'server' => [
            'CONTENT_TYPE' => $_SERVER['CONTENT_TYPE'] ?? null,
            'HTTP_CONTENT_TYPE' => $_SERVER['HTTP_CONTENT_TYPE'] ?? null,
            'HTTP_AUTHORIZATION' => $_SERVER['HTTP_AUTHORIZATION'] ?? null,
            'REDIRECT_HTTP_AUTHORIZATION' => $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? null,
        ],
    ]);
}, [$auth]);

// Rutas para login.
$router->post('/api/login', [AuthController::class, 'login']);

$router->post('/api/logout', [AuthController::class, 'logout']);

$router->post('/api/forgot-password', [AuthController::class, 'forgotPassword']);

$router->post('/api/reset-password', [AuthController::class, 'resetPassword']);

$router->post('/api/verify-token', [AuthController::class, 'verifyToken']);

$router->get('/api/me', [AuthController::class, 'me'], [
    AuthMiddleware::class
]);

// $router->get('/perfil', function($req, $res) {
//     $res->success('Perfil del usuario', $req->getUser());
// }, [
//     AuthMiddleware::class
// ]);

// Rutas para usuarios.
$router->get('/api/usuarios', [UsuarioController::class, 'index'], [$auth]);
$router->post('/api/usuarios', [UsuarioController::class, 'store'], [$auth]);
$router->get('/api/usuarios/{id}', [UsuarioController::class, 'show'], [$auth]);
$router->get('/api/usuarios/{id}/check-dependencies', [UsuarioController::class, 'checkDependencies'], [$auth]);
$router->put('/api/usuarios/{id}', [UsuarioController::class, 'update'], [$auth]);
$router->delete('/api/usuarios/{id}', [UsuarioController::class, 'destroy'], [$auth]);

$router->get('/api/roles', [RolController::class, 'index'], [$auth]);
$router->post('/api/roles', [RolController::class, 'store'], [$auth]);
$router->get('/api/roles/{id}', [RolController::class, 'show'], [$auth]);
$router->put('/api/roles/{id}', [RolController::class, 'update'], [$auth]);
$router->delete('/api/roles/{id}', [RolController::class, 'destroy'], [$auth]);

// Proveedores
$router->get('/api/proveedores', [ProveedorController::class, 'index']);
$router->post('/api/proveedores', [ProveedorController::class, 'store'], [$auth]);
$router->get('/api/proveedores/{id}', [ProveedorController::class, 'show'], [$auth]);
$router->put('/api/proveedores/{id}', [ProveedorController::class, 'update'], [$auth]);
$router->delete('/api/proveedores/{id}', [ProveedorController::class, 'destroy'], [$auth]);

// Insumos
$router->get('/api/insumos', [InsumoController::class, 'index']);
$router->post('/api/insumos', [InsumoController::class, 'store'], [$auth]);
$router->get('/api/insumos/{id}', [InsumoController::class, 'show'], [$auth]);
$router->put('/api/insumos/{id}', [InsumoController::class, 'update'], [$auth]);
$router->delete('/api/insumos/{id}', [InsumoController::class, 'destroy'], [$auth]);

// Stock
$router->post('/api/stock/ajustes', [StockController::class, 'store'], [$auth]);

// Pagos a proveedores
$router->get('/api/pagos/proveedores', [PagoProveedorController::class, 'index']); // [$auth] quitado temporalmente
$router->get('/api/pagos/proveedores/pendientes', [PagoProveedorController::class, 'proveedoresPendientes']); // [$auth] quitado temporalmente
$router->post('/api/pagos/proveedores', [PagoProveedorController::class, 'store'], [$auth]);
$router->delete('/api/pagos/proveedores/{id}', [PagoProveedorController::class, 'destroy'], [$auth]);

// Pagos a clientes
$router->post('/api/pagos-clientes', [\App\Controllers\PagoClienteController::class, 'store'], [$auth]);
$router->get('/api/pagos-clientes', [\App\Controllers\PagoClienteController::class, 'index'], [$auth]);
$router->get('/api/pagos-clientes/{id}', [\App\Controllers\PagoClienteController::class, 'show'], [$auth]);

// Compras
$router->get('/api/compras', [CompraController::class, 'index']); // quitado [$auth] temporalmente
$router->get('/api/compras/proveedor', [CompraController::class, 'getByProveedor'], [$auth]);
$router->get('/api/compras/insumo', [CompraController::class, 'getByInsumo'], [$auth]);
$router->get('/api/compras/{id}', [CompraController::class, 'show'], [$auth]);
$router->post('/api/compras', [CompraController::class, 'store'], [$auth]);
// Preview / imprimir comprobante de compra
$router->post('/api/compras/preview', [CompraController::class, 'preview'], [$auth]);
$router->put('/api/compras/{id}', [CompraController::class, 'update'], [$auth]);
$router->delete('/api/compras/{id}', [CompraController::class, 'destroy'], [$auth]);

// Clientes
$router->get('/api/clientes', [ClienteController::class, 'index']);
$router->post('/api/clientes', [ClienteController::class, 'store'], [$auth]);
$router->get('/api/clientes/{id}', [ClienteController::class, 'show'], [$auth]);
$router->get('/api/clientes/{id}/check-dependencies', [ClienteController::class, 'checkDependencies'], [$auth]);
$router->put('/api/clientes/{id}', [ClienteController::class, 'update'], [$auth]);
$router->delete('/api/clientes/{id}', [ClienteController::class, 'destroy'], [$auth]);

// Catálogos generales
$router->get('/api/catalogos/{section}', [CatalogoController::class, 'index'], [$auth]);
$router->get('/api/catalogos/{section}/{id}', [CatalogoController::class, 'show'], [$auth]);
$router->post('/api/catalogos/{section}', [CatalogoController::class, 'store'], [$auth]);
$router->put('/api/catalogos/{section}/{id}', [CatalogoController::class, 'update'], [$auth]);
$router->delete('/api/catalogos/{section}/{id}', [CatalogoController::class, 'destroy'], [$auth]);

// Productos
$router->get('/api/productos', [ProductoController::class, 'index']);
$router->get('/api/productos/{id}', [ProductoController::class, 'show']);
$router->post('/api/productos', [ProductoController::class, 'store'], [$auth]);
$router->put('/api/productos/{id}', [ProductoController::class, 'update'], [$auth]);

// Productos mantenimiento (sin permiso específico en frontend)
$router->get('/api/mantenimiento/productos', [ProductoMantenimientoController::class, 'index'], [$auth]);
$router->get('/api/mantenimiento/productos/{id}', [ProductoMantenimientoController::class, 'show'], [$auth]);
$router->post('/api/mantenimiento/productos', [ProductoMantenimientoController::class, 'store'], [$auth]);
$router->put('/api/mantenimiento/productos/{id}', [ProductoMantenimientoController::class, 'update'], [$auth]);
$router->delete('/api/mantenimiento/productos/{id}', [ProductoMantenimientoController::class, 'destroy'], [$auth]);

// Caja
$router->get('/api/caja/status', [CajaController::class, 'status'], [$auth]);
$router->post('/api/caja/abrir', [CajaController::class, 'abrir'], [$auth]);
$router->post('/api/caja/cerrar', [CajaController::class, 'cerrar'], [$auth]);
$router->get('/api/caja/movimientos', [CajaController::class, 'movimientos'], [$auth]);

// Ventas
$router->get('/api/ventas', [VentaController::class, 'index'], [$auth]);
$router->post('/api/ventas', [VentaController::class, 'store'], [$auth]);
$router->get('/api/ventas/next-factura', [VentaController::class, 'nextFactura'], [$auth]);
$router->get('/api/ventas/cliente', [VentaController::class, 'getByCliente'], [$auth]);
$router->get('/api/ventas/producto', [VentaController::class, 'getByProducto'], [$auth]);
$router->get('/api/ventas/{id}/factura/html', [VentaController::class, 'facturaHtml'], [$auth]);
$router->get('/api/ventas/{id}/factura/pdf', [VentaController::class, 'facturaPdf'], [$auth]);
$router->get('/api/ventas/{id}', [VentaController::class, 'show'], [$auth]);
$router->put('/api/ventas/{id}', [VentaController::class, 'update'], [$auth]);
$router->delete('/api/ventas/{id}', [VentaController::class, 'destroy'], [$auth]);

// Producción / Pedidos
$router->get('/api/pedidos/produccion', [PedidoController::class, 'listarOrdenesProduccion'], [$auth]);
$router->get('/api/pedidos/produccion/{id}', [PedidoController::class, 'mostrarOrdenProduccion'], [$auth]);
$router->post('/api/pedidos/produccion', [PedidoController::class, 'crearOrdenProduccion'], [$auth]);
$router->put('/api/pedidos/produccion/{id}', [PedidoController::class, 'actualizarOrdenProduccion'], [$auth]);
$router->delete('/api/pedidos/produccion/{id}', [PedidoController::class, 'eliminarOrdenProduccion'], [$auth]);

// Reportes
$router->get('/api/reportes/{section}', [ReporteController::class, 'show'], [$auth]);

return $router;
