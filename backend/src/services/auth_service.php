<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../middleware/request.php';
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/account_service.php';

use Firebase\JWT\JWT;
function registerUser(array $body): array
{
    requireFields($body, ['id_rol', 'correo', 'contraseña', 'documento_identidad', 'nombre_completo']);

    $email = strtolower(trim((string) $body['correo']));
    $password = (string) $body['contraseña'];
    $role = (int) $body['id_rol'];
    $name = trim((string) $body['nombre_completo']);
    $document = trim((string) $body['documento_identidad']);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        sendJson(['message' => 'El correo electrónico no es válido'], 422);
    }
    if (strlen($password) < 8) {
        sendJson(['message' => 'La contraseña debe tener al menos 8 caracteres'], 422);
    }
    if ($role !== 1 && $role !== 2) {
        sendJson(['message' => 'El tipo de cuenta no es válido'], 422);
    }
    if (strlen($name) < 3 || strlen($document) < 5) {
        sendJson(['message' => 'Revisá el nombre y el documento de identidad.'], 422);
    }

    $allowedVehicles = ['Bicicleta', 'Vehículo Eléctrico'];
    $vehicle = trim((string) ($body['tipo_vehiculo'] ?? ''));
    if ($role === 2 && $vehicle !== '' && !in_array($vehicle, $allowedVehicles, true)) {
        sendJson(['message' => 'El tipo de vehículo no es válido'], 422);
    }

    $pdo = database();
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    $statement = $pdo->prepare(
        'INSERT INTO usuarios (id_rol, correo, contraseña_hash, documento_identidad, nombre_completo, telefono, debe_cambiar_contraseña)
         VALUES (:role, :email, :password, :document, :name, :phone, :forceChange)'
    );
    $statement->execute([
        'role' => $role,
        'email' => $email,
        'password' => $passwordHash,
        'document' => $document,
        'name' => $name,
        'phone' => $body['telefono'] ?? null,
        'forceChange' => $role === 1 ? 1 : 0,
    ]);

    $newUserId = (int) $pdo->lastInsertId();

    if ($role === 1) {
        // El comercio viene con el local: creamos también el comercio del nuevo usuario
        $pdo->prepare(
            'INSERT INTO comercios (id_usuario, razon_social, ruc, direccion_origen, ciudad, tarifa_base)
             VALUES (:user, :name, :document, :address, :city, :fee)'
        )->execute([
            'user' => $newUserId,
            'name' => $name,
            'document' => $document,
            'address' => $body['direccion_comercio'] ?? 'Dirección por configurar',
            'city' => $body['ciudad'] ?? 'Concepción',
            'fee' => (float) ($body['tarifa_base'] ?? 12000),
        ]);
    } else {
        // El repartidor entra con el perfil completo: vehículo y matrícula
        $pdo->prepare(
            'INSERT INTO repartidores (id_usuario, tipo_vehiculo, matricula, disponible)
             VALUES (:user, :vehicle, :matricula, 1)'
        )->execute([
            'user' => $newUserId,
            'vehicle' => $vehicle !== '' ? $vehicle : 'Bicicleta',
            'matricula' => trim((string) ($body['matricula'] ?? '')) ?: null,
        ]);
    }

    return ['id_usuario' => $newUserId, 'message' => '¡Cuenta creada! Ya podés iniciar sesión.'];
}

function loginUser(array $body): array
{
    requireFields($body, ['correo', 'contraseña']);

    $email = strtolower(trim((string) $body['correo']));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        sendJson(['message' => 'El correo electrónico no es válido'], 422);
    }

    $statement = database()->prepare(
        'SELECT u.id_usuario, u.id_rol, r.nombre AS rol, u.correo, u.nombre_completo,
            u.activo, u.debe_cambiar_contraseña, u.contraseña_hash,
            rep.id_repartidor, COALESCE(rep.tipo_vehiculo, "Bicicleta") AS tipo_vehiculo,
            COALESCE(rep.matricula, "BICI-EC-042") AS matricula,
            COALESCE(rep.disponible, 1) AS disponible,
            com.id_comercio, com.razon_social, com.ruc, com.direccion_origen AS direccion_comercio,
            com.lat, com.lng, com.ciudad, com.tarifa_base
         FROM usuarios u 
         JOIN roles r ON r.id_rol = u.id_rol
         LEFT JOIN repartidores rep ON rep.id_usuario = u.id_usuario
         LEFT JOIN comercios com ON com.id_usuario = u.id_usuario
         WHERE u.correo = :email LIMIT 1'
    );
    $statement->execute(['email' => $email]);
    $user = $statement->fetch();

    if (!$user || !(bool) $user['activo'] || !password_verify((string) $body['contraseña'], $user['contraseña_hash'])) {
        sendJson(['message' => 'El correo o la contraseña no coinciden. Probá de nuevo.'], 401);
    }

    database()->prepare('UPDATE usuarios SET fecha_ultimo_acceso = NOW() WHERE id_usuario = :id')
        ->execute(['id' => $user['id_usuario']]);
    unset($user['contraseña_hash']);

    $token = issueToken($user);

    return ['user' => $user, 'token' => $token];
}
