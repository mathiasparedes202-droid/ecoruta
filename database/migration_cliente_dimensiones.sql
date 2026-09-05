-- Migración: clientes con ubicación exacta + dimensiones de paquete
-- (nombre: migration_cliente_dimensiones.sql)

CREATE TABLE IF NOT EXISTS clientes (
  id_cliente INT PRIMARY KEY AUTO_INCREMENT,
  id_comercio INT NOT NULL,
  nombre VARCHAR(150) NOT NULL,
  telefono VARCHAR(20) NULL,
  direccion TEXT NOT NULL,
  lat DECIMAL(9,6) NOT NULL,
  lng DECIMAL(9,6) NOT NULL,
  referencia VARCHAR(255) NULL,
  fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,

  CONSTRAINT fk_cliente_comercio
    FOREIGN KEY (id_comercio) REFERENCES comercios(id_comercio) ON DELETE CASCADE,

  CONSTRAINT chk_cliente_nombre
    CHECK (LENGTH(TRIM(nombre)) >= 2),

  CONSTRAINT chk_cliente_telefono
    CHECK (telefono REGEXP '^[+]?[0-9\\s-]{7,20}$' OR telefono IS NULL),

  CONSTRAINT chk_cliente_coords
    CHECK (lat BETWEEN -27.6000 AND -19.2000 AND lng BETWEEN -62.7000 AND -54.2000),

  INDEX idx_cliente_comercio (id_comercio, nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dimensiones de la caja/paquete y vínculo al cliente destinatario en pedidos
ALTER TABLE pedidos
  ADD COLUMN id_cliente INT NULL AFTER id_comercio,
  ADD COLUMN alto_cm DECIMAL(6,2) NULL AFTER peso_kg,
  ADD COLUMN ancho_cm DECIMAL(6,2) NULL AFTER alto_cm,
  ADD COLUMN largo_cm DECIMAL(6,2) NULL AFTER ancho_cm,
  ADD COLUMN destinatario_nombre VARCHAR(150) NULL AFTER largo_cm,
  ADD COLUMN destinatario_telefono VARCHAR(20) NULL AFTER destinatario_nombre,
  ADD COLUMN dest_lat DECIMAL(9,6) NULL AFTER destinatario_telefono,
  ADD COLUMN dest_lng DECIMAL(9,6) NULL AFTER dest_lat,
  ADD CONSTRAINT fk_pedido_cliente
    FOREIGN KEY (id_cliente) REFERENCES clientes(id_cliente) ON DELETE SET NULL,
  ADD INDEX idx_pedido_cliente (id_cliente);