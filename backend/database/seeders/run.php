<?php

require_once __DIR__ . '/../../vendor/autoload.php';

require_once 'RolSeeder.php';
require_once 'UsuarioSeeder.php';
require_once 'ResetDatabaseSeeder.php';

$seedAll = isset($argv[1]) && in_array($argv[1], ['all', 'full'], true);
$resetAll = isset($argv[1]) && in_array($argv[1], ['reset', 'reset-paraguay'], true);

if ($resetAll || $seedAll) {
    require_once 'TipoInsumoSeeder.php';
    require_once 'CategoriaSeeder.php';
    require_once 'UnidadSeeder.php';
    require_once 'VariedadSeeder.php';
    require_once 'ColorSeeder.php';
    require_once 'ProveedorSeeder.php';
    require_once 'InsumoSeeder.php';
    require_once 'ClienteSeeder.php';
    require_once 'ProductoSeeder.php';
    require_once 'CompraSeeder.php';
}

echo "Ejecutando seeders...\n";

if ($resetAll) {
    (new ResetDatabaseSeeder())->run();
    exit;
}

(new RolSeeder())->run();
(new UsuarioSeeder())->run();

if ($seedAll) {
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
    echo "Seeders adicionales ejecutados correctamente.\n";
}

echo "Proceso de seeders finalizado.\n";
