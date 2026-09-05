-- ============================================================
-- EcoRuta  Migration: detalle de pago (comprobante / mixto / vuelto)
-- Agrega a pedidos:
--   1) comprobante_transferencia VARCHAR(80): n° de comprobante exigido
--      cuando el pago incluye transferencia
--   2) monto_efectivo  DECIMAL(10,2): parte abonada en efectivo (mixto)
--   3) monto_transferencia DECIMAL(10,2): parte abonada por transferencia
--   4) monto_recibido  DECIMAL(10,2): efectivo que recibió el repartidor
--   5) vuelto         DECIMAL(10,2): cambio a devolver = monto_recibido - monto_efectivo
-- (Idempotente: verifica INFORMATION_SCHEMA antes de alterar)
-- ============================================================

SET @db = DATABASE();

SET @sql = IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'pedidos' AND COLUMN_NAME = 'comprobante_transferencia') = 0,
  'ALTER TABLE pedidos ADD COLUMN comprobante_transferencia VARCHAR(80) NULL AFTER fecha_pago',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'pedidos' AND COLUMN_NAME = 'monto_efectivo') = 0,
  'ALTER TABLE pedidos ADD COLUMN monto_efectivo DECIMAL(10,2) NULL AFTER comprobante_transferencia',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'pedidos' AND COLUMN_NAME = 'monto_transferencia') = 0,
  'ALTER TABLE pedidos ADD COLUMN monto_transferencia DECIMAL(10,2) NULL AFTER monto_efectivo',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'pedidos' AND COLUMN_NAME = 'monto_recibido') = 0,
  'ALTER TABLE pedidos ADD COLUMN monto_recibido DECIMAL(10,2) NULL AFTER monto_transferencia',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'pedidos' AND COLUMN_NAME = 'vuelto') = 0,
  'ALTER TABLE pedidos ADD COLUMN vuelto DECIMAL(10,2) NULL AFTER monto_recibido',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;