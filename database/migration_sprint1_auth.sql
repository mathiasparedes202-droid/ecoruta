USE ecoruta_db;

ALTER TABLE usuarios
  ADD COLUMN debe_cambiar_contraseña BOOLEAN NOT NULL DEFAULT TRUE;

CREATE TABLE IF NOT EXISTS recuperacion_contraseñas (
  id_recuperacion BIGINT PRIMARY KEY AUTO_INCREMENT,
  id_usuario INT NOT NULL,
  token_hash CHAR(64) NOT NULL UNIQUE,
  expira_en DATETIME NOT NULL,
  usado BOOLEAN NOT NULL DEFAULT FALSE,
  usado_en DATETIME NULL,
  fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_recuperacion_usuario
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario) ON DELETE CASCADE,
  INDEX idx_recuperacion_usuario (id_usuario, usado, expira_en)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

UPDATE usuarios
SET debe_cambiar_contraseña = TRUE
WHERE correo IN ('admin@ecoruta.com', 'carlos.mendoza@ecoruta.com');
