<?php
require_once __DIR__ . '/../../vendor/autoload.php';

use Config\Database;

$db = (new Database())->connect();

if (!function_exists('columnExists')) {
    function columnExists($db, $table, $column)
    {
        $stmt = $db->prepare(
            "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?"
        );
        $stmt->execute([$table, $column]);
        return $stmt->fetchColumn() > 0;
    }
}

try {
    if (!columnExists($db, 'venta', 'delivery_option')) {
        $db->exec("ALTER TABLE venta ADD COLUMN delivery_option ENUM('Retiro', 'Delivery') NOT NULL DEFAULT 'Retiro' AFTER numero_comprobante");
        echo "Columna 'delivery_option' agregada a venta\n";
    } else {
        echo "Columna 'delivery_option' ya existe en venta\n";
    }

    if (!columnExists($db, 'venta', 'direccion_entrega')) {
        $db->exec("ALTER TABLE venta ADD COLUMN direccion_entrega VARCHAR(150) NULL AFTER delivery_option");
        echo "Columna 'direccion_entrega' agregada a venta\n";
    } else {
        echo "Columna 'direccion_entrega' ya existe en venta\n";
    }

    echo "Migration completada exitosamente.\n";
} catch (Exception $e) {
    echo "Error en migration: " . $e->getMessage() . "\n";
}
