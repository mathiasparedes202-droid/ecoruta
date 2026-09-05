<?php

declare(strict_types=1);

require_once __DIR__ . '/../services/routes_service.php';

function getRoutes(): array
{
    return listRoutes();
}
