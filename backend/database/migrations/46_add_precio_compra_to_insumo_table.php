<?php
require_once __DIR__ . '/../../vendor/autoload.php';

use Config\Database;

$db = (new Database())->connect();

// Verificar si la columna 'precio_compra' ya existe
$query = "SHOW COLUMNS FROM insumo LIKE 'precio_compra'";
$stmt = $db->prepare($query);
$stmt->execute();
$columnExists = (bool)$stmt->fetch(PDO::FETCH_ASSOC);

if (!$columnExists) {
    $sql = "ALTER TABLE insumo
        ADD COLUMN precio_compra DECIMAL(12,2) NULL AFTER descripcion";

    $db->exec($sql);
    echo "Columna precio_compra agregada a la tabla insumo correctamente.\n";
} else {
    echo "La columna precio_compra ya existe en la tabla insumo.\n";
}
