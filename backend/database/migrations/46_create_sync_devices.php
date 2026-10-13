<?php
/**
 * Migración 46 — Registro de dispositivos/nodos de sincronización.
 *
 * Tabla `sync_devices`: cada PC/teléfono/agente que sincroniza se identifica
 * con un device_id estable y el servidor guarda su última sincronización.
 * Es la base del inventario de nodos (visión 500 nodos) y del diagnóstico
 * ("¿qué sede no sincroniza hace 3 días?").
 *
 * Idempotente, seguro en LOCAL y en WEB. No toca datos existentes.
 * Uso: php backend/database/migrations/46_create_sync_devices.php [nombre_db]
 */
require_once __DIR__ . '/../../vendor/autoload.php';

use Config\Database;

if (!empty($argv[1])) {
    $_ENV['DB_NAME'] = $argv[1];
    putenv('DB_NAME=' . $argv[1]);
}

$db = (new Database())->connect();
echo 'Base objetivo: ' . ($db->query('SELECT DATABASE()')->fetchColumn()) . "\n";

function tableExists46(PDO $db, string $table): bool
{
    $st = $db->prepare(
        'SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t'
    );
    $st->execute(['t' => $table]);
    return (bool) $st->fetchColumn();
}

echo "== Migración 46: sync_devices ==\n";

if (!tableExists46($db, 'sync_devices')) {
    $db->exec(
        'CREATE TABLE sync_devices (
            id_dispositivo INT AUTO_INCREMENT PRIMARY KEY,
            device_id VARCHAR(64) NOT NULL,
            nombre VARCHAR(100) NOT NULL DEFAULT \'\',
            plataforma VARCHAR(30) NOT NULL DEFAULT \'\',
            primera_sincronizacion DATETIME NULL,
            ultima_sincronizacion DATETIME NULL,
            total_push INT NOT NULL DEFAULT 0,
            total_pull INT NOT NULL DEFAULT 0,
            UNIQUE KEY uq_sync_devices_device (device_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
    echo "OK: tabla sync_devices creada.\n";
} else {
    echo "-- sync_devices ya existe.\n";
}

echo "== Migración 46 completa. Aplica este mismo archivo en LOCAL y en WEB. ==\n";
