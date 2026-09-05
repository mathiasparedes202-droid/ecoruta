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
if (!in_array('fecha_anulacion', $existingColumns, true)) {
    $alterStatements[] = 'ADD COLUMN fecha_anulacion TIMESTAMP NULL';
}
if (!in_array('anulado_por', $existingColumns, true)) {
    $alterStatements[] = 'ADD COLUMN anulado_por INT';
}

if (!empty($alterStatements)) {
    $sql = 'ALTER TABLE compra ' . implode(', ', $alterStatements);
    $db->exec($sql);
}

$stmt = $db->prepare(
    'SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE '
        . 'WHERE TABLE_SCHEMA = :schema AND TABLE_NAME = :table '
        . 'AND COLUMN_NAME = :column AND REFERENCED_TABLE_NAME = :referenced_table'
);
$stmt->execute([
    'schema' => $schema,
    'table' => 'compra',
    'column' => 'anulado_por',
    'referenced_table' => 'usuario',
]);
$foreignKeyExists = (bool) $stmt->fetchColumn();

if (!$foreignKeyExists && in_array('anulado_por', $existingColumns, true)) {
    $db->exec(
        'ALTER TABLE compra ADD CONSTRAINT fk_compra_anulado_por '
            . 'FOREIGN KEY (anulado_por) REFERENCES usuario(id_usuario) ON DELETE SET NULL'
    );
}

echo "Tabla compra actualizada con campos de anulación si era necesario.\n";
