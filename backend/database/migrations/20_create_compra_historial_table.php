<?php
require_once __DIR__ . '/../../vendor/autoload.php';

use Config\Database;

$db = (new Database())->connect();

try {
    // Verificar si la tabla ya existe
    $stmt = $db->query("SHOW TABLES LIKE 'compra_historial'");
    $tableExists = $stmt->rowCount() > 0;

    if (!$tableExists) {
        // Crear tabla si no existe
        $sql = "CREATE TABLE compra_historial (
            id_historial INT AUTO_INCREMENT PRIMARY KEY,
            id_compra INT NOT NULL,
            usuario_id INT,
            campo_modificado VARCHAR(100) NOT NULL COMMENT 'Campo que fue modificado (observaciones, fecha_emision, etc)',
            valor_anterior LONGTEXT NULL,
            valor_nuevo LONGTEXT NULL,
            fecha_cambio TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY idx_compra (id_compra),
            KEY idx_fecha (fecha_cambio),
            KEY idx_usuario (usuario_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

        $db->exec($sql);
        echo "Tabla compra_historial creada correctamente.\n";
    } else {
        echo "Tabla compra_historial ya existe, verificando estructura...\n";
    }

    // Verificar y agregar foreign keys si no existen
    $schemaStmt = $db->query('SELECT DATABASE()');
    $schema = $schemaStmt->fetchColumn();

    // Verificar FK para id_compra
    $stmt = $db->prepare(
        'SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
         WHERE TABLE_SCHEMA = :schema AND TABLE_NAME = :table
         AND COLUMN_NAME = :column AND REFERENCED_TABLE_NAME = :referenced_table'
    );
    $stmt->execute([
        'schema' => $schema,
        'table' => 'compra_historial',
        'column' => 'id_compra',
        'referenced_table' => 'compra',
    ]);

    if (!$stmt->fetchColumn()) {
        $db->exec("ALTER TABLE compra_historial ADD CONSTRAINT fk_historial_compra
                  FOREIGN KEY (id_compra) REFERENCES compra(id_compra) ON DELETE CASCADE");
        echo "Foreign key fk_historial_compra agregada.\n";
    } else {
        echo "Foreign key fk_historial_compra ya existe.\n";
    }

    // Verificar FK para usuario_id
    $stmt->execute([
        'schema' => $schema,
        'table' => 'compra_historial',
        'column' => 'usuario_id',
        'referenced_table' => 'usuario',
    ]);

    if (!$stmt->fetchColumn()) {
        $db->exec("ALTER TABLE compra_historial ADD CONSTRAINT fk_historial_usuario
                  FOREIGN KEY (usuario_id) REFERENCES usuario(id_usuario) ON DELETE SET NULL");
        echo "Foreign key fk_historial_usuario agregada.\n";
    } else {
        echo "Foreign key fk_historial_usuario ya existe.\n";
    }

    echo "Tabla compra_historial verificada correctamente.\n";
} catch (Exception $e) {
    echo "Error en migración 20: " . $e->getMessage() . "\n";
}
