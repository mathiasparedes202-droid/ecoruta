<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../middleware/cors.php';
require_once __DIR__ . '/../middleware/request.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use Firebase\JWT\JWT;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailException;

function authenticatedUser(int $userId): array
{
    $statement = database()->prepare(
        'SELECT u.id_usuario, u.id_rol, r.nombre AS rol, u.correo, u.documento_identidad,
                u.nombre_completo, u.telefono, u.activo, u.debe_cambiar_contraseña,
                rep.id_repartidor, COALESCE(rep.tipo_vehiculo, "Bicicleta") AS tipo_vehiculo,
                COALESCE(rep.matricula, "BICI-EC-042") AS matricula,
                COALESCE(rep.disponible, 1) AS disponible,
                com.id_comercio, com.razon_social, com.ruc, com.direccion_origen AS direccion_comercio,
                com.lat, com.lng,
                com.ciudad, com.tarifa_base
         FROM usuarios u 
         JOIN roles r ON r.id_rol = u.id_rol
         LEFT JOIN repartidores rep ON rep.id_usuario = u.id_usuario
         LEFT JOIN comercios com ON com.id_usuario = u.id_usuario
         WHERE u.id_usuario = :id LIMIT 1'
    );
    $statement->execute(['id' => $userId]);
    $user = $statement->fetch();
    if (!$user || !(bool) $user['activo']) {
        sendJson(['message' => 'Tu usuario no está activo o no existe.'], 401);
    }
    return $user;
}
function updateRepartidorStatus(int $userId, array $body): array
{
    $pdo = database();
    $fields = [];
    $params = ['user_id' => $userId];

    if (isset($body['tipo_vehiculo'])) {
        $fields[] = 'tipo_vehiculo = :tipo';
        $params['tipo'] = in_array($body['tipo_vehiculo'], ['Bicicleta', 'Vehículo Eléctrico']) 
            ? $body['tipo_vehiculo'] : 'Bicicleta';
    }

    if (isset($body['disponible'])) {
        $fields[] = 'disponible = :disp';
        $params['disp'] = (bool)$body['disponible'] ? 1 : 0;
    }

    if (isset($body['matricula'])) {
        $fields[] = 'matricula = :mat';
        $params['mat'] = trim((string)$body['matricula']);
    }

    if (!empty($fields)) {
        $sql = 'UPDATE repartidores SET ' . implode(', ', $fields) . ' WHERE id_usuario = :user_id';
        $pdo->prepare($sql)->execute($params);
    }

    return ['message' => 'Configuración del repartidor guardada', 'user' => authenticatedUser($userId)];
}

function updateCommerce(int $userId, array $body): array
{
    $user = authenticatedUser($userId);
    if ((int) $user['id_rol'] !== 1 || !isset($user['id_comercio'])) {
        sendJson(['message' => 'Solo los comerciantes pueden editar su comercio'], 403);
    }

    $idComercio = (int) $user['id_comercio'];
    $sets = [];
    $params = ['id' => $idComercio];

    $textFields = [
        'razon_social', 'ruc', 'direccion_origen', 'ciudad', 'tarifa_base',
    ];
    foreach ($textFields as $field) {
        if (isset($body[$field]) && (string) $body[$field] !== '') {
            $sets[] = "$field = :$field";
            $params[$field] = trim((string) $body[$field]);
        }
    }

    if (isset($body['lat']) && $body['lat'] !== '' && is_numeric($body['lat'])) {
        $sets[] = 'lat = :lat';
        $params['lat'] = (float) $body['lat'];
    }
    if (isset($body['lng']) && $body['lng'] !== '' && is_numeric($body['lng'])) {
        $sets[] = 'lng = :lng';
        $params['lng'] = (float) $body['lng'];
    }

    if (empty($sets)) {
        sendJson(['message' => 'No hay datos de comercio para actualizar'], 422);
    }

    $sql = 'UPDATE comercios SET ' . implode(', ', $sets) . ' WHERE id_comercio = :id';
    database()->prepare($sql)->execute($params);

    return ['message' => '¡Datos del comercio guardados!', 'user' => authenticatedUser($userId)];
}

