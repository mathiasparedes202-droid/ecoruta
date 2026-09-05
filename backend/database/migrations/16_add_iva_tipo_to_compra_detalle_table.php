<?php
require_once __DIR__ . '/../../vendor/autoload.php';

use Config\Database;

$db = (new Database())->connect();

try {
    $db->exec("ALTER TABLE compra_detalle ADD COLUMN iva_tipo INT DEFAULT 10");
    echo "Columna iva_tipo agregada a compra_detalle.\n";
} catch (Exception $e) {
    echo "La columna iva_tipo ya existe en compra_detalle.\n";
}
