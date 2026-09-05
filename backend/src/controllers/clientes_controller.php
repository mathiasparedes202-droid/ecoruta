<?php

declare(strict_types=1);

require_once __DIR__ . '/../middleware/request.php';
require_once __DIR__ . '/../middleware/auth.php';
require_once __DIR__ . '/../services/clientes_service.php';
require_once __DIR__ . '/../services/admin_service.php';

function requireCommerceOrAdmin(object $claims): int
{
    $userId = (int) ($claims->sub ?? 0);
    if ((int) ($claims->rol ?? 0) === 3) {
        return 0;
    }
    $commerceId = commerceIdOfUser($userId);
    if ($commerceId === null) {
        sendJson(['message' => 'Solo los comerciantes pueden gestionar clientes'], 403);
    }
    return $commerceId;
}

function getClientes(object $claims): never
{
    $commerceId = requireCommerceOrAdmin($claims);
    if ($commerceId === 0) {
        sendJson(['clientes' => listAllClientes()]);
    }
    sendJson(['clientes' => listClientes($commerceId)]);
}

function storeCliente(object $claims): never
{
    $commerceId = requireCommerceOrAdmin($claims);
    if ($commerceId === 0) {
        // El admin necesita saber a qué comercio asignar el cliente
        $commerceId = isset($_SERVER['HTTP_X_COMERCIO']) ? (int) $_SERVER['HTTP_X_COMERCIO'] : 0;
        if ($commerceId <= 0) {
            sendJson(['message' => 'Indica el comercio del cliente (cabecera X-Comercio)'], 422);
        }
    }
    sendJson(['cliente' => createCliente($commerceId, requestBody())], 201);
}

function patchCliente(object $claims, int $clienteId): never
{
    $commerceId = requireCommerceOrAdmin($claims);
    if ($commerceId === 0) {
        sendJson(['message' => 'Los administradores editan clientes desde el comercio correspondiente'], 403);
    }
    sendJson(['cliente' => updateCliente($clienteId, $commerceId, requestBody())]);
}

function removeCliente(object $claims, int $clienteId): never
{
    $commerceId = requireCommerceOrAdmin($claims);
    if ($commerceId === 0) {
        sendJson(['message' => 'Los administradores eliminan clientes desde el comercio correspondiente'], 403);
    }
    sendJson(deleteCliente($clienteId, $commerceId));
}