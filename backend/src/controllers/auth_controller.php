<?php

declare(strict_types=1);

require_once __DIR__ . '/../middleware/request.php';
require_once __DIR__ . '/../services/auth_service.php';

function register(): never
{
    sendJson(registerUser(requestBody()), 201);
}

function login(): never
{
    sendJson(loginUser(requestBody()));
}
