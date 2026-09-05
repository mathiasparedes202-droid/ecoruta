<?php
require_once __DIR__ . '/../../vendor/autoload.php';

use Config\Database;

$db = (new Database())->connect();

$sql = "CREATE TABLE IF NOT EXISTS movimiento_stock_insumo (
    id_movimiento INT AUTO_INCREMENT PRIMARY KEY,
    id_insumo INT NOT NULL,
    tipo_movimiento ENUM('Compra', 'Consumo', 'Ajuste') NOT NULL,
    id_compra INT NULL,
    id_orden_produccion INT NULL,
    motivo VARCHAR(150),
    cantidad DECIMAL(10,2) NOT NULL,
    fecha_movimiento TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    creado_por INT,
    FOREIGN KEY (id_insumo) REFERENCES insumo(id_insumo),
    FOREIGN KEY (id_compra) REFERENCES compra(id_compra),
    FOREIGN KEY (id_orden_produccion) REFERENCES orden_produccion(id_orden),
    FOREIGN KEY (creado_por) REFERENCES usuario(id_usuario) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

$db->exec($sql);

echo "Tabla movimiento_stock_insumo creada correctamente.\n";
