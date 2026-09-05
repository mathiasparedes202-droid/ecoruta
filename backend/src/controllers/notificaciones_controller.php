<?php

declare(strict_types=1);

require_once __DIR__ . '/../services/notificaciones_service.php';

function getNotifications(object $claims): never
{
    $userId = (int) $claims->sub;
    sendJson([
        'notificaciones' => listNotificationsForUser($userId),
        'no_leidas' => unreadNotificationsCount($userId),
    ]);
}

function unreadNotifications(object $claims): never
{
    sendJson(['no_leidas' => unreadNotificationsCount((int) $claims->sub)]);
}

function markReadNotification(object $claims, int $notificationId): never
{
    sendJson(markNotificationRead((int) $claims->sub, $notificationId));
}

function markAllNotifications(object $claims): never
{
    sendJson(markAllNotificationsRead((int) $claims->sub));
}