<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/account_service.php';

/**
 * Gestión del equipo (solo rol 3): listar usuarios, activar/desactivar cuentas,
 * cambiar roles y crear usuarios. Los repartidores que figuran en el listado
 * se cruzan con su actividad del día para que el administrador elija bien.
 */

function requireAdministrator(object $claims): void
{
    if ((int) ($claims->rol ?? 0) !== 3) {
        sendJson(['message' => 'Solo los administradores pueden gestionar usuarios'], 403);
    }
}

function listUsers(): array
{
    return database()->query(
        'SELECT u.id_usuario, u.id_rol, r.nombre AS rol, u.correo, u.documento_identidad,
                u.nombre_completo, u.telefono, u.activo, u.debe_cambiar_contraseña,
                u.fecha_ultimo_acceso
         FROM usuarios u JOIN roles r ON r.id_rol = u.id_rol
         ORDER BY u.nombre_completo'
    )->fetchAll();
}

function setUserStatus(int $userId, array $body): array
{
    if (!array_key_exists('activo', $body) && !array_key_exists('id_rol', $body)) {
        sendJson(['message' => 'No hay cambios para aplicar'], 422);
    }
    if (isset($body['activo']) && !is_bool($body['activo']) && !in_array((string) $body['activo'], ['0', '1', 'true', 'false'], true)) {
        sendJson(['message' => 'El campo activo debe ser un booleano'], 422);
    }

    $pdo = database();
    $target = $pdo->prepare('SELECT id_rol, correo FROM usuarios WHERE id_usuario = :id LIMIT 1');
    $target->execute(['id' => $userId]);
    if (!$target->fetch()) {
        sendJson(['message' => 'Usuario no encontrado'], 404);
    }

    if (isset($body['id_rol'])) {
        $role = (int) $body['id_rol'];
        $roles = $pdo->prepare('SELECT id_rol FROM roles WHERE id_rol = :role LIMIT 1');
        $roles->execute(['role' => $role]);
        if (!$roles->fetch()) {
            sendJson(['message' => 'El rol seleccionado no existe'], 422);
        }
        $pdo->prepare('UPDATE usuarios SET id_rol = :role WHERE id_usuario = :id')
            ->execute(['role' => $role, 'id' => $userId]);
    }

    if (isset($body['activo'])) {
        $active = in_array((string) $body['activo'], ['1', 'true'], true) || $body['activo'] === true;
        $pdo->prepare('UPDATE usuarios SET activo = :active WHERE id_usuario = :id')
            ->execute(['active' => $active ? 1 : 0, 'id' => $userId]);
    }

    return [
        'message' => '¡Usuario actualizado!',
        'user' => listUserById($userId),
    ];
}

function listUserById(int $userId): array
{
    $statement = database()->prepare(
        'SELECT u.id_usuario, u.id_rol, r.nombre AS rol, u.correo, u.documento_identidad,
                u.nombre_completo, u.telefono, u.activo, u.debe_cambiar_contraseña,
                u.fecha_ultimo_acceso
         FROM usuarios u JOIN roles r ON r.id_rol = u.id_rol
         WHERE u.id_usuario = :id LIMIT 1'
    );
    $statement->execute(['id' => $userId]);
    $row = $statement->fetch();
    if (!$row) {
        sendJson(['message' => 'Usuario no encontrado'], 404);
    }
    return $row;
}

function listRepartidores(): array
{
    $statement = database()->query(
        'SELECT r.id_repartidor, u.id_usuario, u.nombre_completo, u.correo, u.telefono,
                r.tipo_vehiculo, r.matricula, r.disponible,
                (SELECT COUNT(*) FROM pedidos p
                  WHERE p.id_repartidor = r.id_repartidor
                    AND p.id_estado IN (2, 3)) AS entregas_en_curso,
                (SELECT COUNT(*) FROM pedidos p
                  WHERE p.id_repartidor = r.id_repartidor
                    AND p.id_estado = 4
                    AND DATE(p.fecha_entrega) = CURDATE()) AS entregas_hoy
         FROM repartidores r
         JOIN usuarios u ON u.id_usuario = r.id_usuario
         ORDER BY r.disponible DESC, u.nombre_completo'
    );

    return array_map(
        static fn (array $row): array => [
            'id_repartidor' => (int) $row['id_repartidor'],
            'id_usuario' => (int) $row['id_usuario'],
            'nombre_completo' => $row['nombre_completo'],
            'correo' => $row['correo'],
            'telefono' => $row['telefono'],
            'tipo_vehiculo' => $row['tipo_vehiculo'],
            'matricula' => $row['matricula'],
            'disponible' => (bool) $row['disponible'],
            'entregas_en_curso' => (int) $row['entregas_en_curso'],
            'entregas_hoy' => (int) $row['entregas_hoy'],
        ],
        $statement->fetchAll()
    );
}

function createUserByAdministrator(array $body): array
{
    requireFields($body, ['nombre_completo', 'documento_identidad', 'correo', 'contraseña', 'id_rol']);

    $name = trim((string) $body['nombre_completo']);
    $document = trim((string) $body['documento_identidad']);
    $email = strtolower(trim((string) $body['correo']));
    $password = (string) $body['contraseña'];
    $role = (int) $body['id_rol'];

    if (strlen($name) < 3 || strlen($document) < 5 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        sendJson(['message' => 'Revisá los datos del usuario: el nombre, el documento y el correo deben ser válidos'], 422);
    }
    if (strlen($password) < 8) {
        sendJson(['message' => 'La contraseña debe tener al menos 8 caracteres'], 422);
    }

    $roles = database()->prepare('SELECT id_rol FROM roles WHERE id_rol = :role LIMIT 1');
    $roles->execute(['role' => $role]);
    if (!$roles->fetch()) {
        sendJson(['message' => 'El rol seleccionado no existe'], 422);
    }

    try {
        $statement = database()->prepare(
            'INSERT INTO usuarios (id_rol, correo, contraseña_hash, documento_identidad, nombre_completo, telefono, activo, debe_cambiar_contraseña)
             VALUES (:role, :email, :password, :document, :name, :phone, TRUE, TRUE)'
        );
        $statement->execute([
            'role' => $role,
            'email' => $email,
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'document' => $document,
            'name' => $name,
            'phone' => trim((string) ($body['telefono'] ?? '')) ?: null,
        ]);
    } catch (PDOException $error) {
        if ($error->getCode() === '23000') {
            sendJson(['message' => 'Ya existe un usuario con ese correo o documento'], 409);
        }
        throw $error;
    }

    return authenticatedUser((int) database()->lastInsertId());
}