function updateProfile(int $userId, array $body): array
{
    $user = authenticatedUser($userId);
    $name = trim((string) ($body['nombre_completo'] ?? $user['nombre_completo']));
    $email = strtolower(trim((string) ($body['correo'] ?? $user['correo'])));
    $phone = trim((string) ($body['telefono'] ?? ($user['telefono'] ?? '')));
    if (strlen($name) < 3 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        sendJson(['message' => 'Revisá el nombre y el correo.'], 422);
    }

    $statement = database()->prepare(
        'UPDATE usuarios SET nombre_completo = :name, correo = :email, telefono = :phone WHERE id_usuario = :id'
    );
    $statement->execute(['name' => $name, 'email' => $email, 'phone' => $phone ?: null, 'id' => $userId]);
    return authenticatedUser($userId);
}

function changePassword(int $userId, array $body): array
{
    requireFields($body, ['contraseña_actual', 'nueva_contraseña']);
    $statement = database()->prepare('SELECT contraseña_hash FROM usuarios WHERE id_usuario = :id');
    $statement->execute(['id' => $userId]);
    $user = $statement->fetch();
    if (!$user || !password_verify((string) $body['contraseña_actual'], $user['contraseña_hash'])) {
        sendJson(['message' => 'La contraseña actual no coincide'], 422);
    }
    setNewPassword($userId, (string) $body['nueva_contraseña']);
    return ['message' => '¡Contraseña actualizada!'];
}

function setNewPassword(int $userId, string $password): void
{
    if (strlen($password) < 8) {
        sendJson(['message' => 'La contraseña debe tener al menos 8 caracteres'], 422);
    }
    database()->prepare(
        'UPDATE usuarios SET contraseña_hash = :hash, debe_cambiar_contraseña = FALSE WHERE id_usuario = :id'
    )->execute(['hash' => password_hash($password, PASSWORD_DEFAULT), 'id' => $userId]);
}

function requestPasswordReset(array $body): array
{
    requireFields($body, ['correo']);
    $email = strtolower(trim((string) $body['correo']));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        sendJson(['message' => 'El correo electrónico no es válido'], 422);
    }

    $statement = database()->prepare('SELECT id_usuario FROM usuarios WHERE correo = :email AND activo = TRUE LIMIT 1');
    $statement->execute(['email' => $email]);
    $user = $statement->fetch();
    $response = ['message' => 'Si el correo existe, recibirás instrucciones para recuperar tu cuenta'];
    if (!$user) {
        return $response;
    }

    $plainToken = bin2hex(random_bytes(32));
    database()->prepare(
        'INSERT INTO recuperacion_contraseñas (id_usuario, token_hash, expira_en)
         VALUES (:user, :token, DATE_ADD(NOW(), INTERVAL 30 MINUTE))'
    )->execute(['user' => $user['id_usuario'], 'token' => hash('sha256', $plainToken)]);

    try {
        sendRecoveryEmail($email, $plainToken);
    } catch (RuntimeException $error) {
        database()->prepare('DELETE FROM recuperacion_contraseñas WHERE token_hash = :token')
            ->execute(['token' => hash('sha256', $plainToken)]);
        throw $error;
    }
    return $response;
}

