<?php

require_once __DIR__ . '/../../vendor/autoload.php';

// Migration: Agregar columnas motivo y crear_nota_credito a compra_historial
// Fecha: 2024-01-15

use Config\Database;

$db = (new Database())->connect();

try {
    $columnExists = function (string $column) use ($db): bool {
        $stmt = $db->query("SHOW COLUMNS FROM compra_historial LIKE '{$column}'");
        return (bool) $stmt->fetch();
    };

    if (!$columnExists('motivo')) {
        $db->exec("ALTER TABLE compra_historial ADD COLUMN motivo TEXT NULL AFTER valor_nuevo");
        echo "Columna 'motivo' agregada a compra_historial\n";
    } else {
        echo "Columna 'motivo' ya existe en compra_historial\n";
    }

    if (!$columnExists('crear_nota_credito')) {
        $db->exec("ALTER TABLE compra_historial ADD COLUMN crear_nota_credito BOOLEAN DEFAULT FALSE AFTER motivo");
        echo "Columna 'crear_nota_credito' agregada a compra_historial\n";
    } else {
        echo "Columna 'crear_nota_credito' ya existe en compra_historial\n";
    }

    echo "Migration completada exitosamente.\n";
} catch (Throwable $e) {
    echo "Error en migration: " . $e->getMessage() . "\n";
}
