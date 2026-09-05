<?php
require '../vendor/autoload.php';

try {
    $db = (new Config\Database())->connect();

    echo "<h2>Ejecutando migraciones para módulo de Ventas...</h2>";

    // Desactivar comprobaciones de claves foráneas
    $db->exec("SET FOREIGN_KEY_CHECKS=0");

    // Drop tables if they exist (para recrear con estructura correcta)
    echo "Eliminando tablas existentes...<br>";
    $db->exec("DROP TABLE IF EXISTS detalle_venta");
    $db->exec("DROP TABLE IF EXISTS venta");

    // Reactivar comprobaciones de claves foráneas
    $db->exec("SET FOREIGN_KEY_CHECKS=1");

    // Create venta table
    echo "Creando tabla venta...<br>";
    $sqlVenta = "CREATE TABLE IF NOT EXISTS venta (
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
    
    $db->exec($sqlVenta);
    echo "Tabla venta creada<br>";

    // Create detalle_venta table
    echo "Creando tabla detalle_venta...<br>";
    $sqlDetalleVenta = "CREATE TABLE IF NOT EXISTS detalle_venta (
        id_detalle_venta INT AUTO_INCREMENT PRIMARY KEY,
        id_venta INT NOT NULL,
        id_insumo INT NOT NULL,
        cantidad INT NOT NULL,
        precio_unitario DECIMAL(10,2) DEFAULT 0,
        iva_tipo INT,
        subtotal DECIMAL(10,2) DEFAULT 0,
        fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        creado_por INT,
        FOREIGN KEY (id_venta) REFERENCES venta(id_venta) ON DELETE CASCADE,
        FOREIGN KEY (id_insumo) REFERENCES insumo(id_insumo),
        FOREIGN KEY (creado_por) REFERENCES usuario(id_usuario) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    
    $db->exec($sqlDetalleVenta);
    echo "Tabla detalle_venta creada<br>";

    echo "<h3 style='color: green;'>Migraciones completadas exitosamente</h3>";
    echo "<p><a href='/floreria_system/frontend/'>Volver al sistema</a></p>";

} catch (Exception $e) {
    echo "<h3 style='color: red;'>Error en la migración:</h3>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}
?>
