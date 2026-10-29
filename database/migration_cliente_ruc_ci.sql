-- Agrega RUC / C.I. opcional al directorio de clientes existente.
ALTER TABLE clientes
  ADD COLUMN ruc_ci VARCHAR(20) NULL AFTER nombre;
