<?php
/**
 * Migración 47 — Sprint 4: Facturación legal delivery + control de timbrado.
 *
 * Tablas (ecoruta_db, idempotente, LOCAL y WEB):
 *  - timbrados: autorización fiscal (RUC, timbrado, vigencia, rango de folios).
 *  - facturas: cabecera del comprobante por pedido (folio único EEE-PPP-NNNNNNN).
 *  - factura_items: detalle de conceptos (tarifa base, IVA 10/5/exenta).
 *  - factura_auditoria: quién emitió/anuló/qué y cuándo.
 *
 * Criterio fiscal aplicado: precios con IVA incluido; servicio de reparto 10%.
 *   iva_10 = round(total * 10 / 110, 2) ; gravada_10 = total - iva_10.
 */
require_once __DIR__ . '/../../vendor/autoload.php';

use Config\Database;

if (!empty($argv[1])) {
    $_ENV['DB_NAME'] = $argv[1];
    putenv('DB_NAME=' . $argv[1]);
}

$db = (new Database())->connect();
echo 'Base objetivo: ' . ($db->query('SELECT DATABASE()')->fetchColumn()) . "\n";

function tableExists47(PDO $db, string $table): bool
{
    $st = $db->prepare(
        'SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t'
    );
    $st->execute(['t' => $table]);
    return (bool) $st->fetchColumn();
}

echo "== Migración 47: timbrados + facturas delivery ==\n";

if (!tableExists47($db, 'timbrados')) {
    $db->exec(
        "CREATE TABLE timbrados (
            id_timbrado INT AUTO_INCREMENT PRIMARY KEY,
            ruc VARCHAR(20) NOT NULL COMMENT 'RUC del emisor, ej 80000000-1',
            razon_social VARCHAR(150) NOT NULL,
            direccion VARCHAR(200) NULL,
            num_timbrado VARCHAR(20) NOT NULL COMMENT 'N° de timbrado SET',
            establecimiento CHAR(3) NOT NULL DEFAULT '001',
            punto_expedicion CHAR(3) NOT NULL DEFAULT '001',
            fecha_inicio DATE NOT NULL,
            fecha_fin DATE NOT NULL,
            nro_inicio INT NOT NULL DEFAULT 1,
            nro_fin INT NOT NULL,
            secuencia_actual INT NOT NULL DEFAULT 0 COMMENT 'Último folio usado',
            activo TINYINT(1) NOT NULL DEFAULT 1,
            fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_timbrados_num (num_timbrado),
            CONSTRAINT chk_timbrados_rango CHECK (nro_fin >= nro_inicio),
            CONSTRAINT chk_timbrados_fechas CHECK (fecha_fin >= fecha_inicio)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
    echo "OK: timbrados creada.\n";
} else {
    echo "-- timbrados ya existe.\n";
}

if (!tableExists47($db, 'facturas')) {
    $db->exec(
        "CREATE TABLE facturas (
            id_factura BIGINT AUTO_INCREMENT PRIMARY KEY,
            id_pedido BIGINT NOT NULL COMMENT 'Un pedido genera como máximo una factura (idempotencia)',
            id_timbrado INT NOT NULL,
            numero_completo VARCHAR(20) NOT NULL COMMENT 'EEE-PPP-NNNNNNN',
            ruc_emisor VARCHAR(20) NOT NULL,
            razon_social_emisor VARCHAR(150) NOT NULL,
            ruc_receptor VARCHAR(30) NULL,
            razon_social_receptor VARCHAR(150) NULL,
            direccion_receptor VARCHAR(200) NULL,
            condicion_venta ENUM('Contado','Crédito') NOT NULL DEFAULT 'Contado',
            total DECIMAL(12,2) NOT NULL,
            gravada_10 DECIMAL(12,2) NOT NULL DEFAULT 0,
            iva_10 DECIMAL(12,2) NOT NULL DEFAULT 0,
            gravada_5 DECIMAL(12,2) NOT NULL DEFAULT 0,
            iva_5 DECIMAL(12,2) NOT NULL DEFAULT 0,
            exenta DECIMAL(12,2) NOT NULL DEFAULT 0,
            cdc VARCHAR(64) NOT NULL COMMENT 'Hash de control interno (pre-fiscal)',
            qr_texto VARCHAR(500) NOT NULL,
            estado ENUM('Vigente','Anulada') NOT NULL DEFAULT 'Vigente',
            motivo_anulacion VARCHAR(255) NULL,
            fecha_emision DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            fecha_anulacion DATETIME NULL,
            origen ENUM('auto','manual','sync') NOT NULL DEFAULT 'manual',
            sync_uuid CHAR(36) NULL,
            UNIQUE KEY uq_facturas_numero (numero_completo),
            UNIQUE KEY uq_facturas_pedido (id_pedido),
            UNIQUE KEY uq_facturas_uuid (sync_uuid),
            INDEX idx_facturas_fecha (fecha_emision),
            INDEX idx_facturas_estado (estado),
            CONSTRAINT fk_facturas_pedido FOREIGN KEY (id_pedido) REFERENCES pedidos(id_pedido) ON DELETE RESTRICT,
            CONSTRAINT fk_facturas_timbrado FOREIGN KEY (id_timbrado) REFERENCES timbrados(id_timbrado) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
    echo "OK: facturas creada.\n";
} else {
    echo "-- facturas ya existe.\n";
}

if (!tableExists47($db, 'factura_items')) {
    $db->exec(
        "CREATE TABLE factura_items (
            id_item BIGINT AUTO_INCREMENT PRIMARY KEY,
            id_factura BIGINT NOT NULL,
            concepto VARCHAR(200) NOT NULL,
            cantidad DECIMAL(10,3) NOT NULL DEFAULT 1,
            precio_unitario DECIMAL(12,2) NOT NULL,
            subtotal DECIMAL(12,2) NOT NULL,
            iva_tipo TINYINT NOT NULL DEFAULT 10 COMMENT '0 exenta, 5 o 10',
            CONSTRAINT fk_items_factura FOREIGN KEY (id_factura) REFERENCES facturas(id_factura) ON DELETE CASCADE,
            CONSTRAINT chk_items_iva CHECK (iva_tipo IN (0,5,10))
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
    echo "OK: factura_items creada.\n";
} else {
    echo "-- factura_items ya existe.\n";
}

if (!tableExists47($db, 'factura_auditoria')) {
    $db->exec(
        "CREATE TABLE factura_auditoria (
            id_auditoria BIGINT AUTO_INCREMENT PRIMARY KEY,
            id_factura BIGINT NULL,
            accion VARCHAR(20) NOT NULL COMMENT 'emitida, anulada, reimpresa',
            id_usuario INT NULL,
            detalle VARCHAR(255) NULL,
            fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_aud_factura (id_factura)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
    echo "OK: factura_auditoria creada.\n";
} else {
    echo "-- factura_auditoria ya existe.\n";
}

echo "== Migración 47 completa. Aplica este mismo archivo en LOCAL y en WEB. ==\n";
