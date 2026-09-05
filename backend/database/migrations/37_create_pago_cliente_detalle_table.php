<?php
require_once __DIR__ . '/../../vendor/autoload.php';

use Config\Database;

$db = (new Database())->connect();

$sql = "CREATE TABLE IF NOT EXISTS pago_cliente_detalle (
    id_pago_detalle INT AUTO_INCREMENT PRIMARY KEY,
    id_pago INT NOT NULL,
    tipo_pago ENUM('Efectivo', 'Tarjeta', 'Transferencia', 'Crédito') NOT NULL,
    monto_pagado DECIMAL(10,2) DEFAULT 0,
    numero_comprobante VARCHAR(50),
    cantidad_cuotas INT NULL,
    numero_cuota INT NULL,
    monto_cuota DECIMAL(10,2) NULL,
    fecha_vencimiento TIMESTAMP NULL,
    saldo_pendiente DECIMAL(10,2) DEFAULT 0,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    creado_por INT,
    FOREIGN KEY (id_pago) REFERENCES pago_cliente_cabecera(id_pago),
    FOREIGN KEY (creado_por) REFERENCES usuario(id_usuario) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

$db->exec($sql);

echo "Tabla pago_cliente_detalle creada correctamente.\n";
