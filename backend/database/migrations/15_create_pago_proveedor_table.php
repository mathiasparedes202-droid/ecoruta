<?php
require_once __DIR__ . '/../../vendor/autoload.php';

use Config\Database;

$db = (new Database())->connect();

$sql = "DROP TABLE IF EXISTS pago_proveedor_detalle";
$db->exec($sql);

$sql = "DROP TABLE IF EXISTS pago_proveedor";
$db->exec($sql);

$sql = "CREATE TABLE pago_proveedor (
    id_pago_proveedor INT AUTO_INCREMENT PRIMARY KEY,
    id_compra INT NOT NULL,
    fecha_pago TIMESTAMP,
    monto_total DECIMAL(10,2) CHECK(monto_total >= 0),
    tipo_pago_general ENUM('Anticipo', 'Compra', 'Saldo') NOT NULL,
    estado ENUM('Pendiente', 'Confirmado', 'Anulado') NOT NULL,
    observaciones VARCHAR(255),
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    creado_por INT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

$db->exec($sql);

$sql2 = "CREATE TABLE pago_proveedor_detalle (
    id_detalle INT AUTO_INCREMENT PRIMARY KEY,
    id_pago_proveedor INT NOT NULL,
    metodo_pago ENUM('Efectivo', 'Tarjeta', 'Transferencia', 'Crédito') NOT NULL,
    monto_pagado DECIMAL(10,2) CHECK(monto_pagado >= 0),
    numero_comprobante VARCHAR(50),
    banco VARCHAR(100),
    fecha_vencimiento TIMESTAMP,
    estado_pago ENUM('Pendiente', 'Confirmado', 'Anulado') DEFAULT 'Confirmado',
    observaciones VARCHAR(255),
    cantidad_cuotas INT NULL,
    numero_cuota INT NULL,
    monto_cuota DECIMAL(10,2) NULL,
    saldo_pendiente DECIMAL(10,2),
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    creado_por INT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

$db->exec($sql2);

echo "Tablas pago_proveedor y pago_proveedor_detalle creadas correctamente.\n";
