<?php

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/RolSeeder.php';
require_once __DIR__ . '/UsuarioSeeder.php';
require_once __DIR__ . '/TipoInsumoSeeder.php';
require_once __DIR__ . '/CategoriaSeeder.php';
require_once __DIR__ . '/UnidadSeeder.php';
require_once __DIR__ . '/VariedadSeeder.php';
require_once __DIR__ . '/ColorSeeder.php';
require_once __DIR__ . '/ProveedorSeeder.php';
require_once __DIR__ . '/InsumoSeeder.php';
require_once __DIR__ . '/ClienteSeeder.php';
require_once __DIR__ . '/ProductoSeeder.php';
require_once __DIR__ . '/CompraSeeder.php';

use Config\Database;

class ResetDatabaseSeeder
{
    protected array $tablesToClean = [
        'detalle_venta',
        'venta',
        'pago_cliente_detalle',
        'pago_cliente_cabecera',
        'pago_proveedor_detalle',
        'pago_proveedor',
        'compra_detalle',
        'compra',
        'compra_historial',
        'pedido_personalizacion',
        'pedido_detalle',
        'pedido',
        'receta_detalle',
        'receta_produccion',
        'orden_produccion',
        'produccion_consumo',
        'movimiento_stock_producto',
        'movimiento_stock_insumo',
        'stock_reserva',
        'stock_producto',
        'stock_insumo',
        'caja',
        'apertura_caja',
        'cierre_caja',
        'arqueo_caja',
        'password_resets',
        'audit_log',
        'insumo',
        'producto',
        'cliente',
        'proveedor',
        'color',
        'variedad',
        'unidad',
        'tipo_insumo',
        'categoria',
    ];

    public function run(): void
    {
        $database = (new Database())->connect();

        echo "Limpiando datos existentes (preservando usuario y rol)...\n";
        $database->exec('SET FOREIGN_KEY_CHECKS = 0');

        foreach ($this->tablesToClean as $table) {
            $database->exec("TRUNCATE TABLE `$table`");
        }

        $database->exec('SET FOREIGN_KEY_CHECKS = 1');
        echo "Limpieza completa.\n";

        echo "Volcando datos base y carga detallada de demo paraguaya...\n";
        (new RolSeeder())->run();
        (new UsuarioSeeder())->run();
        (new TipoInsumoSeeder())->run();
        (new CategoriaSeeder())->run();
        (new UnidadSeeder())->run();
        (new VariedadSeeder())->run();
        (new ColorSeeder())->run();
        (new ProveedorSeeder())->run();
        (new InsumoSeeder())->run();
        (new ClienteSeeder())->run();
        (new ProductoSeeder())->run();
        (new CompraSeeder())->run();

        echo "Carga de demo paraguaya finalizada.\n";
    }
}
