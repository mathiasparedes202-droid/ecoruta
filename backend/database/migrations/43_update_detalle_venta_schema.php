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
    $columns = [
        'id_producto' => 'INT NULL AFTER id_insumo',
        'detalle_produccion' => 'VARCHAR(255) NULL AFTER subtotal',
        'detalle_receta' => 'TEXT NULL AFTER detalle_produccion',
    ];

    foreach ($columns as $column => $definition) {
        if (!columnExists($db, 'detalle_venta', $column)) {
            $db->exec("ALTER TABLE detalle_venta ADD COLUMN $column $definition");
            echo "Columna '$column' agregada a detalle_venta\n";
        } else {
            echo "Columna '$column' ya existe en detalle_venta\n";
        }
    }

    echo "Migration completada exitosamente.\n";
} catch (Exception $e) {
    echo "Error en migration: " . $e->getMessage() . "\n";
}
