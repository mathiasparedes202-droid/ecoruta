-- ============================================================
-- EcoRuta  Migration: pasarela de pago (efectivo / transferencia)
-- Agrega a pedidos:
--   1) metodo_pago   VARCHAR(15): 'efectivo' | 'transferencia'
--   2) pagado        TINYINT(1): 1 cuando el pago fue confirmado
--   3) fecha_pago    DATETIME: cuándo se confirmó
-- (Aplicar con el script idempotente previo, no correr dos veces directo)
-- ============================================================

ALTER TABLE pedidos
  ADD COLUMN metodo_pago VARCHAR(15) NOT NULL DEFAULT 'efectivo' AFTER tarifa_ecologica,
  ADD COLUMN pagado TINYINT(1) NOT NULL DEFAULT 0 AFTER metodo_pago,
  ADD COLUMN fecha_pago DATETIME NULL AFTER pagado;