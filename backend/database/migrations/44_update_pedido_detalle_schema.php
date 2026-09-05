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
    if (!columnExists($db, 'pedido_detalle', 'detalle_receta')) {
        $db->exec("ALTER TABLE pedido_detalle ADD COLUMN detalle_receta TEXT NULL AFTER precio_estimado");
        echo "Columna 'detalle_receta' agregada a pedido_detalle\n";
    } else {
        echo "Columna 'detalle_receta' ya existe en pedido_detalle\n";
    }

    echo "Migration completada exitosamente.\n";
} catch (Exception $e) {
    echo "Error en migration: " . $e->getMessage() . "\n";
}
