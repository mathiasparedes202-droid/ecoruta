<?php

declare(strict_types=1);

require_once __DIR__ . '/../middleware/request.php';
require_once __DIR__ . '/../services/orders_service.php';

function getOrders(): never
{
    $repartidorId = isset($_GET['repartidor_id']) && ctype_digit((string) $_GET['repartidor_id'])
        ? (int) $_GET['repartidor_id']
        : null;
    sendJson(listOrders($repartidorId));
}

function getOrdersByCommerce(int $commerceId): never
{
    sendJson(listOrdersByCommerce($commerceId));
}

function storeOrder(): never
{
    sendJson(createOrder(requestBody()), 201);
}

function changeOrderStatus(int $orderId): never
{
    sendJson(updateOrderStatus($orderId, requestBody()));
}

function assignOrder(int $orderId): never
{
    sendJson(assignOrderToRepartidor($orderId, requestBody()));
}

function cancelOrderController(object $claims, int $orderId): never
{
    sendJson(cancelOrder($orderId, $claims, requestBody()));
}

function updateOrderController(object $claims, int $orderId): never
{
    sendJson(updateOrder($orderId, $claims, requestBody()));
}

function updatePaymentController(object $claims, int $orderId): never
{
    sendJson(updateOrderPayment($orderId, $claims, requestBody()));
}
