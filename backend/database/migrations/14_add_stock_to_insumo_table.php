<?php
require_once __DIR__ . '/../../vendor/autoload.php';

use Config\Database;

$db = (new Database())->connect();

// Verificar si la columna 'stock' ya existe
$query = "SHOW COLUMNS FROM insumo LIKE 'stock'";
$stmt = $db->prepare($query);
$stmt->execute();
$columnExists = (bool)$stmt->fetch(PDO::FETCH_ASSOC);

if (!$columnExists) {
    $sql = "ALTER TABLE insumo
        ADD COLUMN stock DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER id_unidad";

    $db->exec($sql);
    echo "Columna stock agregada a la tabla insumo correctamente.\n";
} else {
    echo "La columna stock ya existe en la tabla insumo.\n";
}
