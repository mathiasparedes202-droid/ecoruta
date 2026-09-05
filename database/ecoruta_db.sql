CREATE DATABASE IF NOT EXISTS ecoruta_db
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;
USE ecoruta_db;

CREATE TABLE roles (
  id_rol SMALLINT PRIMARY KEY AUTO_INCREMENT,
  nombre VARCHAR(30) NOT NULL UNIQUE,
  descripcion VARCHAR(150),
  fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE usuarios (
  id_usuario INT PRIMARY KEY AUTO_INCREMENT,
  id_rol SMALLINT NOT NULL,
  correo VARCHAR(150) NOT NULL UNIQUE,
  contraseña_hash VARCHAR(255) NOT NULL COMMENT 'NUNCA almacenar contraseña en texto plano',
  documento_identidad VARCHAR(20) NOT NULL UNIQUE COMMENT 'RUC / Cédula',
  nombre_completo VARCHAR(150) NOT NULL,
  telefono VARCHAR(20),
  fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
  fecha_ultima_modificacion DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  fecha_ultimo_acceso DATETIME NULL,
  activo BOOLEAN DEFAULT TRUE,
  debe_cambiar_contraseña BOOLEAN NOT NULL DEFAULT TRUE,

  CONSTRAINT fk_usuario_rol
    FOREIGN KEY (id_rol) REFERENCES roles(id_rol) ON DELETE RESTRICT,

  CONSTRAINT chk_correo_valido
    CHECK (correo REGEXP '^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\\.[A-Za-z]{2,}$'),

  CONSTRAINT chk_nombre_completo
    CHECK (LENGTH(TRIM(nombre_completo)) >= 3),

  CONSTRAINT chk_telefono_valido
    CHECK (telefono REGEXP '^[+]?[0-9\\s-]{7,20}$' OR telefono IS NULL)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE recuperacion_contraseñas (
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

CREATE TABLE comercios (
  id_comercio INT PRIMARY KEY AUTO_INCREMENT,
  id_usuario INT NOT NULL UNIQUE,
  razon_social VARCHAR(150) NOT NULL,
  ruc VARCHAR(20) NOT NULL UNIQUE,
  direccion_origen TEXT NOT NULL COMMENT 'Dirección de retiro de paquetes',
  ciudad VARCHAR(80) NOT NULL,
  tarifa_base DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,

  CONSTRAINT fk_comercio_usuario
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario) ON DELETE CASCADE,

  CONSTRAINT chk_razon_social
    CHECK (LENGTH(TRIM(razon_social)) >= 3),

  CONSTRAINT chk_tarifa_base_positiva
    CHECK (tarifa_base >= 0)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE repartidores (
  id_repartidor INT PRIMARY KEY AUTO_INCREMENT,
  id_usuario INT NOT NULL UNIQUE,
  tipo_vehiculo VARCHAR(30) NOT NULL COMMENT 'Bicicleta o Vehículo Eléctrico',
  matricula VARCHAR(50) NULL,
  huella_carbono_estimada DECIMAL(10,4) DEFAULT 0.0000,
  disponible BOOLEAN DEFAULT TRUE,
  fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,

  CONSTRAINT fk_repartidor_usuario
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario) ON DELETE CASCADE,

  CONSTRAINT chk_tipo_vehiculo_permitido
    CHECK (tipo_vehiculo IN ('Bicicleta', 'Vehículo Eléctrico')),

  CONSTRAINT chk_carbono_positivo
    CHECK (huella_carbono_estimada >= 0)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE estados_pedido (
  id_estado SMALLINT PRIMARY KEY,
  nombre_estado VARCHAR(30) NOT NULL UNIQUE,
  descripcion VARCHAR(100)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE pedidos (
  id_pedido BIGINT PRIMARY KEY AUTO_INCREMENT,
  id_comercio INT NOT NULL,
  id_repartidor INT NULL,
  id_estado SMALLINT NOT NULL DEFAULT 1,
  direccion_origen TEXT NOT NULL,
  direccion_destino TEXT NOT NULL,
  detalle_paquete VARCHAR(255) NOT NULL,
  peso_kg DECIMAL(8,3) NULL,
  distancia_km DECIMAL(8,3) NULL,
  tarifa_ecologica DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  co2_ahorrado_kg DECIMAL(10,4) NOT NULL DEFAULT 0.0000,
  observaciones TEXT NULL,
  fecha_solicitud DATETIME DEFAULT CURRENT_TIMESTAMP,
  fecha_asignacion DATETIME NULL,
  fecha_entrega DATETIME NULL,
  confirmacion_tipo VARCHAR(30) NULL COMMENT 'Firma Digital / Código QR',
  confirmacion_datos TEXT NULL,

  CONSTRAINT fk_pedido_comercio
    FOREIGN KEY (id_comercio) REFERENCES comercios(id_comercio) ON DELETE RESTRICT,

  CONSTRAINT fk_pedido_repartidor
    FOREIGN KEY (id_repartidor) REFERENCES repartidores(id_repartidor) ON DELETE RESTRICT,

  CONSTRAINT fk_pedido_estado
    FOREIGN KEY (id_estado) REFERENCES estados_pedido(id_estado),

  CONSTRAINT chk_peso_valido
    CHECK (peso_kg > 0 AND peso_kg <= 50 OR peso_kg IS NULL),

  CONSTRAINT chk_distancia_valida
    CHECK (distancia_km >= 0 OR distancia_km IS NULL),

  CONSTRAINT chk_tarifa_positiva
    CHECK (tarifa_ecologica >= 0),

  CONSTRAINT chk_co2_positivo
    CHECK (co2_ahorrado_kg >= 0),

  CONSTRAINT chk_confirmacion_valida
    CHECK (confirmacion_tipo IN ('Firma Digital', 'Código QR', 'Prueba Fotográfica') OR confirmacion_tipo IS NULL),

  CONSTRAINT chk_fecha_entrega_solo_cuando_entregado
    CHECK (
      (id_estado = 4 AND fecha_entrega IS NOT NULL)
      OR
      (id_estado <> 4 AND fecha_entrega IS NULL)
    )

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE historial_estados (
  id_historial BIGINT PRIMARY KEY AUTO_INCREMENT,
  id_pedido BIGINT NOT NULL,
  id_estado_anterior SMALLINT NULL,
  id_estado_nuevo SMALLINT NOT NULL,
  id_usuario_cambio INT NOT NULL,
  observacion VARCHAR(255) NULL COMMENT 'Notas, firma o código de confirmación',
  fecha_cambio DATETIME DEFAULT CURRENT_TIMESTAMP,

  CONSTRAINT fk_historial_pedido
    FOREIGN KEY (id_pedido) REFERENCES pedidos(id_pedido) ON DELETE RESTRICT,

  CONSTRAINT fk_historial_estado_anterior
    FOREIGN KEY (id_estado_anterior) REFERENCES estados_pedido(id_estado),

  CONSTRAINT fk_historial_estado_nuevo
    FOREIGN KEY (id_estado_nuevo) REFERENCES estados_pedido(id_estado),

  CONSTRAINT fk_historial_usuario
    FOREIGN KEY (id_usuario_cambio) REFERENCES usuarios(id_usuario) ON DELETE RESTRICT

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE metricas_diarias (
  fecha_reporte DATE PRIMARY KEY,
  total_pedidos INT NOT NULL DEFAULT 0,
  entregados INT NOT NULL DEFAULT 0,
  co2_total_ahorrado_kg DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
  km_recorridos_sin_emision DECIMAL(12,3) NOT NULL DEFAULT 0.000,

  CONSTRAINT chk_total_pedidos_positivo CHECK (total_pedidos >= 0),
  CONSTRAINT chk_entregados_positivo CHECK (entregados >= 0),
  CONSTRAINT chk_co2_total_positivo CHECK (co2_total_ahorrado_kg >= 0),
  CONSTRAINT chk_km_positivos CHECK (km_recorridos_sin_emision >= 0)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO roles (id_rol, nombre, descripcion) VALUES
(1, 'Comerciante', 'Usuario que publica pedidos de entrega'),
(2, 'Repartidor', 'Encargado de realizar las entregas'),
(3, 'Administrador', 'Gestión del sistema y métricas');

INSERT INTO estados_pedido (id_estado, nombre_estado, descripcion) VALUES
(1, 'Pendiente', 'Pedido creado sin asignar'),
(2, 'Asignado', 'Asignado a un repartidor'),
(3, 'En Camino', 'En proceso de entrega'),
(4, 'Entregado', 'Entrega finalizada con confirmación');

CREATE INDEX idx_pedidos_comercio ON pedidos(id_comercio, fecha_solicitud DESC);
CREATE INDEX idx_pedidos_repartidor ON pedidos(id_repartidor, id_estado);
CREATE INDEX idx_historial_pedido ON historial_estados(id_pedido, fecha_cambio DESC);
CREATE INDEX idx_usuarios_correo ON usuarios(correo);
CREATE INDEX idx_comercios_usuario ON comercios(id_usuario);
CREATE INDEX idx_repartidores_disponibles ON repartidores(disponible, tipo_vehiculo);
