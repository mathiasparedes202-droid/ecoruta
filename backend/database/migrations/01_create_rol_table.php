<?php
require_once __DIR__ . '/../../vendor/autoload.php';
use Config\Database;

$db = (new Database())->connect();

$sql = "CREATE TABLE IF NOT EXISTS rol (
    id_rol INT AUTO_INCREMENT PRIMARY KEY,
    nombre_rol VARCHAR(50) UNIQUE NOT NULL,
    descripcion TEXT,
    permisos_json JSON NOT NULL, 
    rol_del_sistema TINYINT(1) DEFAULT 0,
    estado TINYINT(1) DEFAULT 1,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

$db->exec($sql);

echo "Tabla rol creada correctamente.\n";