-- ============================================================
-- EcoRuta · Migración: turnos reales + notificaciones + cancelación
-- Agrega:
--   1) Estado 5 "Cancelado" en estados_pedido (nombre + descripción)
--   2) Columnas motivo_cancelacion / fecha_cancelacion en pedidos
--   3) Tabla turnos (sesiones reales del repartidor)
--   4) Tabla notificaciones (avisos a administradores y repartidores)
-- ============================================================

-- 1) Estado Cancelado (id 5 ya existe vacío en la BD viva)
INSERT INTO estados_pedido (id_estado, nombre_estado, descripcion)
VALUES (5, 'Cancelado', 'Pedido cancelado o que no pudo completarse')
ON DUPLICATE KEY UPDATE
  nombre_estado = VALUES(nombre_estado),
  descripcion = VALUES(descripcion);

-- 2) Columnas de cancelación en pedidos
ALTER TABLE pedidos
  ADD COLUMN motivo_cancelacion VARCHAR(255) NULL AFTER observaciones,
  ADD COLUMN fecha_cancelacion DATETIME NULL AFTER motivo_cancelacion;

-- 3) Turnos (sesión de trabajo del repartidor en el día)
CREATE TABLE IF NOT EXISTS turnos (
  id_turno BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  id_repartidor INT NOT NULL,
  estado VARCHAR(12) NOT NULL DEFAULT 'activo' COMMENT 'activo | pausado | finalizado',
  fecha_inicio DATETIME NOT NULL,
  fecha_fin DATETIME NULL,
  fecha_pausa DATETIME NULL,
  tiempo_pausado_seg INT UNSIGNED NOT NULL DEFAULT 0,
  fecha_creacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_turno_repartidor FOREIGN KEY (id_repartidor)
    REFERENCES repartidores(id_repartidor) ON DELETE CASCADE,
  INDEX idx_turnos_repartidor (id_repartidor, fecha_inicio)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4) Notificaciones (avisos internos por usuario)
CREATE TABLE IF NOT EXISTS notificaciones (
  id_notificacion BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  id_usuario_destino INT NOT NULL,
  tipo VARCHAR(40) NOT NULL COMMENT 'nuevo_pedido | pedido_asignado | pedido_cancelado | pedido_entregado',
  titulo VARCHAR(255) NOT NULL,
  mensaje TEXT NULL,
  id_pedido BIGINT NULL,
  leida TINYINT(1) NOT NULL DEFAULT 0,
  fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_notificacion_usuario FOREIGN KEY (id_usuario_destino)
    REFERENCES usuarios(id_usuario) ON DELETE CASCADE,
  INDEX idx_notificaciones_usuario (id_usuario_destino, leida, fecha)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;