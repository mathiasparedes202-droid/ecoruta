<?php
require_once __DIR__ . '/../../vendor/autoload.php';

use Config\Database;

$db = (new Database())->connect();

$sql = "CREATE TABLE IF NOT EXISTS compra (
    id_compra INT AUTO_INCREMENT PRIMARY KEY,
    id_proveedor INT NOT NULL,
    numero_factura VARCHAR(50) NOT NULL,
    timbrado VARCHAR(50) NOT NULL,
    fecha_emision TIMESTAMP,
    estado_factura ENUM('Vigente', 'Anulada', 'Pendiente') NOT NULL,
    tipo_comprobante ENUM('Factura', 'Recibo') NOT NULL,
    condicion_compra ENUM('Contado', 'Crédito') NOT NULL,
    plazo INT,
    observaciones VARCHAR(255),
    total_compra DECIMAL(10,2) NOT NULL CHECK(total_compra >= 0),
    iva_5 DECIMAL(10,2) CHECK(iva_5 >= 0),
    iva_10 DECIMAL(10,2) CHECK(iva_10 >= 0),
    subtotal_iva_exenta DECIMAL(10,2) CHECK(subtotal_iva_exenta >= 0),
    subtotal_iva_5 DECIMAL(10,2) CHECK(subtotal_iva_5 >= 0),
    subtotal_iva_10 DECIMAL(10,2) CHECK(subtotal_iva_10 >= 0),
    total_iva DECIMAL(10,2) CHECK(total_iva >= 0),
    liquidacion_iva_5 DECIMAL(10,2) CHECK(liquidacion_iva_5 >= 0),
    liquidacion_iva_10 DECIMAL(10,2) CHECK(liquidacion_iva_10 >= 0),
    total_liquidacion DECIMAL(10,2) CHECK(total_liquidacion >= 0),
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    creado_por INT,
    CONSTRAINT fk_compra_proveedor FOREIGN KEY (id_proveedor) REFERENCES proveedor(id_proveedor),
    CONSTRAINT fk_compra_usuario FOREIGN KEY (creado_por) REFERENCES usuario(id_usuario) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

$db->exec($sql);

echo "Tabla compra creada correctamente.\n";
