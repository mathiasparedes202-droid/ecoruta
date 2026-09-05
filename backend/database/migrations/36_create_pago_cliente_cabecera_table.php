<?php
require_once __DIR__ . '/../../vendor/autoload.php';

use Config\Database;

$db = (new Database())->connect();

$sql = "CREATE TABLE IF NOT EXISTS pago_cliente_cabecera (
    id_pago INT AUTO_INCREMENT PRIMARY KEY,
    id_venta INT NOT NULL,
    fecha_pago TIMESTAMP NULL,
    monto_total DECIMAL(10,2) DEFAULT 0,
    tipo_pago_general ENUM('Anticipo', 'Venta', 'Saldo') NOT NULL,
    estado ENUM('Pendiente', 'Confirmado', 'Anulado') NOT NULL,
    observaciones VARCHAR(255),
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    creado_por INT,
    FOREIGN KEY (id_venta) REFERENCES venta(id_venta),
    FOREIGN KEY (creado_por) REFERENCES usuario(id_usuario) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

$db->exec($sql);

echo "Tabla pago_cliente_cabecera creada correctamente.\n";
