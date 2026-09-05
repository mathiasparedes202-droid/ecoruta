<?php
require_once __DIR__ . '/../../vendor/autoload.php';

use Config\Database;

$db = (new Database())->connect();

$sql = "CREATE TABLE IF NOT EXISTS pedido (
    id_pedido INT AUTO_INCREMENT PRIMARY KEY,
    id_cliente INT NULL,
    nombre_cliente_manual VARCHAR(100),
    telefono_cliente VARCHAR(20),
    fecha_entrega TIMESTAMP NULL,
    franja_horaria ENUM('Mañana', 'Tarde') NULL,
    tipo_entrega ENUM('Delivery', 'Retiro') NOT NULL,
    direccion_entrega VARCHAR(150),
    telefono_receptor VARCHAR(20),
    costo_delivery DECIMAL(10,2) DEFAULT 0,
    presupuesto_cliente DECIMAL(10,2) DEFAULT 0,
    costo_estimado DECIMAL(10,2) DEFAULT 0,
    precio_final DECIMAL(10,2) DEFAULT 0,
    estado ENUM('Pendiente', 'En proceso', 'Terminado', 'Entregado'),
    observaciones VARCHAR(255),
    estado_logico TINYINT(1) DEFAULT 1,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    creado_por INT,
    FOREIGN KEY (id_cliente) REFERENCES cliente(id_cliente),
    FOREIGN KEY (creado_por) REFERENCES usuario(id_usuario) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

$db->exec($sql);

echo "Tabla pedido creada correctamente.\n";
