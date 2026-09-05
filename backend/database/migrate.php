<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Config\Database;

// Conexión a DB
$db = (new Database())->connect();

// Carpeta de migrations
$migrationsDir = __DIR__ . '/migrations';

// Obtener todos los archivos PHP de la carpeta
$files = glob($migrationsDir . '/*.php');

foreach ($files as $file) {
    echo "Ejecutando migration: " . basename($file) . "\n";
    require $file;
}

echo "Todas las migrations se ejecutaron correctamente.\n";