<?php

declare(strict_types=1);

require_once __DIR__ . '/../middleware/request.php';
require_once __DIR__ . '/../services/admin_service.php';

function getUsers(object $claims): never
{
    requireAdministrator($claims);
    sendJson(['users' => listUsers()]);
}

function getRepartidores(object $claims): never
{
    requireAdministrator($claims);
    sendJson(['repartidores' => listRepartidores()]);
}

function patchUser(object $claims, int $userId): never
{
    requireAdministrator($claims);
    sendJson(setUserStatus($userId, requestBody()));
}

function storeUser(object $claims): never
{
    requireAdministrator($claims);
    sendJson(['user' => createUserByAdministrator(requestBody())], 201);
}
