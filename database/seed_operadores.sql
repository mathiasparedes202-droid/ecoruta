USE ecoruta_db;

INSERT INTO roles (id_rol, nombre, descripcion) VALUES
(1, 'Comerciante', 'Usuario que publica pedidos de entrega'),
(2, 'Repartidor', 'Encargado de realizar las entregas'),
(3, 'Administrador', 'Gestión del sistema y métricas')
ON DUPLICATE KEY UPDATE nombre = VALUES(nombre), descripcion = VALUES(descripcion);

START TRANSACTION;

INSERT INTO usuarios (
  id_rol, correo, contraseña_hash, documento_identidad, nombre_completo, telefono, activo
) VALUES (
  3,
  'admin@ecoruta.com',
  '$2y$10$fHbh/jmDwyAu.JyRjibQ3.RxyPO8b5PI4T/cui1nxpr193AFczXwe',
  '0999999999',
  'María Fernanda Vélez',
  '+593 99 555 0142',
  TRUE
)
ON DUPLICATE KEY UPDATE
  id_rol = VALUES(id_rol),
  contraseña_hash = VALUES(contraseña_hash),
  nombre_completo = VALUES(nombre_completo),
  telefono = VALUES(telefono),
  activo = TRUE;

INSERT INTO usuarios (
  id_rol, correo, contraseña_hash, documento_identidad, nombre_completo, telefono, activo
) VALUES (
  2,
  'carlos.mendoza@ecoruta.com',
  '$2y$10$ebc481wYjCbtGRxSmuRYAOJXTcMbnK7ExcYxUwYniruHMcCpPQB5G',
  '0912345678',
  'Carlos Mendoza',
  '+593 98 420 7781',
  TRUE
)
ON DUPLICATE KEY UPDATE
  id_rol = VALUES(id_rol),
  contraseña_hash = VALUES(contraseña_hash),
  nombre_completo = VALUES(nombre_completo),
  telefono = VALUES(telefono),
  activo = TRUE;

INSERT INTO repartidores (
  id_usuario, tipo_vehiculo, matricula, huella_carbono_estimada, disponible
)
SELECT id_usuario, 'Bicicleta', 'BICI-EC-042', 0.0000, TRUE
FROM usuarios
WHERE correo = 'carlos.mendoza@ecoruta.com'
ON DUPLICATE KEY UPDATE
  tipo_vehiculo = VALUES(tipo_vehiculo),
  matricula = VALUES(matricula),
  huella_carbono_estimada = VALUES(huella_carbono_estimada),
  disponible = TRUE;

COMMIT;

SELECT u.id_usuario, u.correo, r.nombre AS rol, u.nombre_completo, u.activo
FROM usuarios u
JOIN roles r ON r.id_rol = u.id_rol
WHERE u.correo IN ('admin@ecoruta.com', 'carlos.mendoza@ecoruta.com');
