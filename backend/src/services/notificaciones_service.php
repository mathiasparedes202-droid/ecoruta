<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../middleware/request.php';

/**
 * Avisos internos: cada usuario ve SÓLO los suyos (admin ve pedidos nuevos y
 * cancelaciones; el repartidor ve asignaciones, pagos confirmados, etc.).
 * Las funciones notifican por "rol" o por "repartidor" para no depender de
 * una lista de usuarios hardcodeada.
 */

function createNotification(int $destinoUserId, string $tipo, string $titulo, string $mensaje, ?int $idPedido = null): void
{
    $stmt = database()->prepare(
        'INSERT INTO notificaciones (id_usuario_destino, tipo, titulo, mensaje, id_pedido)
         VALUES (:user, :tipo, :titulo, :mensaje, :pedido)'
    );
    $stmt->execute([
        'user' => $destinoUserId,
        'tipo' => $tipo,
        'titulo' => $titulo,
        'mensaje' => $mensaje,
        'pedido' => $idPedido,
    ]);
}

function notifyAdmins(string $tipo, string $titulo, string $mensaje, ?int $idPedido = null): void
{
    $admins = database()->query('SELECT id_usuario FROM usuarios WHERE id_rol = 3 AND activo = TRUE')->fetchAll();
    foreach ($admins as $admin) {
        createNotification((int) $admin['id_usuario'], $tipo, $titulo, $mensaje, $idPedido);
    }
}

function notifyRepartidor(int $repartidorId, string $tipo, string $titulo, string $mensaje, ?int $idPedido = null): void
{
    $stmt = database()->prepare('SELECT id_usuario FROM repartidores WHERE id_repartidor = :rep LIMIT 1');
    $stmt->execute(['rep' => $repartidorId]);
    $rep = $stmt->fetch();
    if ($rep) {
        createNotification((int) $rep['id_usuario'], $tipo, $titulo, $mensaje, $idPedido);
    }
}

function notifyComercio(int $commerceId, string $tipo, string $titulo, string $mensaje, ?int $idPedido = null): void
{
    $stmt = database()->prepare('SELECT id_usuario FROM comercios WHERE id_comercio = :com LIMIT 1');
    $stmt->execute(['com' => $commerceId]);
    $com = $stmt->fetch();
    if ($com) {
        createNotification((int) $com['id_usuario'], $tipo, $titulo, $mensaje, $idPedido);
    }
}

function listNotificationsForUser(int $userId, int $limit = 40): array
{
    $stmt = database()->prepare(
        'SELECT id_notificacion, tipo, titulo, mensaje, id_pedido, leida, fecha
         FROM notificaciones
         WHERE id_usuario_destino = :user
         ORDER BY fecha DESC, id_notificacion DESC
         LIMIT :lim'
    );
    $stmt->bindValue('user', $userId, PDO::PARAM_INT);
    $stmt->bindValue('lim', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function unreadNotificationsCount(int $userId): int
{
    $stmt = database()->prepare(
        'SELECT COUNT(*) FROM notificaciones WHERE id_usuario_destino = :user AND leida = 0'
    );
    $stmt->execute(['user' => $userId]);
    return (int) $stmt->fetchColumn();
}

function markNotificationRead(int $userId, int $notificationId): array
{
    $stmt = database()->prepare(
        'UPDATE notificaciones SET leida = 1 WHERE id_notificacion = :id AND id_usuario_destino = :user'
    );
    $stmt->execute(['id' => $notificationId, 'user' => $userId]);
    if ($stmt->rowCount() === 0) {
        sendJson(['message' => 'Notificación no encontrada'], 404);
    }
    return ['message' => '¡Listo! Esa novedad quedó como leída'];
}

function markAllNotificationsRead(int $userId): array
{
    database()->prepare(
        'UPDATE notificaciones SET leida = 1 WHERE id_usuario_destino = :user AND leida = 0'
    )->execute(['user' => $userId]);
    return ['message' => '¡Todo leído! Quedaste al día con las novedades'];
}