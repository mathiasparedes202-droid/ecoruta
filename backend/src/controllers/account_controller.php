<?php

declare(strict_types=1);

require_once __DIR__ . '/../middleware/request.php';
require_once __DIR__ . '/../services/account_service.php';

function getCurrentAccount(object $claims): never
{
    sendJson(['user' => authenticatedUser((int) $claims->sub)]);
}

function editCurrentAccount(object $claims): never
{
    sendJson(['user' => updateProfile((int) $claims->sub, requestBody())]);
}

function editCurrentPassword(object $claims): never
{
    sendJson(changePassword((int) $claims->sub, requestBody()));
}

function editRepartidorAccount(object $claims): never
{
    sendJson(updateRepartidorStatus((int) $claims->sub, requestBody()));
}

function editCurrentCommerce(object $claims): never
{
    sendJson(updateCommerce((int) $claims->sub, requestBody()));
}

function forgotAccount(): never
{
    try {
        sendJson(requestPasswordReset(requestBody()));
    } catch (RuntimeException $error) {
        sendJson(['message' => $error->getMessage(), 'code' => 'MAIL_DELIVERY_FAILED'], 503);
    }
}

function resetAccount(): never
{
    sendJson(resetPassword(requestBody()));
}