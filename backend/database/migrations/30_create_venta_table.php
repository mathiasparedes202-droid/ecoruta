<?php
require_once __DIR__ . '/../../vendor/autoload.php';

use Config\Database;

$db = (new Database())->connect();

$sql = "CREATE TABLE IF NOT EXISTS venta (
    id_venta INT AUTO_INCREMENT PRIMARY KEY,
    id_cliente INT NOT NULL,
    id_pedido INT NULL,
    numero_factura VARCHAR(50) NOT NULL,
    timbrado VARCHAR(50) NOT NULL,
    fecha_emision TIMESTAMP DEFAULT CURRENT_TIMESTAMP NOT NULL,
    estado_factura ENUM('Vigente', 'Anulada') NOT NULL,
    observaciones VARCHAR(255),
    tipo_comprobante ENUM('Ticket', 'Factura') NOT NULL,
    numero_comprobante VARCHAR(50),
    delivery_option ENUM('Retiro', 'Delivery') NOT NULL DEFAULT 'Retiro',
    direccion_entrega VARCHAR(150),
    total_factura DECIMAL(10,2) NOT NULL,
    iva_5 DECIMAL(10,2) DEFAULT 0,
    iva_10 DECIMAL(10,2) DEFAULT 0,
    subtotal_iva_exenta DECIMAL(10,2) DEFAULT 0,
    subtotal_iva_5 DECIMAL(10,2) DEFAULT 0,
    subtotal_iva_10 DECIMAL(10,2) DEFAULT 0,
    total_iva DECIMAL(10,2) DEFAULT 0,
    liquidacion_iva_5 DECIMAL(10,2) DEFAULT 0,
    liquidacion_iva_10 DECIMAL(10,2) DEFAULT 0,
    total_liquidacion DECIMAL(10,2) DEFAULT 0,
    condicion_venta ENUM('Contado', 'Crédito') NOT NULL,
    plazo INT,
    creado_por INT,
    FOREIGN KEY (id_cliente) REFERENCES cliente(id_cliente),
    FOREIGN KEY (id_pedido) REFERENCES pedido(id_pedido),
    FOREIGN KEY (creado_por) REFERENCES usuario(id_usuario) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

$db->exec($sql);

echo "Tabla venta creada correctamente.\n";
