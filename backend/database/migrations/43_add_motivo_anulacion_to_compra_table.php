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

if (!in_array('motivo_anulacion', $existingColumns, true)) {
    $alterStatements[] = 'ADD COLUMN motivo_anulacion LONGTEXT NULL AFTER anulado_por';
}

if (!empty($alterStatements)) {
    $sql = 'ALTER TABLE compra ' . implode(', ', $alterStatements);
    $db->exec($sql);
}

echo "Tabla compra actualizada con campo motivo_anulacion si era necesario.\n";