function sendRecoveryEmail(string $email, string $token): void
{
    $username = trim((string) ($_ENV['MAIL_USERNAME'] ?? ''));
    $password = preg_replace('/\s+/', '', (string) ($_ENV['MAIL_PASSWORD'] ?? ''));
    if ($username === '' || $password === '' || str_contains($username, 'tu-correo')) {
        throw new RuntimeException('Correo no configurado: define MAIL_USERNAME y MAIL_PASSWORD en backend/.env');
    }

    $frontendUrl = rtrim($_ENV['FRONTEND_URL'] ?? 'http://localhost:5174', '/');
    $resetUrl = $frontendUrl . '/?reset_token=' . rawurlencode($token);
    $mailer = new PHPMailer(true);
    try {
        $mailer->isSMTP();
        $mailer->Host = $_ENV['MAIL_HOST'] ?? 'smtp.gmail.com';
        $mailer->SMTPAuth = true;
        $mailer->Username = $username;
        $mailer->Password = $password;
        $mailer->SMTPSecure = ($_ENV['MAIL_ENCRYPTION'] ?? 'tls') === 'ssl'
            ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
        $mailer->Port = (int) ($_ENV['MAIL_PORT'] ?? 587);
        $mailer->CharSet = 'UTF-8';
        $fromAddress = $_ENV['MAIL_FROM_ADDRESS'] ?? $username;
        if (!filter_var($fromAddress, FILTER_VALIDATE_EMAIL) || str_contains($fromAddress, 'TU_CORREO')) {
            $fromAddress = $username;
        }
        $mailer->setFrom($fromAddress, $_ENV['MAIL_FROM_NAME'] ?? 'EcoRuta');
        $mailer->addAddress($email);
        $mailer->isHTML(true);
        $mailer->Subject = $_ENV['MAIL_SUBJECT_PASSWORD_RESET'] ?? 'Recuperación de cuenta EcoRuta';
        $mailer->Body = '<p>Hola,</p><p>Recibimos una solicitud para cambiar tu contraseña de EcoRuta.</p>'
            . '<p><a href="' . htmlspecialchars($resetUrl, ENT_QUOTES, 'UTF-8') . '">Restablecer mi contraseña</a></p>'
            . '<p>El enlace vence en 30 minutos y solo puede utilizarse una vez.</p>'
            . '<p>Si no realizaste esta solicitud, puedes ignorar este mensaje.</p>';
        $mailer->AltBody = "Restablece tu contraseña de EcoRuta aquí: {$resetUrl}\n\nEl enlace vence en 30 minutos.";
        $mailer->send();
    } catch (MailException $error) {
        error_log('Error SMTP EcoRuta: ' . $error->getMessage());
        $detail = ($_ENV['APP_ENV'] ?? 'production') === 'development'
            ? ' Detalle SMTP: ' . $error->getMessage() . ' Confirma que la contraseña de aplicación pertenece a ' . $username . '.'
            : '';
        throw new RuntimeException('No se pudo enviar el correo de recuperación. Revisa MAIL_HOST, MAIL_PORT y la contraseña de aplicación.' . $detail);
    }
}

function resetPassword(array $body): array
{
    requireFields($body, ['token', 'nueva_contraseña']);
    $statement = database()->prepare(
        'SELECT id_recuperacion, id_usuario FROM recuperacion_contraseñas
         WHERE token_hash = :token AND usado = FALSE AND expira_en > NOW() LIMIT 1'
    );
    $statement->execute(['token' => hash('sha256', (string) $body['token'])]);
    $reset = $statement->fetch();
    if (!$reset) {
        sendJson(['message' => 'El enlace es inválido o ya expiró'], 400);
    }

    setNewPassword((int) $reset['id_usuario'], (string) $body['nueva_contraseña']);
    database()->prepare('UPDATE recuperacion_contraseñas SET usado = TRUE, usado_en = NOW() WHERE id_recuperacion = :id')
        ->execute(['id' => $reset['id_recuperacion']]);
    return ['message' => '¡Contraseña restablecida!'];
}

function issueToken(array $user): string
{
    $issuedAt = time();
    $secret = $_ENV['JWT_SECRET'] ?? getenv('JWT_SECRET') ?: '721181d4304ecbe50c420aada6df6a4592f0213e264e764f3f2f2aee86abe019';
    return JWT::encode([
        'iss' => $_ENV['APP_URL'] ?? 'http://localhost:8000',
        'iat' => $issuedAt,
        'exp' => $issuedAt + (int) ($_ENV['JWT_EXPIRE'] ?? 3600),
        'sub' => (int) $user['id_usuario'],
        'rol' => (int) $user['id_rol'],
    ], $secret, 'HS256');
}
