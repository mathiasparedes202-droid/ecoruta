<?php
require_once __DIR__ . '/../../vendor/autoload.php';

use Config\Database;

$db = (new Database())->connect();

$sql = "CREATE TABLE IF NOT EXISTS insumo (
    id_insumo INT AUTO_INCREMENT PRIMARY KEY,
    nombre_insumo VARCHAR(255) NOT NULL,
    descripcion TEXT,
    id_tipo_insumo INT NOT NULL,
    id_variedad INT,
    id_color INT,
    id_categoria INT NOT NULL,
    id_unidad INT NOT NULL,
    estado TINYINT(1) DEFAULT 1,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    creado_por INT,
    FOREIGN KEY (id_tipo_insumo) REFERENCES tipo_insumo(id_tipo_insumo),
    FOREIGN KEY (id_variedad) REFERENCES variedad(id_variedad) ON DELETE SET NULL,
    FOREIGN KEY (id_color) REFERENCES color(id_color) ON DELETE SET NULL,
    FOREIGN KEY (id_categoria) REFERENCES categoria(id_categoria),
    FOREIGN KEY (id_unidad) REFERENCES unidad(id_unidad),
    FOREIGN KEY (creado_por) REFERENCES usuario(id_usuario) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

$db->exec($sql);

echo "Tabla insumo creada correctamente.\n";
