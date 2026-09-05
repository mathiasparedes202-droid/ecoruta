<?php
require_once __DIR__ . '/../../vendor/autoload.php';

use Config\Database;

$db = (new Database())->connect();

$sql = "CREATE TABLE IF NOT EXISTS producto (
    id_producto INT AUTO_INCREMENT PRIMARY KEY,
    nombre_producto VARCHAR(100) UNIQUE NOT NULL,
    tipo_producto ENUM('Ramo', 'Corona', 'Detalle') NOT NULL,
    descripcion VARCHAR(150),
    costo_produccion DECIMAL(10,2) DEFAULT 0,
    precio_base DECIMAL(10,2) NOT NULL,
    imagen LONGBLOB,
    estado TINYINT(1) DEFAULT 1,
    es_personalizable TINYINT(1) DEFAULT 0,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    creado_por INT,
    FOREIGN KEY (creado_por) REFERENCES usuario(id_usuario) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

$db->exec($sql);

echo "Tabla producto creada correctamente.\n";
