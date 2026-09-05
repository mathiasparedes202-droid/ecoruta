<?php
require_once __DIR__ . '/../../vendor/autoload.php';

use Config\Database;

$db = (new Database())->connect();

$sql = "CREATE TABLE IF NOT EXISTS stock_reserva (
    id_reserva INT AUTO_INCREMENT PRIMARY KEY,
    id_insumo INT NOT NULL,
    id_pedido INT NOT NULL,
    cantidad DECIMAL(10,2) NOT NULL,
    estado ENUM('Reservado', 'Consumido', 'Cancelado') NOT NULL DEFAULT 'Reservado',
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_consumo TIMESTAMP NULL,
    creado_por INT NULL,
    FOREIGN KEY (id_insumo) REFERENCES insumo(id_insumo),
    FOREIGN KEY (id_pedido) REFERENCES pedido(id_pedido),
    FOREIGN KEY (creado_por) REFERENCES usuario(id_usuario) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

$db->exec($sql);

echo "Tabla stock_reserva creada correctamente.\n";
