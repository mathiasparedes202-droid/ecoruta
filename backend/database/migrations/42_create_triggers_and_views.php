<?php
require_once __DIR__ . '/../../vendor/autoload.php';

use Config\Database;

$db = (new Database())->connect();

// Triggers
echo "Creando triggers...\n";

// Trigger para actualizar stock al comprar insumos
$db->exec("DROP TRIGGER IF EXISTS trg_compra_detalle_insert");
$db->exec("
CREATE TRIGGER trg_compra_detalle_insert
AFTER INSERT ON compra_detalle
FOR EACH ROW
BEGIN
    -- Insertar si no existe
    INSERT INTO stock_insumo (id_insumo, cantidad, cantidad_minima, fecha_actualizacion)
    VALUES (NEW.id_insumo, NEW.cantidad, 0, NOW())
    ON DUPLICATE KEY UPDATE
        cantidad = cantidad + NEW.cantidad,
        fecha_actualizacion = NOW();

    -- Registrar movimiento
    INSERT INTO movimiento_stock_insumo (
        id_insumo, tipo_movimiento, id_compra, cantidad, fecha_movimiento
    )
    VALUES (
        NEW.id_insumo, 'Compra', NEW.id_compra, NEW.cantidad, NOW()
    );
END
");

// Trigger para descontar stock al producir
$db->exec("DROP TRIGGER IF EXISTS trg_produccion_consumo");
$db->exec("
CREATE TRIGGER trg_produccion_consumo
AFTER INSERT ON produccion_consumo
FOR EACH ROW
BEGIN
    UPDATE stock_insumo
    SET cantidad = cantidad - NEW.cantidad_usada,
        fecha_actualizacion = NOW()
    WHERE id_insumo = NEW.id_insumo;

    INSERT INTO movimiento_stock_insumo (
        id_insumo, tipo_movimiento, id_orden_produccion, cantidad, fecha_movimiento
    )
    VALUES (
        NEW.id_insumo, 'Consumo', NEW.id_orden, NEW.cantidad_usada, NOW()
    );
END
");

// Evitar stock negativo
$db->exec("DROP TRIGGER IF EXISTS trg_validar_stock");
$db->exec("
CREATE TRIGGER trg_validar_stock
BEFORE INSERT ON produccion_consumo
FOR EACH ROW
BEGIN
    DECLARE stock_actual DECIMAL(10,2);

    SELECT cantidad INTO stock_actual
    FROM stock_insumo
    WHERE id_insumo = NEW.id_insumo;

    IF stock_actual < NEW.cantidad_usada THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Stock insuficiente';
    END IF;
END
");

// Actualizar stock de productos al vender
$db->exec("DROP TRIGGER IF EXISTS trg_venta_detalle");
$db->exec("
CREATE TRIGGER trg_venta_detalle
AFTER INSERT ON detalle_venta
FOR EACH ROW
BEGIN
    UPDATE stock_producto
    SET cantidad = cantidad - NEW.cantidad,
        fecha_actualizacion = NOW()
    WHERE id_producto = NEW.id_producto;

    INSERT INTO movimiento_stock_producto (
        id_producto, stock_anterior, tipo_movimiento, cantidad, fecha_movimiento
    )
    VALUES (
        NEW.id_producto, 0, 'Salida', NEW.cantidad, NOW()
    );
END
");

// Procedimiento para generar orden de producción
$db->exec("DROP PROCEDURE IF EXISTS generar_orden_produccion");
$db->exec("
CREATE PROCEDURE generar_orden_produccion(IN p_id_pedido INT)
BEGIN
    INSERT INTO orden_produccion (
        id_pedido,
        fecha_inicio,
        estado,
        fecha_creacion
    )
    VALUES (
        p_id_pedido,
        NOW(),
        'Pendiente',
        NOW()
    );
END
");

// Vistas
echo "Creando vistas...\n";

// Vista de stock de insumos
$db->exec("DROP VIEW IF EXISTS vw_stock_insumos");
$db->exec("
CREATE VIEW vw_stock_insumos AS
SELECT
    i.nombre_insumo,
    t.nombre_tipo AS tipo,
    c.nombre_color AS color,
    v.nombre_variedad AS variedad,
    s.cantidad,
    s.cantidad_minima
FROM stock_insumo s
JOIN insumo i ON s.id_insumo = i.id_insumo
LEFT JOIN tipo_insumo t ON i.id_tipo_insumo = t.id_tipo_insumo
LEFT JOIN color c ON i.id_color = c.id_color
LEFT JOIN variedad v ON i.id_variedad = v.id_variedad;
");

// Vista de pedidos
$db->exec("DROP VIEW IF EXISTS vw_pedidos");
$db->exec("
CREATE VIEW vw_pedidos AS
SELECT
    p.id_pedido,
    p.fecha_entrega,
    p.estado,
    p.precio_final,
    COALESCE(c.razon_social, p.nombre_cliente_manual) AS cliente_nombre
FROM pedido p
LEFT JOIN cliente c ON p.id_cliente = c.id_cliente;
");

// Consumo de insumos
$db->exec("DROP VIEW IF EXISTS vw_consumo_insumos");
$db->exec("
CREATE VIEW vw_consumo_insumos AS
SELECT
    i.nombre_insumo,
    SUM(pc.cantidad_usada) AS total_usado
FROM produccion_consumo pc
JOIN insumo i ON pc.id_insumo = i.id_insumo
GROUP BY i.nombre_insumo;
");

echo "Triggers y vistas creados correctamente.\n";