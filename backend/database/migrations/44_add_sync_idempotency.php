<?php
/**
 * Migración 44 — Soporte de sincronización offline (intermediario PC).
 *
 * Qué hace (idempotente, seguro correr en LOCAL y en WEB):
 *  1) venta.sync_uuid CHAR(36) UNIQUE  -> clave de idempotencia. Cada venta creada
 *     (local o web) lleva un UUID v4. Si el sync reintenta el push, la web detecta
 *     el uuid y NO duplica la factura.
 *  2) venta.origen ENUM('local','web')  -> saber dónde nació la venta.
 *  3) venta.updated_at TIMESTAMP        -> para el pull incremental (?since=).
 *  4) UNIQUE(numero_factura)            -> segunda barrera anti-duplicado a nivel DB.
 *     Si dos ventas distintas chocan en número, MySQL rechaza y el SyncController
 *     reasigna el siguiente folio disponible.
 *  5) Triggers anti-stock-negativo      -> aunque el PHP valide, el trigger es la
 *     última defensa: impide que insumo.stock o stock_producto.cantidad bajen de 0,
 *     tanto en local como en web, durante y después del sync.
 *
 * Uso:
 *   php backend/database/migrations/44_add_sync_idempotency.php   (desde raíz backend)
 * O vía navegador (solo admin local):
 *   http://localhost:8000/migrate.php  (si tu migrate.php corre todas)
 */
require_once __DIR__ . '/../../vendor/autoload.php';

use Config\Database;

// Permite elegir la base: php 44_add_sync_idempotency.php [nombre_db]
// (Por defecto usa DB_NAME del .env. En este repo las tablas venta/stock
// viven en floracia_db, mientras ecoruta_db es el módulo delivery.)
if (!empty($argv[1])) {
    $_ENV['DB_NAME'] = $argv[1];
    putenv('DB_NAME=' . $argv[1]);
}

$db = (new Database())->connect();
echo 'Base objetivo: ' . ($db->query('SELECT DATABASE()')->fetchColumn()) . "\n";

function colExists(PDO $db, string $table, string $col): bool
{
    $st = $db->prepare(
        "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c"
    );
    $st->execute(['t' => $table, 'c' => $col]);
    return (bool)$st->fetchColumn();
}

function indexExists(PDO $db, string $table, string $index): bool
{
    $st = $db->prepare(
        "SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND INDEX_NAME = :i"
    );
    $st->execute(['t' => $table, 'i' => $index]);
    return (bool)$st->fetchColumn();
}

function triggerExists(PDO $db, string $name): bool
{
    $st = $db->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TRIGGERS WHERE TRIGGER_SCHEMA = DATABASE() AND TRIGGER_NAME = :n");
    $st->execute(['n' => $name]);
    return (bool)$st->fetchColumn();
}

echo "== Migración 44: sync idempotencia + anti-negativo ==\n";

// 1) sync_uuid
if (!colExists($db, 'venta', 'sync_uuid')) {
    $db->exec("ALTER TABLE venta ADD COLUMN sync_uuid CHAR(36) NULL AFTER numero_comprobante");
    echo "OK: venta.sync_uuid agregado.\n";
} else {
    echo "-- venta.sync_uuid ya existe.\n";
}
if (!indexExists($db, 'venta', 'uq_venta_sync_uuid')) {
    // Limpiar duplicados NULL-safe: los NULL no chocan en UNIQUE, directo.
    $db->exec("ALTER TABLE venta ADD UNIQUE KEY uq_venta_sync_uuid (sync_uuid)");
    echo "OK: UNIQUE uq_venta_sync_uuid creado.\n";
} else {
    echo "-- uq_venta_sync_uuid ya existe.\n";
}

// 2) origen
if (!colExists($db, 'venta', 'origen')) {
    $db->exec("ALTER TABLE venta ADD COLUMN origen ENUM('local','web') NOT NULL DEFAULT 'web' AFTER sync_uuid");
    echo "OK: venta.origen agregado.\n";
} else {
    echo "-- venta.origen ya existe.\n";
}

// 3) updated_at (para pull incremental)
if (!colExists($db, 'venta', 'updated_at')) {
    $db->exec("ALTER TABLE venta ADD COLUMN updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER fecha_emision");
    $db->exec("UPDATE venta SET updated_at = fecha_emision WHERE updated_at IS NULL");
    echo "OK: venta.updated_at agregado.\n";
} else {
    echo "-- venta.updated_at ya existe.\n";
}

// 4) UNIQUE numero_factura (barrera DB anti-duplicado de folio)
if (!indexExists($db, 'venta', 'uq_venta_numero_factura')) {
    // Si hay folios duplicados históricos, los reportamos en vez de romper.
    $dups = $db->query(
        "SELECT numero_factura, COUNT(*) c FROM venta WHERE numero_factura IS NOT NULL AND numero_factura <> '' GROUP BY numero_factura HAVING c > 1 LIMIT 5"
    )->fetchAll(PDO::FETCH_ASSOC);
    if ($dups) {
        echo "AVISO: hay folios duplicados históricos, NO se crea el UNIQUE. Corrige primero:\n";
        foreach ($dups as $d) {
            echo "  - {$d['numero_factura']} x{$d['c']}\n";
        }
    } else {
        $db->exec("ALTER TABLE venta ADD UNIQUE KEY uq_venta_numero_factura (numero_factura)");
        echo "OK: UNIQUE uq_venta_numero_factura creado.\n";
    }
} else {
    echo "-- uq_venta_numero_factura ya existe.\n";
}

// updated_at también en stock para pull de niveles
if (colExists($db, 'stock_producto', 'fecha_actualizacion')) {
    echo "-- stock_producto.fecha_actualizacion ya sirve para pull.\n";
}

// 5) Triggers anti-negativo -------------------------------------------------
// insumo.stock
if (!triggerExists($db, 'trg_insumo_no_negativo')) {
    $db->exec("DROP TRIGGER IF EXISTS trg_insumo_no_negativo");
    $db->exec("
        CREATE TRIGGER trg_insumo_no_negativo BEFORE UPDATE ON insumo
        FOR EACH ROW
        BEGIN
            IF NEW.stock < 0 THEN
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'STOCK_NEGATIVO: insumo.stock no puede quedar negativo';
            END IF;
        END
    ");
    echo "OK: trigger trg_insumo_no_negativo creado.\n";
} else {
    echo "-- trg_insumo_no_negativo ya existe.\n";
}

// stock_producto.cantidad
$hasStockProd = $db->query("SHOW TABLES LIKE 'stock_producto'")->fetch() !== false;
if ($hasStockProd && !triggerExists($db, 'trg_stockprod_no_negativo')) {
    $db->exec("DROP TRIGGER IF EXISTS trg_stockprod_no_negativo");
    $db->exec("
        CREATE TRIGGER trg_stockprod_no_negativo BEFORE UPDATE ON stock_producto
        FOR EACH ROW
        BEGIN
            IF NEW.cantidad < 0 THEN
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'STOCK_NEGATIVO: stock_producto.cantidad no puede quedar negativo';
            END IF;
        END
    ");
    echo "OK: trigger trg_stockprod_no_negativo creado.\n";
} elseif ($hasStockProd) {
    echo "-- trg_stockprod_no_negativo ya existe.\n";
}

echo "== Migración 44 completa. Aplica este mismo archivo en LOCAL y en WEB. ==\n";
