<?php
/**
 * Migración 45 — Soporte de sincronización offline, módulo DELIVERY (EcoRuta).
 *
 * Agrega a `pedidos` (idempotente, seguro en LOCAL y en WEB):
 *  1) sync_uuid CHAR(36) UNIQUE -> clave de idempotencia. Cada pedido creado
 *     (local o web) lleva un UUID v4. Si el agente reintenta el push, el otro
 *     lado detecta el uuid y NO duplica el pedido ni el cobro.
 *  2) origen ENUM('local','web') -> saber dónde nació el pedido.
 *  3) updated_at TIMESTAMP       -> para el pull incremental (?since=).
 *
 * Regla de dinero: el sync NUNCA cambia pagado=1 a pagado=0. Si el pedido ya
 * figura pagado en el servidor, un reintento offline no lo revierte.
 *
 * Uso:
 *   php backend/database/migrations/45_add_sync_pedidos.php [nombre_db]
 *   (Sin argumento usa DB_NAME del .env. En Render, SYNC_DB_NAME/DB_NAME
 *   ya apuntan a la base con pedidos.)
 */
require_once __DIR__ . '/../../vendor/autoload.php';

use Config\Database;

if (!empty($argv[1])) {
    $_ENV['DB_NAME'] = $argv[1];
    putenv('DB_NAME=' . $argv[1]);
}

$db = (new Database())->connect();
echo 'Base objetivo: ' . ($db->query('SELECT DATABASE()')->fetchColumn()) . "\n";

function colExists45(PDO $db, string $table, string $col): bool
{
    $st = $db->prepare(
        "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c"
    );
    $st->execute(['t' => $table, 'c' => $col]);
    return (bool)$st->fetchColumn();
}

function indexExists45(PDO $db, string $table, string $index): bool
{
    $st = $db->prepare(
        "SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND INDEX_NAME = :i"
    );
    $st->execute(['t' => $table, 'i' => $index]);
    return (bool)$st->fetchColumn();
}

function tableExists45(PDO $db, string $table): bool
{
    $st = $db->prepare(
        "SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t"
    );
    $st->execute(['t' => $table]);
    return (bool)$st->fetchColumn();
}

echo "== Migración 45: sync pedidos (delivery) ==\n";

if (!tableExists45($db, 'pedidos')) {
    echo "-- Sin tabla pedidos en esta base: nada que hacer.\n";
    echo "== Migración 45 completa (sin cambios). ==\n";
    exit(0);
}

// 1) sync_uuid
if (!colExists45($db, 'pedidos', 'sync_uuid')) {
    $db->exec("ALTER TABLE pedidos ADD COLUMN sync_uuid CHAR(36) NULL AFTER confirmacion_datos");
    echo "OK: pedidos.sync_uuid agregado.\n";
} else {
    echo "-- pedidos.sync_uuid ya existe.\n";
}
if (!indexExists45($db, 'pedidos', 'uq_pedidos_sync_uuid')) {
    $db->exec("ALTER TABLE pedidos ADD UNIQUE KEY uq_pedidos_sync_uuid (sync_uuid)");
    echo "OK: UNIQUE uq_pedidos_sync_uuid creado.\n";
} else {
    echo "-- uq_pedidos_sync_uuid ya existe.\n";
}

// 2) origen
if (!colExists45($db, 'pedidos', 'origen')) {
    $db->exec("ALTER TABLE pedidos ADD COLUMN origen ENUM('local','web') NOT NULL DEFAULT 'web' AFTER sync_uuid");
    echo "OK: pedidos.origen agregado.\n";
} else {
    echo "-- pedidos.origen ya existe.\n";
}

// 3) updated_at (pull incremental)
if (!colExists45($db, 'pedidos', 'updated_at')) {
    $db->exec("ALTER TABLE pedidos ADD COLUMN updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER fecha_solicitud");
    echo "OK: pedidos.updated_at agregado.\n";
} else {
    echo "-- pedidos.updated_at ya existe.\n";
}

// Rellenos para filas preexistentes (sin esto el pull incremental las ignora
// para siempre y los uuid nulos rompen la idempotencia):
//  - updated_at NULL -> fecha_solicitud (conserva el orden cronológico)
//  - sync_uuid NULL  -> UUID() único por fila (idempotencia estable)
$backfillTs = (int) $db->query('SELECT COUNT(*) FROM pedidos WHERE updated_at IS NULL')->fetchColumn();
if ($backfillTs > 0) {
    $db->exec('UPDATE pedidos SET updated_at = fecha_solicitud WHERE updated_at IS NULL');
    echo "OK: updated_at rellenado en $backfillTs pedido(s).\n";
}
$backfillUuid = (int) $db->query('SELECT COUNT(*) FROM pedidos WHERE sync_uuid IS NULL OR sync_uuid = \'\'')->fetchColumn();
if ($backfillUuid > 0) {
    $db->exec("UPDATE pedidos SET sync_uuid = UUID(), origen = 'web' WHERE sync_uuid IS NULL OR sync_uuid = ''");
    echo "OK: sync_uuid generado en $backfillUuid pedido(s).\n";
}

echo "== Migración 45 completa. Aplica este mismo archivo en LOCAL y en WEB. ==\n";
