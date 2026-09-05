<?php
require_once __DIR__ . '/../../vendor/autoload.php';

use Config\Database;

$db = (new Database())->connect();

// Verificar si la columna 'banco' ya existe
$query = "SHOW COLUMNS FROM pago_cliente_detalle LIKE 'banco'";
$stmt = $db->prepare($query);
$stmt->execute();
$columnExists = (bool)$stmt->fetch(PDO::FETCH_ASSOC);

if (!$columnExists) {
    $sql = "ALTER TABLE pago_cliente_detalle
        ADD COLUMN banco VARCHAR(150) NULL AFTER numero_comprobante";

    $db->exec($sql);
    echo "Columna banco agregada a la tabla pago_cliente_detalle correctamente.\n";
} else {
    echo "La columna banco ya existe en la tabla pago_cliente_detalle.\n";
}
