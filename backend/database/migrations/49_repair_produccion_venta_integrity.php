<?php
require_once __DIR__ . '/../../vendor/autoload.php';

use Config\Database;

$db = (new Database())->connect();

// `insumo.stock` es el saldo operativo que consumen las ventas. Se normaliza
// el espejo histórico para que compras y producción no mantengan dos saldos.
try {
    $db->exec('DELETE s1 FROM stock_insumo s1 INNER JOIN stock_insumo s2 ON s1.id_insumo = s2.id_insumo AND s1.id_stock_insumo > s2.id_stock_insumo');
    $db->exec('INSERT INTO stock_insumo (id_insumo, cantidad, cantidad_minima, fecha_actualizacion) SELECT i.id_insumo, i.stock, 0, NOW() FROM insumo i LEFT JOIN stock_insumo s ON s.id_insumo = i.id_insumo WHERE s.id_insumo IS NULL');
    $db->exec('UPDATE stock_insumo s INNER JOIN insumo i ON i.id_insumo = s.id_insumo SET s.cantidad = i.stock, s.fecha_actualizacion = NOW()');
    $index = $db->query("SHOW INDEX FROM stock_insumo WHERE Key_name = 'uq_stock_insumo_producto'")->fetch();
    if (!$index) {
        $db->exec('ALTER TABLE stock_insumo ADD UNIQUE KEY uq_stock_insumo_producto (id_insumo)');
    }
} catch (Throwable $e) {
    throw $e;
}

// El trigger anterior registraba una salida sin precio_unitario obligatorio y
// no validaba saldo. Los arreglos se controlan por receta al finalizar su orden.
$db->exec('DROP TRIGGER IF EXISTS trg_venta_detalle');

$db->exec('DROP TRIGGER IF EXISTS trg_compra_detalle_insert');
$db->exec("CREATE TRIGGER trg_compra_detalle_insert AFTER INSERT ON compra_detalle FOR EACH ROW
BEGIN
    UPDATE insumo SET stock = stock + NEW.cantidad WHERE id_insumo = NEW.id_insumo;
    INSERT INTO stock_insumo (id_insumo, cantidad, cantidad_minima, fecha_actualizacion)
    VALUES (NEW.id_insumo, NEW.cantidad, 0, NOW())
    ON DUPLICATE KEY UPDATE cantidad = cantidad + NEW.cantidad, fecha_actualizacion = NOW();
    INSERT INTO movimiento_stock_insumo (id_insumo, tipo_movimiento, id_compra, cantidad, fecha_movimiento)
    VALUES (NEW.id_insumo, 'Compra', NEW.id_compra, NEW.cantidad, NOW());
END");

$db->exec('DROP TRIGGER IF EXISTS trg_validar_stock');
$db->exec("CREATE TRIGGER trg_validar_stock BEFORE INSERT ON produccion_consumo FOR EACH ROW
BEGIN
    DECLARE stock_actual DECIMAL(10,2);
    SELECT stock INTO stock_actual FROM insumo WHERE id_insumo = NEW.id_insumo FOR UPDATE;
    IF stock_actual IS NULL OR stock_actual < NEW.cantidad_usada THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Stock insuficiente';
    END IF;
END");

$db->exec('DROP TRIGGER IF EXISTS trg_produccion_consumo');
$db->exec("CREATE TRIGGER trg_produccion_consumo AFTER INSERT ON produccion_consumo FOR EACH ROW
BEGIN
    UPDATE insumo SET stock = stock - NEW.cantidad_usada WHERE id_insumo = NEW.id_insumo;
    UPDATE stock_insumo SET cantidad = cantidad - NEW.cantidad_usada, fecha_actualizacion = NOW() WHERE id_insumo = NEW.id_insumo;
    INSERT INTO movimiento_stock_insumo (id_insumo, tipo_movimiento, id_orden_produccion, cantidad, fecha_movimiento)
    VALUES (NEW.id_insumo, 'Consumo', NEW.id_orden, NEW.cantidad_usada, NOW());
END");

echo "Integridad de producción y ventas reparada.\n";
