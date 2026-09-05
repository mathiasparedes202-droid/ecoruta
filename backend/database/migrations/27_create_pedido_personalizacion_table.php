<?php
require_once __DIR__ . '/../../vendor/autoload.php';

use Config\Database;

$db = (new Database())->connect();

$sql = "CREATE TABLE IF NOT EXISTS pedido_personalizacion (
    id_personalizacion INT AUTO_INCREMENT PRIMARY KEY,
    id_pedido INT NOT NULL,
    id_insumo INT NOT NULL,
    cantidad INT NOT NULL,
    es_adicional TINYINT(1) DEFAULT 0,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    creado_por INT,
    FOREIGN KEY (id_pedido) REFERENCES pedido(id_pedido) ON DELETE CASCADE,
    FOREIGN KEY (id_insumo) REFERENCES insumo(id_insumo),
    FOREIGN KEY (creado_por) REFERENCES usuario(id_usuario) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

$db->exec($sql);

echo "Tabla pedido_personalizacion creada correctamente.\n";
