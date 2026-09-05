<?php
require_once __DIR__ . '/../../vendor/autoload.php';

use Config\Database;

$db = (new Database())->connect();

$columns = [
    'mensaje_personalizado' => "ALTER TABLE pedido ADD COLUMN mensaje_personalizado VARCHAR(255) NULL AFTER observaciones",
    'presentacion' => "ALTER TABLE pedido ADD COLUMN presentacion VARCHAR(100) NULL AFTER mensaje_personalizado",
    'detalle_personalizacion' => "ALTER TABLE pedido ADD COLUMN detalle_personalizacion TEXT NULL AFTER presentacion",
    'repartidor' => "ALTER TABLE pedido ADD COLUMN repartidor VARCHAR(100) NULL AFTER detalle_personalizacion",
    'estado_entrega' => "ALTER TABLE pedido ADD COLUMN estado_entrega VARCHAR(50) NULL DEFAULT 'Pendiente' AFTER repartidor",
    'fecha_hora_entrega' => "ALTER TABLE pedido ADD COLUMN fecha_hora_entrega DATETIME NULL AFTER estado_entrega",
    'etapas_produccion' => "ALTER TABLE pedido ADD COLUMN etapas_produccion TEXT NULL AFTER fecha_hora_entrega",
    'progreso_produccion' => "ALTER TABLE pedido ADD COLUMN progreso_produccion INT DEFAULT 0 AFTER etapas_produccion",
];

foreach ($columns as $column => $sql) {
    $stmt = $db->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pedido' AND COLUMN_NAME = :column");
    $stmt->execute([':column' => $column]);
    if ((int)$stmt->fetchColumn() === 0) {
        $db->exec($sql);
        echo "Columna '{$column}' agregada a pedido\n";
    } else {
        echo "Columna '{$column}' ya existe en pedido\n";
    }
}

echo "Migration completada exitosamente.\n";
