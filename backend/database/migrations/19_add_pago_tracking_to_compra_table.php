<?php
require_once __DIR__ . '/../../vendor/autoload.php';

use Config\Database;

$db = (new Database())->connect();

$schemaStmt = $db->query('SELECT DATABASE()');
$schema = $schemaStmt->fetchColumn();

$stmt = $db->prepare(
    'SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = :schema AND TABLE_NAME = :table'
);
$stmt->execute(['schema' => $schema, 'table' => 'compra']);
$existingColumns = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'COLUMN_NAME');

$alterStatements = [];
if (!in_array('estado_pago', $existingColumns, true)) {
    $alterStatements[] = "ADD COLUMN estado_pago VARCHAR(50) DEFAULT 'No pagada' COMMENT 'No pagada, Pagada parcial, Pagada, Cancelada'";
}
if (!in_array('monto_pagado', $existingColumns, true)) {
    $alterStatements[] = "ADD COLUMN monto_pagado DECIMAL(12,2) DEFAULT 0 COMMENT 'Total pagado hasta ahora'";
}
if (!in_array('fecha_ultima_modificacion', $existingColumns, true)) {
    $alterStatements[] = "ADD COLUMN fecha_ultima_modificacion TIMESTAMP NULL";
}
if (!in_array('modificado_por', $existingColumns, true)) {
    $alterStatements[] = "ADD COLUMN modificado_por INT NULL";
}

if (!empty($alterStatements)) {
    $sql = 'ALTER TABLE compra ' . implode(', ', $alterStatements);
    $db->exec($sql);
}

// Agregar foreign key para modificado_por si es necesario
$stmt = $db->prepare(
    'SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE '
        . 'WHERE TABLE_SCHEMA = :schema AND TABLE_NAME = :table '
        . 'AND COLUMN_NAME = :column AND REFERENCED_TABLE_NAME = :referenced_table'
);
$stmt->execute([
    'schema' => $schema,
    'table' => 'compra',
    'column' => 'modificado_por',
    'referenced_table' => 'usuario',
]);
$foreignKeyExists = (bool) $stmt->fetchColumn();

if (!$foreignKeyExists && in_array('modificado_por', $existingColumns, true)) {
    try {
        $db->exec(
            'ALTER TABLE compra ADD CONSTRAINT fk_compra_modificado_por '
                . 'FOREIGN KEY (modificado_por) REFERENCES usuario(id_usuario) ON DELETE SET NULL'
        );
    } catch (Exception $e) {
        // La FK podría ya existir
    }
}

echo "Tabla compra actualizada con campos de seguimiento si era necesario.\n";
